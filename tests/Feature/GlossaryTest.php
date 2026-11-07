<?php

namespace Tests\Feature;

use App\Data\Contracts\GlossaryRepository;
use App\Data\Store\JsonStore;
use App\Support\Glossary;
use App\Support\GlossaryExamples;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Glossar (#53–#62): Startinhalt, Tabelle, Sperre der offiziellen Felder, Listen, Excel, Doppelte.
 */
class GlossaryTest extends TestCase
{
    private function repo(): GlossaryRepository
    {
        return app(GlossaryRepository::class);
    }

    /** ID eines Listeneintrags des Startinhalts (#159: UUID, Schlüssel im Feld «schluessel»). */
    private function id(string $list, string $key): string
    {
        return (string) $this->repo()->listId($list, $key);
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $abk, ?string $kategorie = null): array
    {
        return collect($this->repo()->all())->first(fn (array $e): bool => $e['abk_offiziell'] === $abk && ($kategorie === null || in_array($kategorie, $e['kategorien'], true)));
    }

    public function test_seed_without_merging_and_with_origin_vsa(): void
    {
        $all = $this->repo()->all();
        $this->assertCount(141, $all);
        $this->assertCount(10, $this->repo()->listItems('kategorien'));
        $this->assertSame(3, collect($all)->where('abk_offiziell', 'SHP')->count(), 'Doppelte nicht zusammengeführt');
        $this->assertTrue(collect($all)->every(fn (array $e): bool => $e['herkunft'] === [$this->id('herkunft', 'vsa')]));
        $this->assertSame('zu_pruefen', $this->entry('SV')['status']);
        $this->assertSame(['biz'], $this->entry('BIZ')['aliase_offiziell']);
    }

    public function test_table_for_all_staff_edit_only_with_right(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/glossar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('glossary/index')
            ->has('rows', 141)
            ->where('rechte.edit', false)
            ->where('rows', fn ($rows): bool => collect($rows)->firstWhere('anzeige', 'SHP')['mehrdeutig'] === true));
        $this->post('/glossar/zellen', ['changes' => [['id' => $this->entry('VSA')['id'], 'field' => 'bez_eigen', 'value' => 'x']]])->assertForbidden();

        $this->signInAs('verwaltung');
        $vsa = $this->entry('VSA');
        $this->postJson('/glossar/zellen', ['changes' => [
            ['id' => $vsa['id'], 'field' => 'bez_eigen', 'value' => 'Volksschulamt Zürich'],
            ['id' => $vsa['id'], 'field' => 'kategorien', 'value' => 'BEH; Recht'],
            ['id' => $vsa['id'], 'field' => 'status', 'value' => 'aktuell'],
        ]])->assertOk()->assertJsonPath('rows.0.kategorien', 'Behörden; Recht');
        $this->assertSame([$this->id('kategorien', 'beh'), $this->id('kategorien', 'rec')], $this->repo()->find($vsa['id'])['kategorien']);

        // offizielle Felder gesperrt, unbekannte Herkunft abgewiesen, alles oder nichts
        $this->postJson('/glossar/zellen', ['changes' => [
            ['id' => $vsa['id'], 'field' => 'bez_eigen', 'value' => 'geändert'],
            ['id' => $vsa['id'], 'field' => 'bez_offiziell', 'value' => 'Anders'],
        ]])->assertStatus(422);
        $this->postJson('/glossar/zellen', ['changes' => [['id' => $vsa['id'], 'field' => 'herkunft', 'value' => 'Mars']]])->assertStatus(422);
        $this->assertSame('Volksschulamt Zürich', $this->repo()->find($vsa['id'])['bez_eigen']);

        $this->signInAs('admin');
        $this->postJson('/glossar/zellen', ['changes' => [['id' => $vsa['id'], 'field' => 'bez_offiziell', 'value' => 'Volksschulamt']]])->assertOk();
    }

    public function test_multiple_meanings_only_with_different_designation(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page
            ->where('kategorienNamen.Recht', 'Recht')
            ->where('kategorienNamen.Behörden', 'Behörden & Schulorganisation')
            ->where('rows', function ($rows): bool {
                $by = collect($rows)->groupBy('anzeige');

                return $by['SHP']->every(fn ($r): bool => $r['bedeutungen'] === 3 && $r['mehrdeutig'] === true)
                    && $by['LP']->every(fn ($r): bool => $r['bedeutungen'] === 1 && $r['mehrdeutig'] === false)
                    && $by['VSA'][0]['kategorien'] === 'Behörden';
            }));
    }

    public function test_examples_fill_only_empty_fields_once(): void
    {
        $vsa = $this->entry('VSA');
        $this->repo()->update($vsa['id'], ['kurzbeschreibung' => 'Eigene']);
        $changed = app(GlossaryExamples::class)->apply();
        $this->assertGreaterThan(20, $changed);
        $this->assertSame('Eigene', $this->repo()->find($vsa['id'])['kurzbeschreibung']);
        $this->assertSame([$this->id('herkunft', 'vsa'), $this->id('herkunft', 'bund')], $this->entry('BFS')['herkunft']);
        $this->assertSame([$this->id('kategorien', 'dsi'), $this->id('kategorien', 'unt')], $this->entry('BYOD')['kategorien']);
        $this->assertSame(0, app(GlossaryExamples::class)->apply(), 'nur einmal');
    }

    public function test_term_without_abbreviation_shows_designation(): void
    {
        $this->signInAs('verwaltung');
        $this->post('/glossar', ['bez_offiziell' => 'Personendaten', 'kategorien' => ['dsi'], 'herkunft' => ['dsb'], 'status' => 'aktuell'])->assertRedirect();
        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page->where('rows', function ($rows): bool {
            $names = collect($rows)->pluck('anzeige')->all();
            $row = collect($rows)->firstWhere('anzeige', 'Personendaten');

            return $row !== null && $row['begriff'] === true && $row['mehrdeutig'] === false
                && array_search('Personendaten', $names, true) > array_search('LP', $names, true)
                && array_search('Personendaten', $names, true) < array_search('PHZH', $names, true)
                && collect($rows)->firstWhere('anzeige', 'VSA')['begriff'] === false;
        }));
    }

    public function test_delete_selection_with_right_only(): void
    {
        $ids = collect($this->repo()->all())->where('abk_offiziell', 'SHP')->pluck('id')->values()->all();
        $this->signInAs('lehrperson');
        $this->post('/glossar/auswahl-loeschen', ['ids' => $ids])->assertForbidden();

        $this->signInAs('verwaltung');
        $this->post('/glossar/auswahl-loeschen', ['ids' => [...$ids, 'gibt-es-nicht']])->assertRedirect('/glossar');
        $this->assertSame(0, collect($this->repo()->all())->where('abk_offiziell', 'SHP')->count());
        $this->assertCount(138, $this->repo()->all());
        $this->post('/glossar/auswahl-loeschen', ['ids' => []])->assertSessionHasErrors('ids');
    }

    public function test_new_row_and_single_record(): void
    {
        $this->signInAs('schulleitung');
        $id = $this->postJson('/glossar/zeile')->assertOk()->json('id');
        $this->assertNotNull($this->repo()->find($id));

        $this->post('/glossar', ['abk_offiziell' => 'ZLV', 'bez_offiziell' => 'Zürcher Lehrerinnen- und Lehrerverband', 'kategorien' => ['ver'], 'herkunft' => ['vsa'], 'notiz' => '**wichtig**', 'status' => 'aktuell', 'url' => 'nicht-url'])->assertSessionHasErrors('url');
        $this->post('/glossar', ['abk_eigen' => 'TEST', 'bez_eigen' => 'Testbegriff', 'kategorien' => ['ver'], 'herkunft' => ['schule'], 'kurzbeschreibung' => 'Kurz', 'status' => 'aktuell'])->assertRedirect();
        $neu = collect($this->repo()->all())->firstWhere('abk_eigen', 'TEST');
        $this->get('/glossar/'.$neu['id'])->assertOk()->assertInertia(fn (Assert $page) => $page->component('glossary/show')->where('eintrag.kurzbeschreibung', 'Kurz'));
    }

    public function test_list_ids_are_uuids_and_old_keys_are_migrated(): void
    {
        $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
        foreach (['kategorien', 'herkunft'] as $list) {
            foreach ($this->repo()->listItems($list) as $item) {
                $this->assertMatchesRegularExpression($uuid, (string) $item['id'], 'Startinhalt mit UUID');
            }
        }

        // früherer Stand: Kurz-IDs ohne Label/Farbe, Einträge verweisen darauf
        $store = app(JsonStore::class);
        $store->replace('glossar_kategorien', [['id' => 'beh', 'kuerzel' => 'BEH', 'bezeichnung' => 'Behörden', 'position' => 1]]);
        $store->replace('glossar_herkunft', [['id' => 'vsa', 'kuerzel' => 'VSA', 'bezeichnung' => 'Volksschulamt', 'position' => 1]]);
        $entry = $this->entry('VSA');
        $store->replace('glossar', [['kategorien' => ['beh'], 'herkunft' => ['vsa']] + $entry]);
        $before = $store->find('glossar', $entry['id']);
        $this->repo()->saveSyncState(['listen' => ['herkunft' => ['vsa' => 'vsa']]]);

        $this->assertSame(2, $this->repo()->migrateListIds());
        $beh = $this->repo()->listItems('kategorien')[0];
        $this->assertMatchesRegularExpression($uuid, (string) $beh['id']);
        $this->assertSame('beh', $beh['schluessel']);
        $this->assertSame('Behörden', $beh['label'], 'Label des Startinhalts fest gespeichert');
        $this->assertSame('violet', $beh['farbe'], 'Farbe des Startinhalts fest gespeichert');
        $after = $store->find('glossar', $entry['id']);
        $this->assertSame([$beh['id']], $after['kategorien']);
        $this->assertSame([$this->id('herkunft', 'vsa')], $after['herkunft']);
        $this->assertSame($before['updated_at'], $after['updated_at'], 'Zeitstempel bleiben');
        $this->assertSame(['vsa' => $this->id('herkunft', 'vsa')], $this->repo()->syncState()['listen']['herkunft']);
        $this->assertSame(0, $this->repo()->migrateListIds(), 'idempotent');
    }

    public function test_lists_for_categories_and_origin(): void
    {
        $this->signInAs('verwaltung');
        $this->post('/glossar/listen/herkunft', ['kuerzel' => 'GEMA', 'bezeichnung' => 'Gemeindeamt Kanton', 'url' => 'https://example.org'])->assertRedirect();
        $this->post('/glossar/listen/herkunft', ['kuerzel' => 'gema', 'bezeichnung' => 'doppelt'])->assertSessionHasErrors('kuerzel');
        $this->delete('/glossar/listen/herkunft/'.$this->id('herkunft', 'vsa'))->assertRedirect();
        $this->assertNotNull(collect($this->repo()->listItems('herkunft'))->firstWhere('schluessel', 'vsa'), 'verwendet → nicht gelöscht');
        $this->get('/glossar/listen?liste=herkunft')->assertInertia(fn (Assert $page) => $page->where('liste', 'herkunft')->where('eintraege.0.verwendet', 141)->where('eintraege.0.label', 'VSA'));
    }

    public function test_tag_colors_for_table_lists_and_color_concept(): void
    {
        $this->signInAs('admin');
        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page
            ->where('tagFarben.kategorien.Recht', 'rose')
            ->where('tagFarben.herkunft.VSA', 'brand'));
        $this->put('/glossar/listen/kategorien/rec', ['kuerzel' => 'REC', 'label' => 'Recht', 'bezeichnung' => 'Recht', 'farbe' => 'pink'])->assertSessionHasErrors('farbe');
        $this->put('/glossar/listen/kategorien/rec', ['kuerzel' => 'REC', 'label' => 'Recht', 'bezeichnung' => 'Recht', 'farbe' => 'teal'])->assertRedirect();
        $this->post('/glossar/listen/kategorien', ['kuerzel' => 'FIN', 'label' => 'Finanzen', 'bezeichnung' => 'Finanzen & Rechnungslegung'])->assertRedirect();
        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page
            ->where('tagFarben.kategorien.Recht', 'teal')
            ->where('tagFarben.kategorien.Finanzen', fn ($c): bool => in_array($c, Glossary::COLORS, true)));
        $this->get('/farbkonzept')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('glossarTags.kategorien', fn ($t): bool => collect($t)->firstWhere('label', 'Recht')['farbe'] === 'teal')
            ->has('glossarTags.herkunft', 8));
    }

    public function test_origin_label_as_tag_in_table_and_for_input(): void
    {
        $this->signInAs('verwaltung');
        $this->put('/glossar/listen/herkunft/kanton', ['kuerzel' => 'KT', 'label' => 'ZH', 'bezeichnung' => 'Kanton Zürich'])->assertRedirect();
        $vsa = $this->entry('VSA');
        $this->postJson('/glossar/zellen', ['changes' => [['id' => $vsa['id'], 'field' => 'herkunft', 'value' => 'VSA; ZH']]])
            ->assertOk()->assertJsonPath('rows.0.herkunft', 'VSA; ZH');
        $this->assertSame([$this->id('herkunft', 'vsa'), $this->id('herkunft', 'kanton')], $this->repo()->find($vsa['id'])['herkunft']);
        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page
            ->where('rows', fn ($rows): bool => collect($rows)->firstWhere('id', $vsa['id'])['herkunft'] === 'VSA; ZH'));
    }

    public function test_duplicates_merge_and_keep_apart(): void
    {
        $this->signInAs('verwaltung');
        $this->get('/glossar/doppelte')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('gruppen', fn ($g): bool => collect($g)->pluck('key')->contains('abk:shp')));

        $shp = collect($this->repo()->all())->where('abk_offiziell', 'SHP')->values();
        $ziel = $shp[0]['id'];
        $this->post('/glossar/doppelte/zusammenfuehren', ['ids' => $shp->pluck('id')->all(), 'ziel' => $ziel, 'auswahl' => ['bez_offiziell' => $shp[1]['id']]])->assertRedirect('/glossar/doppelte');
        $merged = $this->repo()->find($ziel);
        $this->assertSame([$this->id('kategorien', 'beh'), $this->id('kategorien', 'sop'), $this->id('kategorien', 'per')], $merged['kategorien']);
        $this->assertSame($shp[1]['bez_offiziell'], $merged['bez_offiziell']);
        $this->assertCount(2, $merged['fruehere_ids']);
        $this->assertSame(1, collect($this->repo()->all())->where('abk_offiziell', 'SHP')->count());

        $sl = collect($this->repo()->all())->where('abk_offiziell', 'SL')->pluck('id')->all();
        $this->post('/glossar/doppelte/belassen', ['key' => 'abk:sl', 'ids' => $sl])->assertRedirect();
        $keys = collect(app(Glossary::class)->duplicates())->pluck('key');
        $this->assertNotContains('abk:sl', $keys);
        $this->assertNotContains('abk:shp', $keys);
    }

    public function test_excel_round_trip_with_preview(): void
    {
        $this->signInAs('verwaltung');
        $xlsx = $this->post('/glossar/export', ['ids' => [$this->entry('VSA')['id']]])->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('Content-Type'));

        $vsa = $this->entry('VSA');
        $header = ['ID', ...array_values(Glossary::FIELDS)];
        $row = fn (array $v): array => array_map(fn (string $h): string => $v[$h] ?? '', $header);
        $content = SpreadsheetWriter::xlsx($header, [
            $row(['ID' => $vsa['id'], 'Kategorien' => 'BEH', 'Abkürzung offiziell' => 'VSA', 'Bezeichnung offiziell' => 'Volksschulamt', 'Bezeichnung eigene' => 'Unser VSA', 'Herkunft' => 'VSA', 'Status' => 'aktuell']),
            $row(['Kategorien' => 'Verbände', 'Abkürzung eigene' => 'NEU1', 'Bezeichnung eigene' => 'Neuer Begriff', 'Herkunft' => 'Schule intern', 'Status' => 'aktuell']),
            $row(['Kategorien' => 'Verbände', 'Abkürzung eigene' => 'NEU2', 'Herkunft' => 'Mars', 'Status' => 'aktuell']),
            $row(['ID' => 'gibt-es-nicht', 'Abkürzung eigene' => 'X']),
        ], 'Glossar');
        $path = tempnam(sys_get_temp_dir(), 'gl').'.xlsx';
        file_put_contents($path, $content);

        $response = $this->post('/glossar/import', ['datei' => new UploadedFile($path, 'glossar.xlsx', null, null, true)])->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('glossary/import')
            ->where('plan', fn ($plan): bool => collect($plan)->pluck('art')->sort()->values()->all() === ['fehler', 'fehler', 'geaendert', 'neu']));
        $token = $response->viewData('page')['props']['token'];
        $this->assertSame('', $this->repo()->find($vsa['id'])['bez_eigen'], 'Vorschau speichert nichts');

        $this->post('/glossar/import/'.$token)->assertRedirect('/glossar');
        $this->assertSame('Unser VSA', $this->repo()->find($vsa['id'])['bez_eigen']);
        $this->assertNotNull(collect($this->repo()->all())->firstWhere('abk_eigen', 'NEU1'));
        $this->assertNull(collect($this->repo()->all())->firstWhere('abk_eigen', 'NEU2'));
        $this->assertCount(142, $this->repo()->all(), 'nie gelöscht');
        $this->post('/glossar/import/'.$token)->assertNotFound();
    }

    public function test_terms_for_component_and_free_text(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page->where('auth.can.viewGlossary', true));

        $response = $this->getJson('/glossar/begriffe')->assertOk();
        $vsa = collect($response->json('terms'))->firstWhere('abk', 'VSA');
        $this->assertNotNull($vsa);
        $this->assertContains('VSA', $vsa['match']);
        $this->assertSame('Volksschulamt', $vsa['bez']);
        $this->assertSame(['id', 'abk', 'bez', 'kurz', 'match', 'historisch'], array_keys($vsa));
        $this->assertCount(3, collect($response->json('terms'))->filter(fn (array $t): bool => in_array('SHP', $t['match'], true)));

        // Unverändert: nur «304»; eigene Bezeichnung ändern → neue Daten
        $etag = (string) $response->headers->get('ETag');
        $this->getJson('/glossar/begriffe', ['If-None-Match' => $etag])->assertStatus(304);
        $this->repo()->update($vsa['id'], ['bez_eigen' => 'Volksschulamt Zürich', 'alias_eigen' => 'VS-Amt']);
        $fresh = $this->getJson('/glossar/begriffe', ['If-None-Match' => $etag])->assertOk();
        $vsa = collect($fresh->json('terms'))->firstWhere('id', $vsa['id']);
        $this->assertSame('Volksschulamt Zürich', $vsa['bez']);
        $this->assertContains('VS-Amt', $vsa['match']);
    }

    public function test_used_in_as_links_only_where_visible(): void
    {
        $vsa = $this->entry('VSA');
        $this->repo()->update($vsa['id'], ['genutzt_in' => ['bereich:wissen', 'modul:ablaeufe']]);

        $this->signInAs('lehrperson');
        $this->get('/glossar/'.$vsa['id'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('bereiche.bereich:wissen.href', '/dokumente')
            ->where('bereiche.modul:ablaeufe.href', null)
            ->where('bereiche.modul:ablaeufe.title', 'Modul Abläufe Verwaltung'));

        $this->signInAs('verwaltung');
        $this->get('/glossar/'.$vsa['id'])->assertInertia(fn (Assert $page) => $page
            ->where('bereiche.modul:ablaeufe.href', '/ablaeufe'));
    }

    public function test_menu_and_rights(): void
    {
        $this->signInAs('ict');
        $this->get('/start')->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($groups): bool => str_contains((string) json_encode($groups), '/glossar')));
        $this->get('/glossar/doppelte')->assertForbidden();
        $this->get('/glossar/neu')->assertForbidden();
    }

    public function test_active_flag_hides_entries_for_viewers_and_from_terms(): void
    {
        $vsa = $this->entry('VSA');
        $this->assertTrue(Glossary::active($vsa), 'ohne Feld aktiv');

        $this->signInAs('verwaltung');
        $this->postJson('/glossar/zellen', ['changes' => [['id' => $vsa['id'], 'field' => 'aktiv', 'value' => false]]])
            ->assertOk()->assertJsonPath('rows.0.aktiv', false);
        $this->assertFalse($this->repo()->find($vsa['id'])['aktiv']);
        $this->postJson('/glossar/zellen', ['changes' => [['id' => $vsa['id'], 'field' => 'aktiv', 'value' => 'vielleicht']]])->assertStatus(422);
        // Pflegende sehen inaktive Einträge (abgeblendet)
        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page
            ->has('rows', 141)
            ->where('felder.aktiv', 'Aktiv'));
        $this->get('/glossar/'.$vsa['id'])->assertOk();

        $this->signInAs('lehrperson');
        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page
            ->has('rows', 140)
            ->where('rows', fn ($rows): bool => collect($rows)->every(fn (array $r): bool => $r['aktiv'] === true)));
        $this->get('/glossar/'.$vsa['id'])->assertNotFound();
        $this->assertNull(collect($this->getJson('/glossar/begriffe')->json('terms'))->firstWhere('id', $vsa['id']), 'nicht markiert');

        // Excel: ja/nein
        $this->assertTrue(Glossary::flag('ja'));
        $this->assertTrue(Glossary::flag(''));
        $this->assertFalse(Glossary::flag('nein'));
        $this->assertFalse(Glossary::flag('0'));
        $this->assertNull(Glossary::flag('vielleicht'));
        $this->signInAs('verwaltung');
        $this->put('/glossar/'.$vsa['id'], ['aktiv' => true])->assertRedirect();
        $this->assertTrue($this->repo()->find($vsa['id'])['aktiv']);
    }
}
