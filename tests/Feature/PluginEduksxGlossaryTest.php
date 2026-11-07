<?php

namespace Tests\Feature;

use App\Data\Contracts\GlossaryRepository;
use App\Plugins\PluginManager;
use App\Support\Glossary;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Plugins\EduksxGlossary\Plugin;
use Tests\TestCase;

/**
 * Sync mit dem EduKSX-Glossary (#156). Antworten des Glossary sind erfunden und bilden die API v1 nach.
 */
class PluginEduksxGlossaryTest extends TestCase
{
    private const NEW_ID = '01900000-0000-7000-8000-000000000001';

    private const OLD_ID = '01900000-0000-7000-8000-000000000002';

    /** @var array<string, mixed> */
    private array $api = [];

    private function repo(): GlossaryRepository
    {
        return app(GlossaryRepository::class);
    }

    private function vsaId(): string
    {
        return (string) collect($this->repo()->all())->firstWhere('abk_offiziell', 'VSA')['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function term(string $id, string $abk, string $name, array $extra = []): array
    {
        return $extra + [
            'id' => $id, 'slug' => strtolower($abk), 'abbreviation' => $abk, 'name' => $name,
            'official_abbreviation' => $abk, 'official_name' => $name, 'official_aliases' => [], 'alternative_aliases' => [],
            'aliases' => [], 'display_label' => $abk, 'is_full_term' => false, 'short_description' => null, 'description' => null,
            'url' => null, 'categories' => [['key' => 'beh', 'label' => 'BEH', 'name' => 'Behörden', 'url' => null]],
            'sources' => [['key' => 'vsa', 'label' => 'VSA', 'name' => 'Volksschulamt', 'url' => null]], 'status' => 'aktuell',
            'is_active' => true, 'valid_from' => null, 'valid_until' => null, 'revision' => 1,
            'published_at' => '2026-11-07T10:00:00Z', 'web_url' => 'https://glossary.eduksx.ch/begriffe/'.strtolower($abk),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $categories = [['key' => 'beh', 'label' => 'BEH', 'name' => 'Behörden', 'url' => null], ['key' => 'neue-kat', 'label' => 'NK', 'name' => 'Neue Kategorie', 'url' => null]];
        $sources = [['key' => 'vsa', 'label' => 'VSA', 'name' => 'Volksschulamt', 'url' => 'https://www.zh.ch/vsa']];
        $this->api = [
            'cursor' => 10,
            'categories' => $categories,
            'sources' => $sources,
            'terms' => [
                $this->term($this->vsaId(), 'VSA', 'Volksschulamt Kanton Zürich', ['alternative_aliases' => ['Volksschulamt'], 'revision' => 3]),
                $this->term(self::NEW_ID, 'NEUX', 'Neuer Begriff', ['categories' => [['key' => 'neue-kat', 'label' => 'NK', 'name' => 'Neue Kategorie', 'url' => null]]]),
                $this->term(self::OLD_ID, 'ALTX', 'Alter Begriff'),
            ],
            'pages' => [],
            'fail' => false,
        ];
        Http::fake(function (Request $request) {
            if ($this->api['fail']) {
                return Http::response(['message' => 'Wartung'], 500);
            }
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return match ($path) {
                '/api/v1/exports' => Http::response(['release' => 1, 'cursor' => 10, 'license' => ['name' => 'CC BY 4.0'], 'categories' => $this->api['categories'], 'sources' => $this->api['sources'], 'terms' => $this->api['terms']]),
                '/api/v1/releases/latest' => Http::response(['data' => ['release' => 2, 'cursor' => $this->api['cursor'], 'term_count' => 3]]),
                '/api/v1/categories' => Http::response(['data' => $this->api['categories']]),
                '/api/v1/sources' => Http::response(['data' => $this->api['sources']]),
                '/api/v1/changes' => Http::response($this->api['pages'][(string) ($query['since'] ?? '0')]
                    ?? ['data' => [], 'meta' => ['since' => (int) ($query['since'] ?? 0), 'next_cursor' => (int) ($query['since'] ?? 0), 'has_more' => false, 'release' => 2]]),
                default => Http::response([], 404),
            };
        });
        $manager = app(PluginManager::class);
        $manager->saveSettings('eduksx-glossary', ['_allow_write' => false]);
        $manager->setEnabled('eduksx-glossary', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function sync(bool $fresh = false): array
    {
        return app(PluginManager::class)->runAction('eduksx-glossary', 'sync', ['neu' => $fresh], null);
    }

    public function test_first_sync_with_same_ids_keeps_local_data(): void
    {
        $vsa = $this->vsaId();
        $this->repo()->update($vsa, ['bez_eigen' => 'Unser VSA', 'genutzt_in' => ['bereich:personal'], 'notiz' => 'lokal']);
        $count = count($this->repo()->all());

        $result = $this->sync();
        $this->assertSame('success', $result['status'], (string) $result['message']);
        $this->assertStringContainsString('Vollstand', (string) $result['message']);

        $entry = $this->repo()->find($vsa);
        $this->assertSame('Volksschulamt Kanton Zürich', $entry['bez_offiziell']);
        $this->assertSame(['Volksschulamt'], $entry['aliase_alternativ']);
        $this->assertSame('veroeffentlicht', $entry['glossary']['zustand']);
        $this->assertSame(3, $entry['glossary']['revision']);
        $this->assertSame('Unser VSA', $entry['bez_eigen'], 'eigene Form bleibt');
        $this->assertSame(['bereich:personal'], $entry['genutzt_in'], '«Intern genutzt in» bleibt');
        $this->assertSame('lokal', $entry['notiz']);

        $new = $this->repo()->find(self::NEW_ID);
        $this->assertSame('NEUX', $new['abk_offiziell']);
        $this->assertSame([$this->repo()->listId('kategorien', 'neue-kat')], $new['kategorien']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $new['kategorien'][0], 'neue Kategorie mit UUID');
        $this->assertSame('Neue Kategorie', collect($this->repo()->listItems('kategorien'))->firstWhere('schluessel', 'neue-kat')['bezeichnung']);
        $this->assertSame('https://www.zh.ch/vsa', collect($this->repo()->listItems('herkunft'))->firstWhere('schluessel', 'vsa')['url']);
        $this->assertCount($count + 2, $this->repo()->all(), 'lokale Einträge bleiben, nichts gelöscht');
        $this->assertSame(10, $this->repo()->syncState()['cursor']);
    }

    public function test_second_sync_without_changes_changes_nothing(): void
    {
        $this->sync();
        $before = $this->repo()->all();
        $result = $this->sync();
        $this->assertStringContainsString('keine Änderungen', (string) $result['message']);
        $this->assertEquals($before, $this->repo()->all());

        // Vollstand nochmals: gleicher Stand, nichts geändert
        $this->sync(true);
        $this->assertEquals($before, $this->repo()->all());
    }

    public function test_change_feed_published_unpublished_merged_and_paging(): void
    {
        $vsa = $this->vsaId();
        $this->sync();
        $this->repo()->update(self::OLD_ID, ['genutzt_in' => ['bereich:schule'], 'bez_eigen' => 'Alt intern']);
        $this->api['cursor'] = 13;
        $this->api['pages'] = [
            '10' => ['data' => [
                ['cursor' => 11, 'action' => 'published', 'term_id' => $vsa, 'revision' => 4, 'merged_into' => null,
                    'term' => $this->term($vsa, 'VSA', 'Volksschulamt', ['revision' => 4, 'short_description' => 'Amt des Kantons'])],
                ['cursor' => 12, 'action' => 'zukunft', 'term_id' => $vsa, 'revision' => null, 'merged_into' => null, 'term' => null],
            ], 'meta' => ['since' => 10, 'next_cursor' => 12, 'has_more' => true, 'release' => 3]],
            '12' => ['data' => [
                ['cursor' => 13, 'action' => 'merged', 'term_id' => self::OLD_ID, 'revision' => null, 'merged_into' => $vsa, 'term' => null],
                ['cursor' => 14, 'action' => 'unpublished', 'term_id' => self::NEW_ID, 'revision' => null, 'merged_into' => null, 'term' => null],
            ], 'meta' => ['since' => 12, 'next_cursor' => 14, 'has_more' => false, 'release' => 3]],
        ];

        $result = $this->sync();
        $this->assertSame('success', $result['status'], (string) $result['message']);
        $entry = $this->repo()->find($vsa);
        $this->assertSame('Amt des Kantons', $entry['kurzbeschreibung']);
        $this->assertSame(4, $entry['glossary']['revision']);
        $this->assertContains('bereich:schule', $entry['genutzt_in'], 'lokale Daten des zusammengeführten Begriffs übernommen');
        $this->assertContains(self::OLD_ID, $entry['fruehere_ids']);

        $old = $this->repo()->find(self::OLD_ID);
        $this->assertFalse($old['aktiv'], 'ausgeblendet, nicht gelöscht');
        $this->assertSame('zusammengefuehrt', $old['glossary']['zustand']);
        $this->assertFalse($this->repo()->find(self::NEW_ID)['aktiv']);
        $this->assertSame('zurueckgezogen', $this->repo()->find(self::NEW_ID)['glossary']['zustand']);
        $this->assertSame(14, $this->repo()->syncState()['cursor']);
        $this->assertSame(3, $this->repo()->syncState()['release']);

        // gleiche Ereignisse nochmals: nichts ändert sich
        $before = $this->repo()->all();
        $this->repo()->saveSyncState(['cursor' => 10]);
        $this->sync();
        $this->assertEquals($before, $this->repo()->all());
    }

    public function test_offline_keeps_local_state_and_reports_error(): void
    {
        $this->sync();
        $before = $this->repo()->all();
        $this->api['fail'] = true;
        $this->api['cursor'] = 99;

        $result = $this->sync();
        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('HTTP 500', (string) $result['message']);
        $this->assertEquals($before, $this->repo()->all());
        $state = $this->repo()->syncState();
        $this->assertSame(10, $state['cursor']);
        $this->assertStringContainsString('HTTP 500', (string) $state['fehler']);

        $this->artisan('intranet:glossar-sync', ['--jetzt' => true])->assertFailed();
    }

    public function test_command_respects_plugin_and_interval(): void
    {
        $this->artisan('intranet:glossar-sync')->assertSuccessful();
        $this->assertSame(10, $this->repo()->syncState()['cursor']);
        $this->api['cursor'] = 20;
        // Abstand nicht erreicht: kein Abruf
        $this->artisan('intranet:glossar-sync')->assertSuccessful();
        Http::assertSentCount(2);

        app(PluginManager::class)->setEnabled('eduksx-glossary', false);
        $this->artisan('intranet:glossar-sync', ['--jetzt' => true])->expectsOutputToContain('nicht eingeschaltet')->assertSuccessful();
    }

    public function test_shared_fields_locked_local_fields_editable_and_source_shown(): void
    {
        $this->sync();
        $vsa = $this->vsaId();
        $this->signInAs('verwaltung');
        $this->postJson('/glossar/zellen', ['changes' => [['id' => $vsa, 'field' => 'bez_offiziell', 'value' => 'Anders']]])->assertStatus(422);
        $this->postJson('/glossar/zellen', ['changes' => [['id' => $vsa, 'field' => 'aktiv', 'value' => 'nein']]])->assertStatus(422);
        $this->postJson('/glossar/zellen', ['changes' => [['id' => $vsa, 'field' => 'bez_eigen', 'value' => 'Unser Amt']]])->assertOk();
        $this->assertSame('Unser Amt', $this->repo()->find($vsa)['bez_eigen']);
        $this->assertSame('Volksschulamt Kanton Zürich', $this->repo()->find($vsa)['bez_offiziell']);

        $this->post('/glossar/doppelte/zusammenfuehren', ['ids' => [$vsa, self::NEW_ID], 'ziel' => $vsa])->assertSessionHasErrors('ids');

        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page
            ->where('quelle', true)
            ->where('rows', fn ($rows): bool => collect($rows)->firstWhere('id', $vsa)['sync'] === true));
        $this->get('/glossar/'.$vsa)->assertInertia(fn (Assert $page) => $page->where('eintrag.glossary.zustand', 'veroeffentlicht'));
    }

    public function test_ambiguity_from_shared_values_and_alternative_spellings(): void
    {
        $vsa = $this->vsaId();
        // zweite Bedeutung im Glossary, gleiche Bezeichnung mit anderem Leerraum zählt einmal
        $this->api['terms'][1] = $this->term(self::NEW_ID, 'VSA', 'Verband  Schweizer Archive');
        $this->api['terms'][2] = $this->term(self::OLD_ID, 'vsa', 'verband schweizer archive');
        $this->sync();
        // lokale eigene Abkürzung «VSA» eines nicht übernommenen Eintrags zählt für übernommene nicht
        $local = collect($this->repo()->all())->first(fn (array $e): bool => ! isset($e['glossary']));
        $this->repo()->update((string) $local['id'], ['abk_eigen' => 'VSA', 'bez_eigen' => 'Ganz anderes']);

        $this->signInAs('verwaltung');
        $this->get('/glossar')->assertInertia(fn (Assert $page) => $page->where('rows', function ($rows) use ($vsa): bool {
            $row = collect($rows)->firstWhere('id', $vsa);
            $this->assertSame(2, $row['bedeutungen']);
            $this->assertSame('Volksschulamt', $row['aliase_alternativ']);

            return true;
        }));
        $term = collect(app(Glossary::class)->terms())->firstWhere('id', $vsa);
        $this->assertContains('Volksschulamt', $term['match'], 'alternative Schreibweise im Freitext erkannt');
    }

    public function test_status_text_and_connection_test(): void
    {
        $this->assertSame('Noch nicht synchronisiert.', Plugin::statusText([]));
        $this->sync();
        $this->assertStringContainsString('Cursor 10', Plugin::statusText($this->repo()->syncState()));
        $test = app(PluginManager::class)->test('eduksx-glossary', null);
        $this->assertTrue($test['ok'], (string) $test['message']);
    }
}
