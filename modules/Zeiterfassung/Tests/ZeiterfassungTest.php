<?php

namespace Modules\Zeiterfassung\Tests;

use App\Data\Contracts\UserRepository;
use App\Support\Notifications;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
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
}
