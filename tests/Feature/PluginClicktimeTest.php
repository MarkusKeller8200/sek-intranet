<?php

namespace Tests\Feature;

use App\Data\Contracts\DocumentRepository;
use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use App\Data\Store\JsonStore;
use App\Plugins\PersonalDataUnlock;
use App\Plugins\PluginManager;
use App\Support\PersonLinks;
use App\Support\RolePins;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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

        // alle: Übersicht, PDF je Person nur einmal abgelegt
        $all = $this->postJson('/plugins/clicktime/aktion/month', ['options' => ['month' => '2026-10', 'pdf' => true]])->assertOk();
        $this->assertCount(5, $all->json('rows'));
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
        $this->assertContains('8.40 h 07:30–16:24 (Art 1000)', array_column($result->json('rows'), 'before'));
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
}
