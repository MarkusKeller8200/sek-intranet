<?php

namespace Modules\Beschaffung\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Beschaffung\Data\Contracts\ArtikelRepository;
use Modules\Beschaffung\Kataloge;
use Modules\Beschaffung\Startinhalt;
use Tests\TestCase;

/**
 * Beschaffung (#197, #198): Kataloge unter «Bestellen» mit gleichem Aufbau, Startinhalt aus dem ICT-Wiki,
 * Bearbeiten in der Tabelle, Bilder, Rechte.
 */
class BeschaffungTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    private function ersterEintrag(string $katalog): array
    {
        return app(ArtikelRepository::class)->katalog($katalog)[0];
    }

    public function test_menu_lists_catalogs_under_order(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/start')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('navigation', function ($groups): bool {
                $items = collect(collect($groups)->firstWhere('key', 'bestellen')['items'] ?? []);
                $hardware = $items->firstWhere('key', 'beschaffung-hardware');

                return $items->pluck('title')->take(4)->all() === ['Hardware', 'Plattformen', 'Software – Apps', 'Material – PICTS / TICTS']
                    && collect($hardware['children'])->pluck('href')->first() === '/beschaffung/zimmer-ausruestung'
                    && $items->contains('key', 'beschaffung-lehrmittel');
            }));

        // Lehrmittel bleibt ein Platzhalter mit den Kästen «Geplante Funktionen» und «So geht es weiter»
        $this->get('/module/beschaffung-lehrmittel')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('modules/show')
            ->where('module.title', 'Lehrmittel')
            ->has('module.features', 3));
    }

    public function test_every_catalog_has_seeded_entries_with_the_same_fields(): void
    {
        $this->signInAs('lehrperson');

        foreach (Kataloge::schluessel() as $katalog) {
            $this->get("/beschaffung/{$katalog}")->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Beschaffung::katalog')
                ->where('katalog.key', $katalog)
                ->where('darfBearbeiten', false)
                ->where('katalog.quellen', fn ($q): bool => count($q) > 0 && str_starts_with($q[0]['url'], 'https://sekbuelach.atlassian.net/wiki/'))
                ->where('zeilen', fn ($zeilen): bool => count($zeilen) > 0
                    && collect($zeilen)->every(fn ($z): bool => array_key_exists('preis', $z) && array_key_exists('bild', $z) && $z['bezeichnung'] !== '')));
        }

        $this->get('/beschaffung')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Beschaffung::index')
            ->has('kataloge', 12));
        $this->get('/beschaffung/gibt-es-nicht')->assertNotFound();
    }

    public function test_seed_follows_the_decisions(): void
    {
        $repo = app(ArtikelRepository::class);

        $hausdienst = collect($repo->katalog('ausruestung-hausdienst'));
        $this->assertFalse($hausdienst->contains('modell', 'MacBook Air M4 (2025)'), 'Hausdienst nur noch mit Lenovo');
        $this->assertTrue($hausdienst->contains('modell', 'Lenovo Yoga 7 2-in-1'));

        $admin = collect($repo->katalog('plattformen-administration'));
        $nummern = $admin->pluck('bestellnummer')->filter();
        $this->assertSame($nummern->count(), $nummern->unique()->count(), 'Bestellnummern eindeutig');
        $this->assertTrue($admin->contains('bezeichnung', 'Abraxas FIS'));
        $this->assertTrue($admin->firstWhere('bezeichnung', 'VSA – Puls')['pruefen']);

        $unterricht = collect($repo->katalog('plattformen-unterricht'));
        $this->assertSame('gestrichen', $unterricht->firstWhere('bezeichnung', 'AirServer')['status']);
        $this->assertSame(1200.0, (float) collect($repo->katalog('ausruestung-lehrpersonen'))->firstWhere('bezeichnung', 'MacBook Air')['preis']);

        // Keine Personenangaben im Startinhalt (E-Mail-Adressen, Namen der Zuständigen)
        $json = (string) file_get_contents(Startinhalt::verzeichnis().'/artikel.json');
        $this->assertDoesNotMatchRegularExpression('/[a-z0-9._-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $json);
        $this->assertCount(count(Kataloge::ALLE), array_unique(array_column(Startinhalt::artikel(), 'katalog')));
    }

    public function test_ict_edits_cells_all_or_nothing(): void
    {
        $this->signInAs('admin');
        $eintrag = $this->ersterEintrag('ausruestung-lehrpersonen');

        $this->postJson('/beschaffung/ausruestung-lehrpersonen/zellen', ['changes' => [
            ['id' => $eintrag['id'], 'field' => 'preis', 'value' => "1'250,50"],
            ['id' => $eintrag['id'], 'field' => 'url', 'value' => 'keine-adresse'],
        ]])->assertStatus(422);
        $this->assertSame(1200.0, (float) app(ArtikelRepository::class)->find($eintrag['id'])['preis'], 'nichts gespeichert');

        $this->postJson('/beschaffung/ausruestung-lehrpersonen/zellen', ['changes' => [
            ['id' => $eintrag['id'], 'field' => 'preis', 'value' => "1'250,50"],
            ['id' => $eintrag['id'], 'field' => 'pruefen', 'value' => true],
            ['id' => $eintrag['id'], 'field' => 'kosten', 'value' => 'hoch'],
        ]])->assertOk()->assertJsonPath('rows.0.preis', '1250.50')->assertJsonPath('rows.0.pruefen', true);

        // Eintrag eines anderen Katalogs über diesen Katalog: nicht gefunden
        $fremd = $this->ersterEintrag('mobile-apps');
        $this->postJson('/beschaffung/ausruestung-lehrpersonen/zellen', ['changes' => [
            ['id' => $fremd['id'], 'field' => 'bezeichnung', 'value' => 'X'],
        ]])->assertStatus(422);
    }

    public function test_add_and_delete_rows(): void
    {
        $this->signInAs('admin');
        $vorher = count(app(ArtikelRepository::class)->katalog('material-picts-ticts'));

        $id = $this->postJson('/beschaffung/material-picts-ticts/zeile')->assertOk()->json('id');
        $neu = app(ArtikelRepository::class)->find($id);
        $this->assertSame('material-picts-ticts', $neu['katalog']);
        $this->assertTrue($neu['pruefen']);
        $this->assertSame($vorher + 1, $neu['position']);

        $this->post('/beschaffung/material-picts-ticts/loeschen', ['ids' => [$id]])->assertRedirect();
        $this->assertNull(app(ArtikelRepository::class)->find($id));
        $this->assertCount($vorher, app(ArtikelRepository::class)->katalog('material-picts-ticts'));
    }

    public function test_upload_show_and_remove_image(): void
    {
        Storage::fake('local');
        $this->signInAs('admin');
        $eintrag = $this->ersterEintrag('zimmer-ausruestung');

        $this->post("/beschaffung/bild/{$eintrag['id']}", ['file_bild' => UploadedFile::fake()->create('notiz.txt', 1, 'text/plain')])
            ->assertSessionHasErrors('file_bild');

        $row = $this->post("/beschaffung/bild/{$eintrag['id']}", ['file_bild' => UploadedFile::fake()->image('beamer.png', 64, 64)])
            ->assertOk()->json('row');
        $this->assertStringStartsWith("/beschaffung/bild/{$eintrag['id']}?v=", $row['bild']);
        Storage::disk('local')->assertExists("beschaffung/bilder/{$eintrag['id']}.png");
        $this->get("/beschaffung/bild/{$eintrag['id']}")->assertOk();

        $this->postJson("/beschaffung/bild/{$eintrag['id']}/entfernen")->assertOk()->assertJsonPath('row.bild', '');
        Storage::disk('local')->assertMissing("beschaffung/bilder/{$eintrag['id']}.png");
        $this->get("/beschaffung/bild/{$eintrag['id']}")->assertNotFound();
    }

    public function test_rights(): void
    {
        $this->signInAs('lehrperson');
        $eintrag = $this->ersterEintrag('mobile-apps');
        $this->postJson('/beschaffung/mobile-apps/zellen', ['changes' => [['id' => $eintrag['id'], 'field' => 'bezeichnung', 'value' => 'X']]])->assertForbidden();
        $this->postJson('/beschaffung/mobile-apps/zeile')->assertForbidden();
        $this->post("/beschaffung/bild/{$eintrag['id']}/entfernen")->assertForbidden();

        $this->signInAs('redaktion');
        $this->get('/beschaffung/mobile-apps')->assertForbidden();
    }
}
