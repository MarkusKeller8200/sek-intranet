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
use Modules\Zeiterfassung\MonatsblattPdf;
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

    public function test_report_hours_format_and_rows(): void
    {
        $this->assertSame(['10:13', '-1:14', '0:00', '8:24'], [MonatsblattPdf::hm(10.2167), MonatsblattPdf::hm(-1.2333), MonatsblattPdf::hm(0.0), MonatsblattPdf::hm(8.4)]);
        $eintraege = [
            ['datum' => '2026-10-01', 'art' => 'arbeit', 'buchungsart' => 'arbeitszeit', 'kategorie' => 'unterricht', 'beginn' => '07:30', 'ende' => '16:24', 'pause_min' => 30, 'stunden' => 8.4, 'notiz' => 'Erfundene Notiz'],
            ['datum' => '2026-10-02', 'art' => 'abwesenheit', 'buchungsart' => 'ferien', 'kategorie' => 'ferien', 'stunden' => 8.4],
        ];
        $soll = ['2026-10-01' => 8.4, '2026-10-02' => 8.4, '2026-10-05' => 8.4];
        $pdf = MonatsblattPdf::erstellen('Test Person', self::MONAT, $eintraege, Zeitkonto::summen($eintraege), null, null,
            ['person' => 'Test Person', 'schulleitung' => ''], [], ['soll' => $soll, 'untertitel' => '99 Test Person', 'unterschriftsfelder' => false, 'heute' => '2026-10-05'])['pdf'];
        // Pause als eigene Zeile, Block brutto, Notiz, Tag ohne Buchung, Wochentag, Total mit Soll
        foreach (['(-0:30)', '(8:54)', '(7:30)', '(Erfundene Notiz)', '(keine Buchung)', '(Do.)', '(1.10)', '(25:12)', '(-8:24)', '(99 Test Person)'] as $text) {
            $this->assertStringContainsString($text, $pdf);
        }
    }

    public function test_compensation_is_not_credited(): void
    {
        $eintraege = [
            ['datum' => '2026-10-19', 'art' => 'abwesenheit', 'buchungsart' => 'kompensation', 'kategorie' => 'kompensation', 'stunden' => 8.4],
            ['datum' => '2026-10-20', 'art' => 'abwesenheit', 'buchungsart' => 'ct_kompensation_minussaldo', 'kategorie' => 'kompensation', 'stunden' => 8.4],
            ['datum' => '2026-10-21', 'art' => 'abwesenheit', 'buchungsart' => 'ferien', 'kategorie' => 'ferien', 'stunden' => 8.4],
        ];
        $this->assertFalse(Zeitkonto::angerechnet($eintraege[0]));
        $this->assertFalse(Zeitkonto::angerechnet($eintraege[1]));
        $this->assertTrue(Zeitkonto::angerechnet($eintraege[2]), 'Ferien zählen');
        $summen = Zeitkonto::summen($eintraege);
        $this->assertSame(25.2, $summen['total']);
        $this->assertSame(8.4, $summen['ist']);
        // Kompensationstag: Ist 0:00, Differenz −Soll, aber keine Warnung «Arbeitszeit prüfen»
        $soll = ['2026-10-19' => 8.4, '2026-10-20' => 8.4, '2026-10-21' => 8.4];
        $this->assertSame([], Zeitkonto::pruefungen(self::MONAT, $eintraege, $soll, '2026-10-31'));
        $pdf = MonatsblattPdf::erstellen('Test Person', self::MONAT, $eintraege, $summen, null, null, ['person' => 'Test Person', 'schulleitung' => ''], [],
            ['soll' => $soll, 'unterschriftsfelder' => false, 'heute' => '2026-10-31'])['pdf'];
        $this->assertStringContainsString('(0:00)', $pdf);
        $this->assertStringContainsString('(-16:48)', $pdf, 'Total-Differenz: 8:24 Ist gegen 25:12 Soll');
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

    public function test_day_checks_missing_booking_clock_in_out_and_deviation(): void
    {
        $soll = ['2026-10-01' => 8.4, '2026-10-02' => 8.4, '2026-10-03' => 0.0, '2026-10-05' => 8.4, '2026-10-06' => 8.4, '2026-10-07' => 8.4, '2026-10-30' => 8.4];
        $eintraege = [
            ['id' => 'a', 'datum' => '2026-10-01', 'beginn' => '07:30', 'ende' => '16:24', 'stunden' => 8.4],
            ['id' => 'b', 'datum' => '2026-10-05', 'beginn' => '07:30', 'ende' => null, 'stunden' => 8.4],
            ['id' => 'c', 'datum' => '2026-10-06', 'beginn' => null, 'ende' => '16:00', 'stunden' => 8.4],
            ['id' => 'd', 'datum' => '2026-10-07', 'beginn' => '07:30', 'ende' => '11:30', 'stunden' => 4.0],
            // Ferien ohne Zeiten: kein Fehler
            ['id' => 'e', 'datum' => '2026-10-02', 'beginn' => null, 'ende' => null, 'stunden' => 8.4],
        ];
        $p = Zeitkonto::pruefungen(self::MONAT, $eintraege, $soll, '2026-10-29');
        $texte = fn (string $tag): array => array_column($p[$tag] ?? [], 'text');
        $this->assertSame([], $texte('2026-10-01'));
        $this->assertSame([], $texte('2026-10-02'), 'Ferien ohne Zeiten');
        $this->assertSame([], $texte('2026-10-03'), 'Soll 0');
        $this->assertSame(['Prüfen – Austrittsbuchung fehlt'], $texte('2026-10-05'));
        $this->assertSame(['b'], $p['2026-10-05'][0]['eintraege']);
        $this->assertSame('fehler', $p['2026-10-05'][0]['art']);
        $this->assertSame(['Prüfen – Eintrittsbuchung fehlt'], $texte('2026-10-06'));
        $this->assertSame(['Arbeitszeit prüfen'], $texte('2026-10-07'), '4.0 h statt 8.4 h');
        $this->assertSame('warnung', $p['2026-10-07'][0]['art']);
        $this->assertSame([], $texte('2026-10-30'), 'künftiger Tag');
        // 20 % genau: keine Warnung; darüber: Warnung
        $this->assertSame([], Zeitkonto::pruefungen(self::MONAT, [['id' => 'x', 'datum' => '2026-10-01', 'stunden' => 10.08]], ['2026-10-01' => 8.4], '2026-10-29'));
        $this->assertNotSame([], Zeitkonto::pruefungen(self::MONAT, [['id' => 'x', 'datum' => '2026-10-01', 'stunden' => 10.2]], ['2026-10-01' => 8.4], '2026-10-29'));
        // ohne Sollzeit (keine ClickTime-Präsenzen): kein «keine Buchung»
        $this->assertSame([], Zeitkonto::pruefungen(self::MONAT, [], [], '2026-10-29'));
        $tage = Zeitkonto::pruefungen(self::MONAT, [], ['2026-10-01' => 8.4], '2026-10-29');
        $this->assertSame([['art' => 'hinweis', 'text' => 'Prüfen – keine Buchung', 'eintraege' => []]], $tage['2026-10-01']);
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
            ->has('pruefungen')
            ->where('status.status', 'abgeschlossen')
            ->where('monatsblatt', "/zeiterfassung/monatsblatt/{$id}/".self::MONAT));
        $this->get("/zeiterfassung/monatsblatt/{$id}/".self::MONAT)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        // gesperrt: kein zweiter Abschluss, kein Übernehmen
        $this->post('/zeiterfassung/abschliessen', ['monat' => self::MONAT])->assertRedirect();
        $this->assertSame((string) $status['abgeschlossen_at'], (string) $months->status($id, self::MONAT)['abgeschlossen_at']);

        // Monatsrapport im Aufbau von ClickTime (#168): Tabelle, Total, Saldi aus ClickTime, Seitenzahl
        $pdf = (string) $this->get("/zeiterfassung/rapport/{$id}/".self::MONAT)->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        foreach (['Monatsrapport Oktober 2026', 'Bemerkungen', 'Diff.', 'Total', 'Saldi', 'Gleitzeit', 'Vormonat', 'In ClickTime abgeschlossen', '(Seite 1 / '] as $text) {
            $this->assertStringContainsString($text, $pdf);
        }

        // andere Person ohne Visum-Recht sieht das Monatsblatt nicht
        $this->signInAs('lehrperson');
        $this->get("/zeiterfassung/monatsblatt/{$id}/".self::MONAT)->assertForbidden();
        $this->get("/zeiterfassung/rapport/{$id}/".self::MONAT)->assertForbidden();

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
            ->where('fehlend', fn ($tage): bool => collect($tage)->contains('2026-10-01') && ! collect($tage)->contains('2026-10-05')));

        // Erinnerung an offene Monate
        $sl = $this->signInAs('schulleitung');
        $this->get('/zeiterfassung/visum?monat='.self::MONAT)->assertInertia(fn (Assert $page) => $page
            ->where('personen.0.status', 'offen')
            ->where('personen.0.fehlend', fn ($n): bool => $n > 0)
            ->where('personen.0.pruefen.fehler', 0));
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
