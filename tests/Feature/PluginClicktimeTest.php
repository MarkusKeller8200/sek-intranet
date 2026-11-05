<?php

namespace Tests\Feature;

use App\Auth\IntranetUser;
use App\Data\Contracts\DocumentRepository;
use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use App\Plugins\PersonalDataUnlock;
use App\Plugins\PluginManager;
use App\Support\PersonLinks;
use App\Support\RolePins;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Zeiterfassung\Buchungsarten;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;
use Plugins\Clicktime\Importer;
use Plugins\Clicktime\Months;
use Plugins\Clicktime\Plugin;
use RuntimeException;
use Tests\TestCase;

/**
 * ClickTime (#29, #30): Manifest aus der OpenAPI-Beschreibung, Demo-Modus, Anmeldung, Filter, Sperre der
 * Personendaten ausserhalb der Schweiz, Abgleich. Alle Antworten sind erfunden.
 */
class PluginClicktimeTest extends TestCase
{
    private const TOKEN_URL = 'https://login.clicktime.ch/auth/realms/openapi/protocol/openid-connect/token';

    private const API = 'https://login2.clicktime.ch/api/v3/customers/12345';

    /**
     * @param  array<string, mixed>  $settings
     */
    private function enable(array $settings, bool $allowWrite = false): void
    {
        $manager = app(PluginManager::class);
        $manager->saveSettings('clicktime', $settings + ['_allow_write' => $allowWrite]);
        $manager->setEnabled('clicktime', true);
    }

    private function realAccount(bool $swiss = true, bool $endtest = false): void
    {
        config(['intranet.server_in_switzerland' => $swiss]);
        $this->enable(['demo' => false, 'endtest' => $endtest, 'customer' => '12345', 'client_id' => 'intranet-client-1', 'client_secret' => 'geheimes-secret-987']);
    }

    public function test_manifest_with_all_endpoints_and_examples(): void
    {
        $manifest = app(PluginManager::class)->manifest('clicktime');
        $this->assertCount(51, $manifest->endpoints(), '51 Endpunkte aus der OpenAPI-Beschreibung von ClickTime');
        $this->assertSame('oauth2_client_credentials', $manifest->auth()['type']);
        $this->assertEqualsCanonicalizing(['client_id', 'client_secret'], $manifest->secretKeys());

        foreach ($manifest->endpoints() as $endpoint) {
            foreach ($endpoint['params'] ?? [] as $param) {
                $this->assertTrue(isset($param['default']) || isset($param['example']), "{$endpoint['id']}: {$param['name']} ohne Beispiel");
            }
            if (in_array($endpoint['method'], ['POST', 'PUT', 'PATCH'], true)) {
                $this->assertArrayHasKey('body', $endpoint, "{$endpoint['id']}: Beispiel-Body fehlt");
            }
            $this->assertStringNotContainsString('example.com', (string) json_encode($endpoint), "{$endpoint['id']}: Beispiel aus der Doku nicht ersetzt");
        }
        $this->assertTrue($manifest->endpoint('entries')['personal_data'] ?? false);
        $this->assertArrayNotHasKey('personal_data', $manifest->endpoint('departments'));
    }

    public function test_demo_mode_answers_without_requests(): void
    {
        Http::fake();
        $this->enable(['demo' => true]);
        $this->signInAs('admin');

        $this->postJson('/plugins/clicktime/test')->assertOk()->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Demo-Modus: 4 Abteilungen.');
        $this->postJson('/plugins/clicktime/aktion/employees', ['options' => []])->assertOk()
            ->assertJsonPath('rows.0.name', 'Andrea Muster')
            ->assertJsonPath('rows.0.department', 'Schulverwaltung')
            ->assertJsonPath('rows.2.managers', 'Beat Beispiel');
        $this->postJson('/plugins/clicktime/aktion/lookup', ['options' => ['email' => 'daniel.probe@sekbuelach.example']])->assertOk()
            ->assertJsonPath('rows.0.value', '3')
            ->assertJsonPath('rows.4.value', 'Beat Beispiel');

        // Buchungen: Filter wirken, am 3. Arbeitstag fehlt ein Eintrag
        $ctx = app(PluginManager::class)->context('clicktime', null);
        $entries = (array) $ctx->call('entries', ['filters' => 'day::GE::2026-10-01|day::LE::2026-10-31|employee_no::IN::3'])->json();
        $this->assertSame([3], array_values(array_unique(array_column($entries, 'employee_no'))));
        $this->assertNotContains('2026-10-05', array_column($entries, 'day'));
        $this->assertContains('2026-10-06', array_column($entries, 'day'));
        Http::assertNothingSent();
    }

    public function test_real_account_sends_token_and_repeated_filters(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 3600]),
            self::API.'/departments' => Http::response([['number' => '10', 'name' => 'Schulleitung']]),
            self::API.'/entries*' => Http::response([]),
        ]);
        $this->realAccount();
        $this->signInAs('admin');

        $this->postJson('/plugins/clicktime/test')->assertOk()->assertJsonPath('message', 'Verbunden mit ClickTime: 1 Abteilungen.');
        app(PluginManager::class)->context('clicktime', null)->call('entries', ['filters' => ['day::GE::2026-10-01', 'day::LE::2026-10-31']]);

        Http::assertSent(fn (Request $r): bool => $r->url() === self::TOKEN_URL && $r['grant_type'] === 'client_credentials' && $r['client_id'] === 'intranet-client-1');
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/entries?filters=day%3A%3AGE%3A%3A2026-10-01&filters=day%3A%3ALE%3A%3A2026-10-31')
            && $r->hasHeader('Authorization', 'Bearer token-abc-123456'));
    }

    public function test_outside_switzerland_endtest_only_without_personal_data(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 3600]),
            self::API.'/*' => Http::response([['number' => '10', 'name' => 'Schulleitung']]),
        ]);

        // ohne Endtest: Demo-Modus erzwungen
        $this->realAccount(swiss: false);
        $this->assertTrue((new Plugin)->demoActive(app(PluginManager::class)->context('clicktime', null)));

        // mit Endtest: Abteilungen echt, Mitarbeitende gesperrt
        $this->realAccount(swiss: false, endtest: true);
        $ctx = app(PluginManager::class)->context('clicktime', null);
        $this->assertSame(200, $ctx->call('departments')->status());
        try {
            $ctx->call('employees');
            $this->fail('Personendaten dürfen nicht abgefragt werden.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Schweizer Server', $e->getMessage());
        }
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/employees'));
    }

    public function test_match_links_by_email_and_creates_nothing(): void
    {
        Http::fake();
        $this->enable(['demo' => true], allowWrite: true);
        $this->signInAs('admin');
        $before = count(app(UserRepository::class)->all());

        $preview = $this->postJson('/plugins/clicktime/aktion/match', ['options' => []])->assertOk()->json('rows');
        $status = array_column($preview, 'status', 'no');
        $this->assertSame('zuordnen', $status['1']);
        $this->assertSame('ohne Treffer', $status['5']);

        $this->postJson('/plugins/clicktime/aktion/match', ['options' => [], 'apply' => true, 'selected' => ['1', '2', '5']])->assertOk()
            ->assertJsonPath('message', '2 Personen mit ClickTime verknüpft (Datenherkunft). Angelegt oder gelöscht wurde nichts.');

        $links = app(PersonLinks::class);
        $andrea = collect(app(UserRepository::class)->all())->firstWhere('email', 'andrea.muster@sekbuelach.example');
        $this->assertSame((string) $andrea['id'], $links->findPerson('clicktime', '1'));
        $this->assertNull($links->findPerson('clicktime', '5'));
        $this->assertCount($before, app(UserRepository::class)->all(), 'niemand angelegt');

        $again = array_column($this->postJson('/plugins/clicktime/aktion/match', ['options' => []])->json('rows'), 'status', 'no');
        $this->assertSame('zugeordnet', $again['1']);
        $this->assertSame('zuordnen', $again['3']);
    }

    public function test_filters_helper(): void
    {
        $this->assertSame(
            ['day' => ['GE', '2026-10-01'], 'day:LE' => ['LE', '2026-10-31'], 'employee_no' => ['IN', '1,2']],
            Plugin::filters('day::GE::2026-10-01|day::LE::2026-10-31|employee_no::IN::1,2'),
        );
    }

    public function test_target_time_per_day_from_cumulatives(): void
    {
        Http::fake();
        $this->enable(['demo' => true]);
        $this->signInAs('admin');

        // eine Abfrage je Tag (valid_from = valid_to), Sollzeit = Buchungsart 0; Kontrolle gegen den Monat
        $res = $this->postJson('/plugins/clicktime/aktion/targets', ['options' => ['month' => '2026-10', 'person' => '3']])->assertOk();
        $rows = $res->json('rows');
        $this->assertCount(31, $rows);
        $this->assertSame(['no' => '3', 'name' => 'Daniel Probe', 'weekday' => 'DO', 'day' => '01.10.', 'target' => '6.72', 'note' => ''], $rows[0]);
        $this->assertSame(['SA', '0.00'], [$rows[2]['weekday'], $rows[2]['target']]);
        $this->assertStringContainsString('31 Abfragen', (string) $res->json('message'));
        $this->assertStringContainsString('Summe Tage 147.84 h, Monat 147.84 h ✓', (string) $res->json('message'));

        $this->postJson('/plugins/clicktime/aktion/targets', ['options' => ['month' => '2026-13']])->assertStatus(422);
    }

    public function test_balances_and_month_sheet_with_gaps_and_pdf(): void
    {
        Http::fake();
        Storage::fake('local');
        $this->enable(['demo' => true]);
        $this->signInAs('admin');

        $this->postJson('/plugins/clicktime/aktion/balances', ['options' => ['date' => '2026-10-31', 'person' => '1']])->assertOk()
            ->assertJsonPath('rows.0.balance', 'Gleitzeit')
            ->assertJsonPath('rows.1.value', '15.50 d');

        // eine Person: Tage mit Lücke (3. Arbeitstag = Mo 05.10.2026) und Summen
        $one = $this->postJson('/plugins/clicktime/aktion/month', ['options' => ['month' => '2026-10', 'person' => 'daniel.probe@sekbuelach.example']])->assertOk();
        $this->assertStringContainsString('1 Arbeitstage ohne Buchung', (string) $one->json('message'));
        $this->assertContains(['day' => '05.10.2026', 'time' => '', 'hours' => '', 'type' => '', 'note' => 'keine Buchung'], $one->json('rows'));
        $this->assertSame('Arbeitszeit', $one->json('rows.0.type'));

        // alle: Übersicht, PDF je Person nur einmal abgelegt
        $all = $this->postJson('/plugins/clicktime/aktion/month', ['options' => ['month' => '2026-10', 'pdf' => true]])->assertOk();
        $this->assertCount(5, $all->json('rows'));
        // Kumulative im Aufbau der echten Antwort (cumulative_values, Sollzeit = Nummer 0, sonst Name)
        $this->assertSame('184.80', $all->json('rows.0.target'));
        $this->assertStringContainsString('Ferien 8.40 h', (string) $one->json('message'));
        $this->assertStringContainsString('5 Monatsblätter', (string) $all->json('message'));
        $doc = collect(app(DocumentRepository::class)->all())->firstWhere('source', 'clicktime:monatsblatt:2026-10:3');
        $this->assertSame('monatsblaetter', $doc['category']);
        $this->assertSame('manage-signatures', $doc['ability']);
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get((string) $doc['file_path']));
        $this->postJson('/plugins/clicktime/aktion/month', ['options' => ['month' => '2026-10', 'pdf' => true]])->assertOk();
        $this->assertCount(5, collect(app(DocumentRepository::class)->all())->filter(fn (array $d): bool => str_starts_with((string) ($d['source'] ?? ''), 'clicktime:monatsblatt:')));

        $this->postJson('/plugins/clicktime/aktion/month', ['options' => ['month' => '10.2026']])->assertJsonPath('status', 'error');
        Http::assertNothingSent();
    }

    public function test_changes_after_month_closing(): void
    {
        Http::fake();
        Storage::fake('local');
        $this->enable(['demo' => true]);
        $this->signInAs('admin');

        $this->postJson('/plugins/clicktime/aktion/changes', ['options' => ['month' => '2026-10']])->assertOk()
            ->assertJsonPath('status', 'warning');
        $this->postJson('/plugins/clicktime/aktion/close', ['options' => ['month' => '2026-10']])->assertOk()
            ->assertJsonPath('rows.0.entries', '21');
        Storage::disk('local')->assertExists('plugins/clicktime/abschluss/2026-10.json');

        // Demo simuliert nach dem Abschluss eine Korrektur bei Person 1
        $result = $this->postJson('/plugins/clicktime/aktion/changes', ['options' => ['month' => '2026-10', 'notify' => true]])->assertOk();
        $this->assertStringContainsString('1 neu, 1 geändert, 1 gelöscht', (string) $result->json('message'));
        $changes = array_column($result->json('rows'), 'change', 'day');
        $this->assertSame('geändert', $changes['01.10.2026']);
        $this->assertSame('neu', $changes['05.10.2026']);
        $this->assertSame('gelöscht', $changes['30.10.2026']);
        $this->assertContains('8.40 h 07:30–16:24 (Arbeitszeit)', array_column($result->json('rows'), 'before'));
        $this->assertGreaterThan(0, count(app(JsonStore::class)->all('notifications')));

        // erneuter Abschluss: keine Änderungen mehr
        $this->postJson('/plugins/clicktime/aktion/close', ['options' => ['month' => '2026-10']])->assertOk();
        $this->postJson('/plugins/clicktime/aktion/changes', ['options' => ['month' => '2026-10']])->assertOk()
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'keine Änderungen'));
    }

    public function test_diff_detects_new_changed_deleted(): void
    {
        $a = ['number' => '1', 'day' => '2026-10-01', 'time_from' => '07:30', 'time_to' => '12:00', 'hours' => 4.5, 'type' => '1000', 'activity' => ''];
        $b = ['number' => '2', 'day' => '2026-10-02'] + $a;
        $changed = ['hours' => 5.0] + $a;
        $new = ['number' => '3', 'day' => '2026-10-03'] + $a;
        $diff = Months::diff(['1' => ['1' => $a, '2' => $b]], ['1' => ['1' => $changed, '3' => $new]]);
        $this->assertSame(['geändert', 'gelöscht', 'neu'], array_column($diff, 'change'));
    }

    public function test_personal_data_unlocked_for_test_with_admin_pin(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 3600]),
            self::API.'/*' => Http::response([['employee_no' => 1, 'first_name' => 'Andrea', 'last_name' => 'Muster']]),
        ]);
        app(RolePins::class)->set('admin', '24681357', null);
        $this->realAccount(swiss: false, endtest: true);
        $ctx = fn () => app(PluginManager::class)->context('clicktime', null);

        // nur die Administration; falscher PIN wird abgewiesen
        $this->signInAs('ict');
        $this->post('/plugins/clicktime/personendaten', ['pin' => '24681357'])->assertForbidden();
        $this->signInAs('admin');
        $this->get('/plugins/clicktime')->assertInertia(fn (Assert $page) => $page
            ->where('personalData.until', null)->where('personalData.canUnlock', true)->where('personalData.endpoints', 38));
        $this->post('/plugins/clicktime/personendaten', ['pin' => '11112222'])->assertSessionHasErrors('pin');
        $this->assertFalse(app(PersonalDataUnlock::class)->active('clicktime'));

        // richtiger PIN: befristet freigeschaltet, Aufruf geht an ClickTime, im Änderungsverlauf festgehalten
        $this->post('/plugins/clicktime/personendaten', ['pin' => '24681357'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(200, $ctx()->call('employees')->status());
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/employees'));
        $this->assertNotNull(collect(app(JsonStore::class)->all('audit_log'))->firstWhere('source', 'Plugin: Personendaten für Test freigeschaltet'));
        $this->assertStringNotContainsString('24681357', (string) json_encode(app(JsonStore::class)->all('audit_log')));

        // nach Ablauf bzw. Sperren wieder gesperrt
        $this->travel(61)->minutes();
        $this->assertFalse(app(PersonalDataUnlock::class)->active('clicktime'));
        $this->travelBack();
        $this->post('/plugins/clicktime/personendaten', ['pin' => '24681357']);
        $this->delete('/plugins/clicktime/personendaten')->assertRedirect();
        $this->expectException(RuntimeException::class);
        $ctx()->call('employees');
    }

    public function test_entries_in_real_format(): void
    {
        // Aufbau wie die echte Antwort (05.11.2026), erfundene Werte: Nummer leer, Name mehrsprachig, Pause negativ,
        // gelöschte Buchungen in der Liste
        $rows = [
            ['number' => 1, 'employee_no' => 9, 'day' => '2026-10-01', 'time_from' => '07:35:00', 'time_to' => '18:18:00', 'hours' => 10.716666666666667,
                'entry_type_number' => null, 'entry_type_name' => ['de' => 'Arbeitszeit', 'en' => 'Working hours'], 'entry_type_type' => 'WORK', 'deleted' => false],
            ['number' => 2, 'employee_no' => 9, 'day' => '2026-10-01', 'time_from' => null, 'time_to' => null, 'hours' => -0.5,
                'entry_type_number' => null, 'entry_type_name' => ['de' => 'Pause'], 'entry_type_short' => 'PP', 'entry_type_type' => 'WORK', 'deleted' => false],
            ['number' => 3, 'employee_no' => 9, 'day' => '2026-10-02', 'time_from' => null, 'time_to' => null, 'hours' => 8.4,
                'entry_type_number' => null, 'entry_type_name' => ['de' => 'Ferien', 'en' => 'Holidays'], 'entry_type_type' => 'HOLIDAYS', 'deleted' => true],
        ];
        $a = Months::normalize($rows[0]);
        $this->assertSame(['1', '2026-10-01', '07:35', '18:18', 10.72, 'Arbeitszeit', 'Arbeitszeit', 'WORK'], [$a['number'], $a['day'], $a['time_from'], $a['time_to'], $a['hours'], $a['type'], $a['type_name'], $a['category']]);
        $this->assertSame(-0.5, Months::normalize($rows[1])['hours']);
        $this->assertSame('-0.50 h (Pause)', Months::describe(Months::normalize($rows[1])));

        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 3600]),
            self::API.'/entries*' => Http::response($rows),
        ]);
        $this->realAccount();
        $entries = (new Months(app(PluginManager::class)->context('clicktime', null)))->entries('2026-10');
        $this->assertSame(['1', '2'], array_map('strval', array_keys($entries['9'])), 'gelöschte Buchung fehlt');
    }

    public function test_mapping_suggestions_are_saved(): void
    {
        Http::fake();
        $this->enable(['demo' => true, 'mapping' => "Arbeitszeit = arbeitszeit\nSollzeit = -"], allowWrite: true);
        $this->signInAs('admin');

        $rows = collect($this->postJson('/plugins/clicktime/aktion/mapping', ['options' => []])->assertOk()->json('rows'))->keyBy('name');
        $this->assertSame('zugeordnet', $rows['Arbeitszeit']['status']);
        $this->assertSame('Vorschlag', $rows['Ferien']['status']);
        $this->assertSame('Ferien', $rows['Ferien']['suggest']);
        $this->assertSame('Pause des Tages', $rows['Pause']['suggest']);
        $this->assertSame('nicht übernehmen', $rows['Sollzeit']['current']);

        $this->postJson('/plugins/clicktime/aktion/mapping', ['options' => [], 'apply' => true, 'selected' => ['ferien', 'pause']])->assertOk()
            ->assertJsonPath('message', fn (string $m): bool => str_starts_with($m, '2 Buchungsarten zugeordnet'));
        $mapping = (string) app(PluginManager::class)->state('clicktime')['settings']['mapping'];
        $this->assertStringContainsString('Ferien = ferien', $mapping);
        $this->assertStringContainsString('Pause = pause', $mapping);
        $this->assertStringContainsString('Arbeitszeit = arbeitszeit', $mapping);
        $this->assertTrue(app(PluginManager::class)->state('clicktime')['settings']['demo'], 'übrige Einstellungen bleiben');
    }

    public function test_compare_with_own_time_tracking(): void
    {
        Http::fake();
        $this->enable(['demo' => true]);
        $this->signInAs('admin');
        $daniel = collect(app(UserRepository::class)->all())->firstWhere('email', 'daniel.probe@sekbuelach.example');
        app(PersonLinks::class)->recordSync((string) $daniel['id'], 'clicktime', 'source', '3');
        $eintraege = app(EintragRepository::class);
        foreach ([['2026-10-01', 'arbeitszeit', 'arbeit', 8.4], ['2026-10-02', 'arbeitszeit', 'arbeit', 6.0], ['2026-10-05', 'ferien', 'abwesenheit', 8.4]] as [$tag, $b, $art, $h]) {
            $eintraege->create(['person_id' => (string) $daniel['id'], 'datum' => $tag, 'monat' => '2026-10', 'art' => $art, 'buchungsart' => $b,
                'kategorie' => $art === 'arbeit' ? 'unterricht' : 'ferien', 'stunden' => $h]);
        }

        $result = $this->postJson('/plugins/clicktime/aktion/compare', ['options' => ['month' => '2026-10', 'person' => '3', 'all' => true]])->assertOk();
        $status = array_column($result->json('rows'), 'status', 'day');
        $this->assertSame('gleich', $status['01.10.2026']);
        $this->assertSame('Abweichung', $status['02.10.2026']);
        $this->assertSame('nur Zeiterfassung', $status['05.10.2026']);
        $this->assertSame('nur ClickTime', $status['06.10.2026']);
        $this->assertStringContainsString('1 Tage gleich, 1 mit Abweichung', (string) $result->json('message'));
        $row = collect($result->json('rows'))->firstWhere('day', '02.10.2026');
        $this->assertSame('Arbeitszeit 8.40', $row['clicktime']);
        $this->assertSame('Arbeitszeit 6.00', $row['own']);

        // Person ohne Zuordnung wird gemeldet
        $this->postJson('/plugins/clicktime/aktion/compare', ['options' => ['month' => '2026-10', 'person' => '4']])->assertOk()
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Ohne Intranet-Person') && str_contains($m, 'Eva Fiktiv'));
        Http::assertNothingSent();
    }

    public function test_import_month_into_time_tracking(): void
    {
        Http::fake();
        Storage::fake('local');
        $this->enable(['demo' => true, 'mapping' => "Pause = pause\nSollzeit = -"]);
        $admin = $this->signInAs('admin');
        $andrea = collect(app(UserRepository::class)->all())->firstWhere('email', 'andrea.muster@sekbuelach.example');
        $eintraege = app(EintragRepository::class);
        // von Hand erfasst: bleibt
        $eintraege->create(['person_id' => (string) $andrea['id'], 'datum' => '2026-10-05', 'monat' => '2026-10', 'art' => 'arbeit', 'buchungsart' => 'arbeitszeit', 'kategorie' => 'verwaltung', 'stunden' => 2.0]);

        // ohne Zuordnung: Hinweis; Knopf in der Zeiterfassung fehlt
        $this->postJson('/plugins/clicktime/aktion/import', ['options' => ['month' => '2026-10', 'person' => '1']])->assertOk()->assertJsonPath('status', 'warning');
        $this->get('/zeiterfassung?monat=2026-10')->assertInertia(fn (Assert $page) => $page->where('clicktime', null));
        app(PersonLinks::class)->recordSync((string) $andrea['id'], 'clicktime', 'source', '1');

        // Vorschau ändert nichts
        $this->postJson('/plugins/clicktime/aktion/import', ['options' => ['month' => '2026-10', 'person' => '1']])->assertOk()
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Vorschau 2026-10 – Andrea Muster: 21 neu') && str_contains($m, 'Arbeitszeit (clicktime)'));
        $this->assertCount(1, $eintraege->fuerPersonMonat((string) $andrea['id'], '2026-10'));

        // Knopf in der eigenen Zeiterfassung (Administration) übernimmt
        $this->actingAs(new IntranetUser($andrea));
        $this->get('/zeiterfassung?monat=2026-10')->assertInertia(fn (Assert $page) => $page->where('clicktime.nr', '1'));
        $this->post('/zeiterfassung/aus-clicktime', ['monat' => '2026-10'])->assertRedirect();
        $liste = collect($eintraege->fuerPersonMonat((string) $andrea['id'], '2026-10'));
        $this->assertCount(22, $liste);
        $import = $liste->firstWhere('datum', '2026-10-01');
        $this->assertSame(['clicktime', 'ct_arbeitszeit', 'ct:Arbeitszeit', '07:30', '16:24', 8.4], [$import['quelle'], $import['buchungsart'], $import['kategorie'], $import['beginn'], $import['ende'], (float) $import['stunden']]);
        $this->assertSame('Arbeitszeit (clicktime)', app(Buchungsarten::class)->liste()['ct_arbeitszeit']['name']);
        $this->assertStringContainsString('Arbeitszeit = ct_arbeitszeit', (string) app(PluginManager::class)->state('clicktime')['settings']['mapping']);
        $this->assertNotNull($liste->firstWhere('kategorie', 'verwaltung'), 'von Hand erfasst bleibt');

        // zweites Mal: nichts doppelt; Korrektur in ClickTime (Demo nach Abschluss) wird nachgeführt
        $this->actingAs($admin);
        Cache::put('clicktime-demo:closed:2026-10', true, 60);
        $this->postJson('/plugins/clicktime/aktion/import', ['options' => ['month' => '2026-10', 'person' => '1', 'apply' => true]])->assertOk()
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, '1 neu, 1 geändert, 1 gelöscht'));
        $this->assertCount(22, $eintraege->fuerPersonMonat((string) $andrea['id'], '2026-10'));
        $this->assertSame(6.0, (float) collect($eintraege->fuerPersonMonat((string) $andrea['id'], '2026-10'))->firstWhere('quelle_id', '101001')['stunden']);

        // eingereichter Monat: gesperrt
        app(MonatRepository::class)->setzen((string) $andrea['id'], '2026-10', ['status' => 'eingereicht']);
        $this->postJson('/plugins/clicktime/aktion/import', ['options' => ['month' => '2026-10', 'person' => '1', 'apply' => true]])->assertJsonPath('status', 'error');
        Http::assertNothingSent();
    }

    public function test_import_moves_pause_to_longest_block(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-abc-123456', 'expires_in' => 3600]),
            self::API.'/entries*' => Http::response([
                ['number' => 11, 'employee_no' => 9, 'day' => '2026-10-01', 'time_from' => '07:30:00', 'time_to' => '12:00:00', 'hours' => 4.5, 'entry_type_name' => ['de' => 'Arbeitszeit'], 'entry_type_type' => 'WORK', 'notes' => null, 'deleted' => false],
                ['number' => 12, 'employee_no' => 9, 'day' => '2026-10-01', 'time_from' => '13:00:00', 'time_to' => '18:30:00', 'hours' => 5.5, 'entry_type_name' => ['de' => 'Arbeitszeit'], 'entry_type_type' => 'WORK', 'notes' => 'Elternabend', 'deleted' => false],
                ['number' => 13, 'employee_no' => 9, 'day' => '2026-10-01', 'time_from' => null, 'time_to' => null, 'hours' => -0.5, 'entry_type_name' => ['de' => 'Pause'], 'entry_type_type' => 'WORK', 'notes' => 'Pausenkorrektur', 'deleted' => false],
                ['number' => 14, 'employee_no' => 9, 'day' => '2026-10-02', 'time_from' => null, 'time_to' => null, 'hours' => 8.4, 'entry_type_name' => ['de' => 'Ferien'], 'entry_type_type' => 'HOLIDAYS', 'notes' => null, 'deleted' => false],
            ]),
        ]);
        $this->realAccount();
        $eva = collect(app(UserRepository::class)->all())->firstWhere('email', 'eva.fiktiv@sekbuelach.example');
        $ctx = app(PluginManager::class)->context('clicktime', null);
        (new Importer($ctx))->run((string) $eva['id'], '9', '2026-10', true);
        $liste = collect(app(EintragRepository::class)->fuerPersonMonat((string) $eva['id'], '2026-10'))->keyBy('quelle_id');
        $this->assertCount(3, $liste);
        $this->assertSame([30, 5.0, 'Elternabend'], [$liste['12']['pause_min'], (float) $liste['12']['stunden'], $liste['12']['notiz']]);
        $this->assertNull($liste['11']['pause_min']);
        $this->assertSame(['abwesenheit', 'ferien', 'ferien'], [$liste['14']['art'], $liste['14']['buchungsart'], $liste['14']['kategorie']]);
    }
}
