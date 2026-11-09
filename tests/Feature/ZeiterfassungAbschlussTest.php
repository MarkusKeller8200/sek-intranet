<?php

namespace Tests\Feature;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use App\Plugins\PluginManager;
use App\Support\Notifications;
use App\Support\PersonLinks;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;
use Modules\Zeiterfassung\Monatsabschluss;
use Modules\Zeiterfassung\Zeitkonto;
use Tests\TestCase;

/**
 * Monatsabschluss (#164), Monatsblatt-Ablage (#34), Vollständigkeit und Erinnerung (#33). Alle Daten erfunden,
 * ClickTime im Demo-Modus.
 */
class ZeiterfassungAbschlussTest extends TestCase
{
    private const MONAT = '2026-10';

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);
        Storage::fake('local');
        Http::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function andrea(): array
    {
        return collect(app(UserRepository::class)->all())->firstWhere('email', 'andrea.muster@sekbuelach.example');
    }

    private function clicktime(): string
    {
        $manager = app(PluginManager::class);
        $manager->saveSettings('clicktime', ['demo' => true, 'mapping' => "Pause = pause\nSollzeit = -", '_allow_write' => false]);
        $manager->setEnabled('clicktime', true);
        $id = (string) $this->andrea()['id'];
        app(PersonLinks::class)->recordSync($id, 'clicktime', 'source', '1');

        return $id;
    }

    public function test_missing_working_days(): void
    {
        $eintraege = [['datum' => '2026-10-01', 'stunden' => 8.0]];
        $this->assertSame(['2026-10-02', '2026-10-05'], Zeitkonto::fehlendeTage(self::MONAT, $eintraege, [], [], '2026-10-05'));
        // Feiertag zählt nicht
        $this->assertSame(['2026-10-05'], Zeitkonto::fehlendeTage(self::MONAT, $eintraege, ['2026-10-02' => 'Test'], [], '2026-10-05'));
        // mit Sollzeit: nur Tage mit Soll > 0
        $this->assertSame(['2026-10-03'], Zeitkonto::fehlendeTage(self::MONAT, $eintraege, [], ['2026-10-01' => 8.4, '2026-10-02' => 0.0, '2026-10-03' => 4.0], '2026-10-05'));
    }

    public function test_clicktime_person_closes_month_in_clicktime(): void
    {
        $id = $this->clicktime();
        $this->actingAs(new IntranetUser($this->andrea()));
        $months = app(MonatRepository::class);

        // Einreichen gibt es mit ClickTime nicht
        $this->post('/zeiterfassung/einreichen', ['monat' => self::MONAT])->assertRedirect();
        $this->assertSame('offen', $months->status($id, self::MONAT)['status'] ?? 'offen');

        $this->post('/zeiterfassung/abschliessen', ['monat' => self::MONAT])->assertRedirect();
        $status = $months->status($id, self::MONAT);
        $this->assertSame('abgeschlossen', $status['status']);
        $this->assertSame($id, $status['abgeschlossen_by']);
        $this->assertFalse($status['abgeschlossen_stellvertretend']);
        $this->assertCount(21, app(EintragRepository::class)->fuerPersonMonat($id, self::MONAT), 'letzter Abgleich');
        Storage::disk('local')->assertExists(Monatsabschluss::datei($id, self::MONAT));
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get(Monatsabschluss::datei($id, self::MONAT)));

        $this->get('/zeiterfassung?monat='.self::MONAT)->assertInertia(fn (Assert $page) => $page
            ->where('status.status', 'abgeschlossen')
            ->where('monatsblatt', "/zeiterfassung/monatsblatt/{$id}/".self::MONAT));
        $this->get("/zeiterfassung/monatsblatt/{$id}/".self::MONAT)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        // gesperrt: kein zweiter Abschluss, kein Übernehmen
        $this->post('/zeiterfassung/abschliessen', ['monat' => self::MONAT])->assertRedirect();
        $this->assertSame((string) $status['abgeschlossen_at'], (string) $months->status($id, self::MONAT)['abgeschlossen_at']);

        // andere Person ohne Visum-Recht sieht das Monatsblatt nicht
        $this->signInAs('lehrperson');
        $this->get("/zeiterfassung/monatsblatt/{$id}/".self::MONAT)->assertForbidden();

        // Schulleitung: benachrichtigt, Nachbuchung erkennen (übernommene Buchung lokal fehlt → in ClickTime «neu»)
        $sl = $this->signInAs('schulleitung');
        $this->assertNotEmpty(array_filter((array) app(Notifications::class)->forUser($sl->getAuthIdentifier()), fn ($n): bool => str_contains((string) ($n['title'] ?? ''), 'in ClickTime abgeschlossen')));
        $this->get("/zeiterfassung/visum/{$id}?monat=".self::MONAT)->assertInertia(fn (Assert $page) => $page
            ->where('clicktime', true)
            ->where('abgeschlossenVon', (string) $this->andrea()['name']));
        $eintrag = app(EintragRepository::class)->fuerPersonMonat($id, self::MONAT)[0];
        app(EintragRepository::class)->delete((string) $eintrag['id']);
        $this->post("/zeiterfassung/visum/{$id}/vergleichen", ['monat' => self::MONAT])->assertRedirect();
        $this->assertSame(1, $months->status($id, self::MONAT)['abweichung']['anzahl']);
        $this->assertCount(20, app(EintragRepository::class)->fuerPersonMonat($id, self::MONAT), 'Vergleich übernimmt nichts');

        // zurückgeben, dann stellvertretend abschliessen (vermerkt, Person benachrichtigt)
        $this->post("/zeiterfassung/visum/{$id}/zurueck", ['monat' => self::MONAT, 'bemerkung' => 'Nachbuchung übernehmen'])->assertRedirect();
        $this->assertSame('zurueck', $months->status($id, self::MONAT)['status']);
        $this->post("/zeiterfassung/visum/{$id}/abschliessen", ['monat' => self::MONAT])->assertRedirect();
        $status = $months->status($id, self::MONAT);
        $this->assertSame('abgeschlossen', $status['status']);
        $this->assertTrue($status['abgeschlossen_stellvertretend']);
        $this->assertSame((string) $sl->getAuthIdentifier(), $status['abgeschlossen_by']);
        $this->assertNull($status['abweichung']);
        $this->assertCount(21, app(EintragRepository::class)->fuerPersonMonat($id, self::MONAT));
        $this->assertNotEmpty(array_filter((array) app(Notifications::class)->forUser($id), fn ($n): bool => str_contains((string) ($n['title'] ?? ''), 'Abschluss bestätigt')));

        // Export für die Lohnbuchhaltung enthält abgeschlossene Monate
        $csv = $this->get('/zeiterfassung/visum/export?monat='.self::MONAT)->assertOk()->streamedContent();
        $this->assertStringContainsString((string) $this->andrea()['name'], $csv);
    }

    public function test_without_clicktime_visum_files_month_sheet_and_reminder(): void
    {
        $lp = $this->signInAs('lehrperson');
        $id = (string) $lp->getAuthIdentifier();
        $this->post('/zeiterfassung', ['datum' => self::MONAT.'-05', 'art' => 'arbeit', 'kategorie' => 'unterricht', 'beginn' => '07:30', 'ende' => '12:15', 'pause_min' => 15])->assertRedirect();
        $this->get('/zeiterfassung?monat='.self::MONAT)->assertInertia(fn (Assert $page) => $page
            ->where('fehlend', fn ($tage): bool => in_array('2026-10-01', (array) $tage, true) && ! in_array('2026-10-05', (array) $tage, true)));

        // Erinnerung an offene Monate
        $sl = $this->signInAs('schulleitung');
        $this->get('/zeiterfassung/visum?monat='.self::MONAT)->assertInertia(fn (Assert $page) => $page
            ->where('personen.0.status', 'offen')
            ->where('personen.0.fehlend', fn ($n): bool => $n > 0));
        $this->post('/zeiterfassung/visum/erinnerung', ['monat' => self::MONAT])->assertRedirect();
        $this->assertNotEmpty(array_filter((array) app(Notifications::class)->forUser($id), fn ($n): bool => str_contains((string) ($n['title'] ?? ''), 'bitte abschliessen')));
        $this->assertNotNull(app(MonatRepository::class)->status($id, self::MONAT)['erinnert_at']);

        // einreichen und visieren → Monatsblatt abgelegt
        $this->actingAs($lp);
        $this->post('/zeiterfassung/einreichen', ['monat' => self::MONAT])->assertRedirect();
        $this->actingAs($sl);
        $this->post("/zeiterfassung/visum/{$id}/visieren", ['monat' => self::MONAT])->assertRedirect();
        Storage::disk('local')->assertExists(Monatsabschluss::datei($id, self::MONAT));
        $this->get("/zeiterfassung/visum/{$id}?monat=".self::MONAT)->assertInertia(fn (Assert $page) => $page
            ->where('clicktime', false)
            ->where('monatsblatt', "/zeiterfassung/monatsblatt/{$id}/".self::MONAT));
        $this->get("/zeiterfassung/monatsblatt/{$id}/".self::MONAT)->assertOk();
        $this->actingAs($lp);
        $this->get("/zeiterfassung/monatsblatt/{$id}/".self::MONAT)->assertOk();
        // Abschluss in ClickTime nur für Personen mit ClickTime
        $this->post('/zeiterfassung/abschliessen', ['monat' => self::MONAT])->assertForbidden();
    }
}
