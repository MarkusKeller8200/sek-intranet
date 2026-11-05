<?php

namespace Modules\Zeiterfassung\Tests;

use App\Data\Contracts\UserRepository;
use App\Plugins\PluginManager;
use App\Support\Notifications;
use App\Support\Signatures;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Zeiterfassung\Buchungsarten;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use Modules\Zeiterfassung\Zeitkonto;
use Tests\TestCase;

/**
 * Pilotmodul Zeiterfassung (#8): erfassen, einreichen, Visum, Export, Rechte, eigener Datenspeicher.
 */
class ZeiterfassungTest extends TestCase
{
    private const MONAT = '2026-10';

    protected function setUp(): void
    {
        parent::setUp();
        // Modulseiten liegen nicht unter resources/js/pages
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    private function erfassen(array $daten = []): TestResponse
    {
        return $this->post('/zeiterfassung', $daten + [
            'datum' => self::MONAT.'-05',
            'art' => 'arbeit',
            'kategorie' => 'unterricht',
            'beginn' => '07:30',
            'ende' => '12:15',
            'pause_min' => 15,
        ]);
    }

    public function test_rules_for_hours_and_sums(): void
    {
        $this->assertSame(4.5, Zeitkonto::stunden('07:30', '12:15', 15));
        $s = Zeitkonto::summen([
            ['art' => 'arbeit', 'kategorie' => 'unterricht', 'stunden' => 4.5, 'projekt' => 'Sporttag'],
            ['art' => 'abwesenheit', 'kategorie' => 'ferien', 'stunden' => 8.4],
        ]);
        $this->assertSame(4.5, $s['arbeit']);
        $this->assertSame(8.4, $s['abwesenheit']);
        $this->assertSame(12.9, $s['total']);
        $this->assertSame(['Sporttag' => 4.5], $s['projekte']);
    }

    public function test_person_records_entries_in_own_store(): void
    {
        $this->signInAs('lehrperson');
        $this->erfassen()->assertRedirect('/zeiterfassung?monat='.self::MONAT);
        $this->erfassen(['art' => 'abwesenheit', 'kategorie' => 'ferien', 'beginn' => null, 'ende' => null, 'stunden' => 8.4, 'datum' => self::MONAT.'-06'])->assertRedirect();

        $this->get('/zeiterfassung?monat='.self::MONAT)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Zeiterfassung::start')
            ->has('eintraege', 2)
            ->where('summen.arbeit', 4.5)
            ->where('summen.abwesenheit', 8.4)
            ->where('status.status', 'offen'));

        $this->assertFileExists(base_path((string) config('intranet.data_path')).'/modules/zeiterfassung/zeit_eintraege.json');
    }

    public function test_validation(): void
    {
        $this->signInAs('lehrperson');
        $this->erfassen(['kategorie' => 'ferien'])->assertSessionHasErrors('kategorie');
        $this->erfassen(['beginn' => '12:00', 'ende' => '08:00'])->assertSessionHasErrors('ende');
        $this->erfassen(['beginn' => null, 'ende' => null])->assertSessionHasErrors('stunden');
        $this->get('/zeiterfassung?monat=2026-13')->assertNotFound();
    }

    public function test_submit_visum_return_and_export(): void
    {
        $lp = $this->signInAs('lehrperson');
        $id = $lp->getAuthIdentifier();
        $this->erfassen()->assertRedirect();
        $this->post('/zeiterfassung/einreichen', ['monat' => self::MONAT])->assertRedirect();
        // eingereicht = gesperrt
        $this->erfassen(['datum' => self::MONAT.'-07'])->assertSessionHasErrors('datum');

        $sl = $this->signInAs('schulleitung');
        $this->assertNotEmpty(array_filter((array) app(Notifications::class)->forUser($sl->getAuthIdentifier()), fn ($n): bool => str_contains((string) ($n['title'] ?? ''), 'hat eingereicht')));
        $this->get('/zeiterfassung/visum?monat='.self::MONAT)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Zeiterfassung::visum')
            ->where('personen.0.id', $id)
            ->where('personen.0.status', 'eingereicht'));
        $this->post("/zeiterfassung/visum/{$id}/zurueck", ['monat' => self::MONAT])->assertSessionHasErrors('bemerkung');
        $this->post("/zeiterfassung/visum/{$id}/zurueck", ['monat' => self::MONAT, 'bemerkung' => 'Bitte Pause prüfen'])->assertRedirect();

        // zurück = wieder bearbeitbar, erneut einreichen, dann visieren
        $this->signInAs('lehrperson');
        $this->get('/zeiterfassung?monat='.self::MONAT)->assertInertia(fn (Assert $page) => $page->where('status.status', 'zurueck')->where('status.bemerkung', 'Bitte Pause prüfen'));
        $this->erfassen(['datum' => self::MONAT.'-07'])->assertRedirect();
        $this->post('/zeiterfassung/einreichen', ['monat' => self::MONAT])->assertRedirect();

        $this->signInAs('schulleitung');
        $this->get("/zeiterfassung/visum/{$id}?monat=".self::MONAT)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Zeiterfassung::person')->has('eintraege', 2)->where('summen.total', 9));
        $this->post("/zeiterfassung/visum/{$id}/visieren", ['monat' => self::MONAT])->assertRedirect();

        $name = (string) app(UserRepository::class)->find($id)['name'];
        $csv = $this->get('/zeiterfassung/visum/export?monat='.self::MONAT)->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF\"Person\";", $csv);
        $this->assertStringContainsString("\"{$name}\"", $csv);
        $this->assertStringContainsString('"9.00"', $csv);
        $details = $this->get('/zeiterfassung/visum/export?monat='.self::MONAT.'&art=details')->streamedContent();
        $this->assertSame(3, substr_count($details, "\r\n"));
    }

    public function test_rights(): void
    {
        $lp = $this->signInAs('lehrperson');
        $this->erfassen()->assertRedirect();
        $eintrag = app(EintragRepository::class)->all()[0];

        $this->get('/zeiterfassung/visum')->assertForbidden();
        $this->get('/zeiterfassung/visum/export?monat='.self::MONAT)->assertForbidden();

        // fremden Eintrag nicht löschen
        $this->signInAs('verwaltung');
        $this->delete('/zeiterfassung/eintraege/'.$eintrag['id'])->assertNotFound();

        $this->signInAs('lehrperson');
        $this->delete('/zeiterfassung/eintraege/'.$eintrag['id'])->assertRedirect();
        $this->assertSame($lp->getAuthIdentifier(), $eintrag['person_id']);
    }

    public function test_signed_month_report_via_signatures(): void
    {
        Http::fake();
        Storage::fake('local');
        $manager = app(PluginManager::class);
        $manager->saveSettings('deepsign', ['demo' => true, '_allow_write' => true]);
        $manager->setEnabled('deepsign', true);

        $lp = $this->signInAs('lehrperson');
        $id = (string) $lp->getAuthIdentifier();
        $this->erfassen()->assertRedirect();
        $this->post('/zeiterfassung/einreichen', ['monat' => self::MONAT])->assertRedirect();

        $this->signInAs('schulleitung');
        // erst nach dem Visum
        $this->post("/zeiterfassung/visum/{$id}/unterschrift", ['monat' => self::MONAT, 'reihenfolge' => 'person_zuerst']);
        $this->assertSame([], app(Signatures::class)->all());
        $this->post("/zeiterfassung/visum/{$id}/visieren", ['monat' => self::MONAT])->assertRedirect();
        $this->post("/zeiterfassung/visum/{$id}/unterschrift", ['monat' => self::MONAT, 'reihenfolge' => 'person_zuerst', 'beobachtende' => 'personal@sekbuelach.example'])->assertRedirect();

        $auftrag = app(Signatures::class)->all()[0];
        $this->assertSame('versendet', $auftrag['status']);
        $this->assertSame('zeiterfassung', $auftrag['module']);
        $this->assertSame([0, 1], array_column($auftrag['signees'], 'order'));
        $this->assertSame(1, $auftrag['signees'][0]['position']['pageNumber'] ?? null, 'Unterschriftsfeld aus dem PDF');
        $this->get("/zeiterfassung/visum/{$id}?monat=".self::MONAT)->assertInertia(fn (Assert $page) => $page
            ->where('unterschrift.status', 'versendet')
            ->where('unterschrift.auftrag.signees.0.status', 'offen'));
        // kein zweiter Auftrag, solange einer läuft
        $this->post("/zeiterfassung/visum/{$id}/unterschrift", ['monat' => self::MONAT, 'reihenfolge' => 'gleichzeitig']);
        $this->assertCount(1, app(Signatures::class)->all());

        // Demo: beide unterschreiben → PDF bei der Person
        $this->signInAs('admin');
        $this->post("/unterschriften/{$auftrag['id']}/demo")->assertRedirect();
        $this->post("/unterschriften/{$auftrag['id']}/demo")->assertRedirect();
        $this->assertSame('unterschrieben', app(Signatures::class)->find((string) $auftrag['id'])['status'] ?? null);

        $this->signInAs('lehrperson');
        $this->get('/zeiterfassung?monat='.self::MONAT)->assertInertia(fn (Assert $page) => $page
            ->where('unterschrift.status', 'unterschrieben')
            ->where('unterschrift.pdf', "/zeiterfassung/unterschrift/{$id}/".self::MONAT));
        $pdf = $this->get("/zeiterfassung/unterschrift/{$id}/".self::MONAT)->assertOk()->streamedContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('Monatsrapport Oktober 2026', $pdf);

        // andere Personen ohne Visum-Recht nicht
        $this->signInAs('redaktion');
        $this->get("/zeiterfassung/unterschrift/{$id}/".self::MONAT)->assertForbidden();
    }

    public function test_booking_types_on_entries_and_maintained_list(): void
    {
        $this->signInAs('lehrperson');
        // Buchungsart wählen: Art und Kategorie folgen daraus
        $this->post('/zeiterfassung', ['datum' => self::MONAT.'-07', 'buchungsart' => 'unfall', 'stunden' => 8.4])->assertRedirect();
        $this->post('/zeiterfassung', ['datum' => self::MONAT.'-08', 'buchungsart' => 'homeoffice', 'kategorie' => 'schule', 'beginn' => '08:00', 'ende' => '12:00'])->assertRedirect();
        $this->post('/zeiterfassung', ['datum' => self::MONAT.'-09', 'buchungsart' => 'gibts-nicht', 'stunden' => 1])->assertSessionHasErrors('buchungsart');
        // älterer Eintrag ohne Buchungsart: aus Art und Kategorie
        $this->erfassen(['datum' => self::MONAT.'-12', 'art' => 'abwesenheit', 'kategorie' => 'krank', 'beginn' => null, 'ende' => null, 'stunden' => 4.2])->assertRedirect();

        $unfall = collect(app(EintragRepository::class)->all())->firstWhere('datum', self::MONAT.'-07');
        $this->assertSame(['abwesenheit', 'krank', 'unfall'], [$unfall['art'], $unfall['kategorie'], $unfall['buchungsart']]);
        $this->assertSame('krankheit', Buchungsarten::von(['art' => 'abwesenheit', 'kategorie' => 'krank']));
        $this->assertSame('arbeitszeit', Buchungsarten::von(['art' => 'arbeit', 'kategorie' => 'unterricht']));

        $this->get('/zeiterfassung?monat='.self::MONAT)->assertInertia(fn (Assert $page) => $page
            ->where('kategorien.buchungsarten.homeoffice.name', 'Homeoffice')
            ->where('summen.buchungsarten.unfall', 8.4)
            ->where('summen.buchungsarten.homeoffice', 4)
            ->where('summen.buchungsarten.krankheit', 4.2)
            ->where('eintraege', fn ($e): bool => collect($e)->pluck('buchungsart')->sort()->values()->all() === ['homeoffice', 'krankheit', 'unfall']));

        // Liste pflegen: nur mit Visum-Recht; umbenennen, neue Art, deaktivieren
        $this->get('/zeiterfassung/buchungsarten')->assertForbidden();
        $this->signInAs('schulleitung');
        $this->get('/zeiterfassung/buchungsarten')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Zeiterfassung::buchungsarten')->has('buchungsarten', 10));
        $zeilen = array_map(fn (string $k, array $b): array => ['key' => $k] + $b, array_keys(Buchungsarten::VORGABE), Buchungsarten::VORGABE);
        $zeilen[1]['name'] = 'Home-Office';
        $zeilen[9]['aktiv'] = false;
        $zeilen[] = ['key' => '', 'name' => 'Mutterschaft', 'art' => 'abwesenheit', 'aktiv' => true];
        $this->put('/zeiterfassung/buchungsarten', ['zeilen' => $zeilen])->assertSessionHasNoErrors();
        $liste = app(Buchungsarten::class)->liste();
        $this->assertSame('Home-Office', $liste['homeoffice']['name']);
        $this->assertFalse($liste['unbezahlt']['aktiv']);
        $this->assertSame('abwesenheit', $liste['mutterschaft']['art']);
        $this->assertArrayNotHasKey('unbezahlt', app(Buchungsarten::class)->liste(true));

        $nurAbwesenheit = [['key' => 'ferien', 'name' => 'Ferien', 'art' => 'abwesenheit', 'aktiv' => true]];
        $this->put('/zeiterfassung/buchungsarten', ['zeilen' => $nurAbwesenheit])->assertSessionHasErrors('zeilen');
    }
}
