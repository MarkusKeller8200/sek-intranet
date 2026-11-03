<?php

namespace Modules\Ablaeufe\Tests;

use App\Modules\ModuleRegistry;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Ablaeufe\Ablauf;
use Modules\Ablaeufe\Data\Contracts\AblaufRepository;
use Tests\TestCase;

/**
 * Abläufe Verwaltung (#44–#48): Verzeichnis, Schritte, Personengruppen, Startinhalt, Rechte je Rolle.
 */
class AblaeufeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Modulseiten liegen nicht unter resources/js/pages
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ablauf(array $overrides = []): array
    {
        return $overrides + [
            'titel' => 'Klassenlager',
            'bereich' => 'schulbetrieb',
            'status' => 'gueltig',
            'beschreibung' => 'Vorbereitung und Durchführung',
            'verantwortlich' => 'Schulleitung',
            'gueltig_ab' => '2026-08-01',
            'phasen' => [['key' => 'vorher', 'titel' => 'Vorbereitung'], ['key' => '', 'titel' => 'Nachher']],
            'schritte' => [
                ['id' => 'neu-a1', 'phase' => 'vorher', 'text' => 'Lagerhaus reservieren', 'verantwortung' => 'LP', 'kommunikation' => 'Eltern',
                    'dokumente' => ['gibt-es-nicht'], 'bemerkung' => '', 'frist_tage' => -120, 'gilt_fuer' => ['lehrpersonen', 'unbekannt'], 'gegenstueck' => 'neu-b1'],
                ['id' => 'neu-b1', 'phase' => 'nachher', 'text' => 'Abrechnung', 'verantwortung' => 'LP', 'kommunikation' => '',
                    'dokumente' => [], 'bemerkung' => 'mit Belegen', 'frist_tage' => 14, 'gilt_fuer' => [], 'gegenstueck' => null],
                ['id' => '', 'phase' => 'gibt-es-nicht', 'text' => 'verworfen'],
                ['id' => 'neu-c1', 'phase' => 'vorher', 'text' => '   '],
            ],
        ];
    }

    public function test_seeded_directory_with_staff_workflow_and_groups(): void
    {
        $this->signInAs('verwaltung');

        $this->get('/ablaeufe')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Ablaeufe::index')
            ->where('ablaeufe.0.id', 'mitarbeitende')
            ->where('ablaeufe.0.schritte', 29 + 17 + 5)
            ->where('darfBearbeiten', true));

        $this->get('/ablaeufe/mitarbeitende')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Ablaeufe::show')
            ->has('ablauf.phasen', 3)
            ->where('ablauf.schritte.6.text', 'Microsoft Login erstellen gemäss Rollen- und Berechtigungskonzept')
            ->where('ablauf.schritte.6.verantwortung', 'IT')
            ->where('ablauf.schritte.6.gegenstueck', 'off-06')
            ->has('gruppen', 12)
            ->where('gruppen.11.name', 'Schulsozialarbeit'));
    }

    public function test_create_cleans_phases_and_steps(): void
    {
        $this->signInAs('schulleitung');
        $this->post('/ablaeufe', $this->ablauf())->assertRedirect();

        $item = collect(app(AblaufRepository::class)->all())->firstWhere('titel', 'Klassenlager');
        $this->assertNotNull($item);
        $this->assertSame(['vorher', 'nachher'], array_column($item['phasen'], 'key'));
        $this->assertCount(2, $item['schritte'], 'unbekannte Phase und leerer Text verworfen');
        [$a, $b] = $item['schritte'];
        $this->assertSame(['lehrpersonen'], $a['gilt_fuer']);
        $this->assertSame([], $a['dokumente']);
        $this->assertSame(-120, $a['frist_tage']);
        $this->assertSame('neu-b1', $a['gegenstueck']);
        $this->assertSame('mit Belegen', $b['bemerkung']);

        // Gegenstück in derselben Phase wird entfernt
        $this->put('/ablaeufe/'.$item['id'], $this->ablauf(['schritte' => [
            ['id' => 'x1', 'phase' => 'vorher', 'text' => 'A', 'gegenstueck' => 'x2'],
            ['id' => 'x2', 'phase' => 'vorher', 'text' => 'B'],
        ]]))->assertRedirect('/ablaeufe/'.$item['id']);
        $this->assertNull(app(AblaufRepository::class)->find($item['id'])['schritte'][0]['gegenstueck']);
    }

    public function test_validation(): void
    {
        $this->signInAs('admin');
        $this->post('/ablaeufe', $this->ablauf(['titel' => '']))->assertSessionHasErrors('titel');
        $this->post('/ablaeufe', $this->ablauf(['phasen' => [['key' => 'x', 'titel' => '  ']]]))->assertSessionHasErrors('phasen');
        $this->post('/ablaeufe', $this->ablauf(['bereich' => 'falsch']))->assertSessionHasErrors('bereich');
    }

    public function test_drafts_only_for_editors_and_no_access_without_right(): void
    {
        $this->signInAs('admin');
        $this->post('/ablaeufe', $this->ablauf(['titel' => 'Gültig']))->assertRedirect();

        // Lehrpersonen haben das Recht standardmässig nicht
        $this->signInAs('lehrperson');
        $this->get('/ablaeufe')->assertForbidden();

        // Recht «Abläufe ansehen» für Lehrpersonen einschalten → nur gültige Abläufe sichtbar, keine Bearbeitung
        app(ModuleRegistry::class)->setRightRoles('ablaeufe', 'view-ablaeufe', ['admin', 'schulleitung', 'verwaltung', 'lehrperson'], null);
        $this->refreshApplication();
        $this->withoutVite();
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->signInAs('lehrperson');
        $this->get('/ablaeufe')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('ablaeufe', fn ($list): bool => collect($list)->pluck('titel')->all() === ['Gültig'])
            ->where('darfBearbeiten', false));
        $this->get('/ablaeufe/mitarbeitende')->assertNotFound();
        $this->get('/ablaeufe/neu')->assertForbidden();
    }

    public function test_rights_per_role_on_module_page(): void
    {
        $this->signInAs('admin');
        $this->get('/einstellungen/module')->assertInertia(fn (Assert $page) => $page
            ->where('roles.lehrperson', 'Lehrperson')
            ->where('modules', fn ($mods): bool => collect($mods)->firstWhere('key', 'ablaeufe')['rights']['view-ablaeufe']['default_roles'] === ['admin', 'schulleitung', 'verwaltung']));

        $this->post('/einstellungen/module/ablaeufe/rechte/view-ablaeufe', ['roles' => ['admin', 'gibt-es-nicht']])->assertRedirect();
        $registry = app(ModuleRegistry::class);
        $this->assertSame(['admin'], $registry->rightRoles('view-ablaeufe', ['admin', 'schulleitung', 'verwaltung']));

        // gleich wie Vorgabe → Übersteuerung entfernt
        $this->post('/einstellungen/module/ablaeufe/rechte/view-ablaeufe', ['roles' => ['verwaltung', 'admin', 'schulleitung']])->assertRedirect();
        $this->assertSame(['admin', 'schulleitung', 'verwaltung'], $registry->rightRoles('view-ablaeufe', ['admin', 'schulleitung', 'verwaltung']));

        $this->post('/einstellungen/module/ablaeufe/rechte/gibt-es-nicht', ['roles' => []])->assertNotFound();
        $this->signInAs('verwaltung');
        $this->post('/einstellungen/module/ablaeufe/rechte/view-ablaeufe', ['roles' => []])->assertForbidden();
    }

    public function test_groups_crud_and_removal_from_steps(): void
    {
        $this->signInAs('verwaltung');
        $this->post('/ablaeufe/gruppen', ['name' => 'Praktikantinnen', 'bemerkung' => 'befristet'])->assertRedirect();
        $this->get('/ablaeufe/gruppen')->assertInertia(fn (Assert $page) => $page
            ->component('Ablaeufe::gruppen')
            ->has('gruppen', 13)
            ->where('gruppen.12.name', 'Praktikantinnen'));

        $this->post('/ablaeufe', $this->ablauf())->assertRedirect();
        $this->delete('/ablaeufe/gruppen/lehrpersonen')->assertRedirect();
        $item = collect(app(AblaufRepository::class)->all())->firstWhere('titel', 'Klassenlager');
        $this->assertSame([], $item['schritte'][0]['gilt_fuer']);
    }

    public function test_menu_replaces_sharepoint_workflows(): void
    {
        $this->signInAs('verwaltung');
        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('navigation', function ($groups): bool {
                $items = collect(collect($groups)->firstWhere('key', 'verwaltung')['items'] ?? []);

                return $items->contains(fn ($i) => $i['title'] === 'Abläufe Verwaltung' && $i['href'] === '/ablaeufe')
                    && ! $items->contains('key', 'workflows');
            }));
    }

    public function test_slug_and_phase_keys(): void
    {
        $this->assertSame('ueberpruefung-zugriff', Ablauf::slug('Überprüfung Zugriff'));
        $this->assertSame(['a', 'a-2'], array_column(Ablauf::phasen([['key' => 'a', 'titel' => 'A'], ['key' => 'a', 'titel' => 'B']]), 'key'));
    }

    public function test_settings_tables_with_start_values(): void
    {
        $this->signInAs('verwaltung');
        $this->get('/ablaeufe/einstellungen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Ablaeufe::einstellungen')
            ->where('aktiv', 'orte')
            ->where('anzahl.fachbereiche', 9)
            ->where('anzahl.mitwirkung', 4)
            ->has('listen', 5));
        $this->get('/ablaeufe/einstellungen?liste=rollen')->assertInertia(fn (Assert $page) => $page
            ->where('eintraege.0.kuerzel', 'SL')
            ->where('eintraege.0.fachbereich_id', 'sl')
            ->where('fachbereiche.0.label', 'SB – Schulbehörde'));
    }

    public function test_settings_crud_rules(): void
    {
        $this->signInAs('schulleitung');
        $eintrag = ['kuerzel' => 'DAZ', 'bezeichnung' => 'Deutsch als Zweitsprache', 'beschreibung' => 'Lehrperson DaZ', 'url' => 'https://example.org/daz', 'notiz' => 'n', 'fachbereich_id' => 'fsp'];
        $this->post('/ablaeufe/einstellungen/rollen', $eintrag)->assertRedirect('/ablaeufe/einstellungen?liste=rollen');
        $this->post('/ablaeufe/einstellungen/rollen', ['kuerzel' => 'daz'] + $eintrag)->assertSessionHasErrors('kuerzel');
        $this->post('/ablaeufe/einstellungen/rollen', ['kuerzel' => 'X', 'url' => 'ftp://x'] + $eintrag)->assertSessionHasErrors('url');
        $this->post('/ablaeufe/einstellungen/rollen', ['kuerzel' => 'Y', 'fachbereich_id' => 'gibt-es-nicht'] + $eintrag)->assertSessionHasErrors('fachbereich_id');
        $this->post('/ablaeufe/einstellungen/gibt-es-nicht', $eintrag)->assertNotFound();

        // Fachbereich mit Rollen nicht löschbar, ohne Rollen schon
        $this->delete('/ablaeufe/einstellungen/fachbereiche/fsp')->assertRedirect();
        $this->get('/ablaeufe/einstellungen?liste=fachbereiche')->assertInertia(fn (Assert $page) => $page->where('anzahl.fachbereiche', 9));
        $this->delete('/ablaeufe/einstellungen/fachbereiche/ff')->assertRedirect();
        $this->get('/ablaeufe/einstellungen?liste=fachbereiche')->assertInertia(fn (Assert $page) => $page->where('anzahl.fachbereiche', 8));

        // Orte: Hierarchie, keine Schleifen
        $this->post('/ablaeufe/einstellungen/orte', ['kuerzel' => 'HG', 'bezeichnung' => 'Haus Grün', 'typ' => 'schulhaus', 'parent_id' => 'sek'])->assertRedirect();
        $hg = collect($this->get('/ablaeufe/einstellungen?liste=orte')->viewData('page')['props']['eintraege'])->firstWhere('kuerzel', 'HG');
        $this->put('/ablaeufe/einstellungen/orte/sek', ['kuerzel' => 'SEK', 'bezeichnung' => 'Sekundarschule Bülach', 'typ' => 'standort', 'parent_id' => $hg['id']])->assertSessionHasErrors('parent_id');
        $this->delete('/ablaeufe/einstellungen/orte/sek')->assertRedirect();
        $this->get('/ablaeufe/einstellungen?liste=orte')->assertInertia(fn (Assert $page) => $page->where('anzahl.orte', 2));

        // Reihenfolge
        $this->post('/ablaeufe/einstellungen/mitwirkung/reihenfolge', ['ids' => ['i', 'v', 'e', 'm']])->assertRedirect();
        $this->get('/ablaeufe/einstellungen?liste=mitwirkung')->assertInertia(fn (Assert $page) => $page->where('eintraege.0.kuerzel', 'I'));
    }

    public function test_settings_need_right(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/ablaeufe/einstellungen')->assertForbidden();
    }
}
