<?php

namespace Tests\Feature;

use App\Data\Contracts\GlossaryRepository;
use App\Plugins\PluginManager;
use App\Support\Glossary;
use GuzzleHttp\Promise\PromiseInterface;
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
            'proposals' => [],
            'sent' => [],
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
                '/api/v1/service/whoami' => $request->hasHeader('Authorization', 'Bearer gls_test_erfunden')
                    ? Http::response(['service_account' => ['id' => self::NEW_ID, 'name' => 'Intranet Test', 'scopes' => ['propose']], 'token' => ['name' => 'Test', 'expires_at' => null]])
                    : Http::response([], 401),
                '/api/v1/proposals' => $this->proposalCreated($request),
                default => str_starts_with($path, '/api/v1/proposals/') && isset($this->api['proposals'][substr($path, 18)])
                    ? Http::response(['data' => $this->api['proposals'][substr($path, 18)]])
                    : Http::response([], 404),
            };
        });
        $manager = app(PluginManager::class);
        $manager->saveSettings('eduksx-glossary', ['_allow_write' => false]);
        $manager->setEnabled('eduksx-glossary', true);
    }

    private function proposalCreated(Request $request): PromiseInterface
    {
        if (! $request->hasHeader('Authorization', 'Bearer gls_test_erfunden')) {
            return Http::response([], 401);
        }
        $this->api['sent'][] = $request->data();
        $id = (string) (count($this->api['sent']) + 6);
        $this->api['proposals'][$id] = ['id' => (int) $id, 'kind' => isset($request->data()['term']) ? 'change' : 'new', 'term_id' => null, 'status' => 'eingereicht', 'created_at' => '2026-11-09T10:00:00Z', 'resolved_at' => null];

        return Http::response($this->api['proposals'][$id], 201);
    }

    private function withToken(): void
    {
        app(PluginManager::class)->saveSettings('eduksx-glossary', ['token' => 'gls_test_erfunden', '_allow_write' => true]);
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
        // ohne «Automatisch synchronisieren» kein Abruf im Zeitplan (#161)
        $this->artisan('intranet:glossar-sync')->assertSuccessful();
        Http::assertSentCount(0);
        app(PluginManager::class)->saveSettings('eduksx-glossary', ['auto' => true, '_allow_write' => false]);
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

    public function test_dry_run_shows_changes_and_saves_nothing(): void
    {
        $before = $this->repo()->all();
        $result = app(PluginManager::class)->runAction('eduksx-glossary', 'sync', ['probelauf' => true], null);
        $this->assertSame('success', $result['status'], (string) $result['message']);
        $this->assertStringContainsString('Probelauf – nichts gespeichert', (string) $result['message']);
        $this->assertStringContainsString('lokale Begriffe ohne Gegenstück', (string) $result['message']);
        $rows = collect($result['rows']);
        $this->assertNotNull($rows->first(fn (array $r): bool => $r['art'] === 'neu' && str_contains($r['titel'], 'NEUX')));
        $this->assertNotNull($rows->first(fn (array $r): bool => $r['art'] === 'geändert' && str_contains($r['felder'], 'Bezeichnung offiziell')));
        $this->assertNotNull($rows->firstWhere('liste', 'Kategorien'));
        $this->assertEquals($before, $this->repo()->all(), 'nichts gespeichert');
        $this->assertNull(collect($this->repo()->listItems('kategorien'))->firstWhere('schluessel', 'neue-kat'));
        $this->assertSame([], array_intersect_key($this->repo()->syncState(), ['cursor' => true]));
    }

    public function test_sync_page_dry_run_and_sync_for_glossary_editors(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/glossar/sync')->assertForbidden();

        $this->signInAs('verwaltung');
        $this->get('/glossar/sync')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('glossary/sync')
            ->where('sync.eingeschaltet', true)
            ->where('sync.token', false)
            ->where('ergebnis', null));
        $this->post('/glossar/sync/probelauf')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('ergebnis.status', 'success')
            ->where('ergebnis.rows', fn ($rows): bool => count($rows) > 0));
        $this->assertArrayNotHasKey('cursor', $this->repo()->syncState());
        $this->post('/glossar/sync')->assertRedirect('/glossar/sync');
        $this->assertSame(10, $this->repo()->syncState()['cursor']);

        app(PluginManager::class)->setEnabled('eduksx-glossary', false);
        $this->get('/glossar/sync')->assertInertia(fn (Assert $page) => $page->where('sync.eingeschaltet', false));
        $this->post('/glossar/sync')->assertRedirect('/glossar/sync');
    }

    public function test_connection_test_with_token_checks_scope(): void
    {
        $this->withToken();
        $test = app(PluginManager::class)->test('eduksx-glossary', null);
        $this->assertTrue($test['ok'], (string) $test['message']);
        $this->assertStringContainsString('Intranet Test', (string) $test['message']);
        $this->assertStringContainsString('propose', (string) $test['message']);
    }

    public function test_propose_new_term_then_accepted_and_linked_without_duplicate(): void
    {
        $local = $this->repo()->create(['abk_eigen' => 'LOKX', 'bez_eigen' => 'Lokaler Begriff', 'genutzt_in' => ['bereich:schule'], 'notiz' => 'nur lokal'] + Glossary::blank());
        $this->signInAs('verwaltung');
        $this->get('/glossar/'.$local['id'])->assertInertia(fn (Assert $page) => $page->where('vorschlagen', false));

        $this->withToken();
        $this->get('/glossar/'.$local['id'])->assertInertia(fn (Assert $page) => $page->where('vorschlagen', true));
        $this->get('/glossar/'.$local['id'].'/vorschlag')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('glossary/propose')
            ->where('werte.official_abbreviation', 'LOKX')
            ->where('werte.official_name', 'Lokaler Begriff'));
        $this->post('/glossar/'.$local['id'].'/vorschlag', ['werte' => ['official_abbreviation' => 'LOKX', 'official_name' => 'Lokaler Begriff', 'categories' => ['gibt-es-nicht']], 'begruendung' => 'Test'])
            ->assertRedirect('/glossar/'.$local['id'].'/vorschlag');
        $this->assertSame([], $this->api['sent'], 'unbekannte Kategorie: nichts gesendet');

        $this->post('/glossar/'.$local['id'].'/vorschlag', ['werte' => ['official_abbreviation' => 'LOKX', 'official_name' => 'Lokaler Begriff', 'short_description' => 'Erfunden', 'categories' => ['beh']], 'begruendung' => 'Aus dem Intranet'])
            ->assertRedirect('/glossar/'.$local['id']);
        $sent = $this->api['sent'][0];
        $this->assertArrayNotHasKey('term', $sent);
        $this->assertSame(['official_abbreviation' => 'LOKX', 'official_name' => 'Lokaler Begriff', 'short_description' => 'Erfunden', 'categories' => ['beh']], $sent['changes']);
        $this->assertSame('Aus dem Intranet', $sent['reason']);
        $this->assertStringNotContainsString('nur lokal', (string) json_encode($sent), 'lokale Felder nie gesendet');
        $note = $this->repo()->find($local['id'])['vorschlaege'][0];
        $this->assertSame(['id' => 7, 'art' => 'neu', 'status' => 'eingereicht'], array_intersect_key($note, ['id' => 1, 'art' => 1, 'status' => 1]));

        // Moderation nimmt an: neuer Begriff im Glossary mit eigener ID
        $acceptedId = '01900000-0000-7000-8000-0000000000aa';
        $this->api['proposals']['7'] = ['status' => 'angenommen', 'term_id' => $acceptedId, 'resolved_at' => '2026-11-09T11:00:00Z'] + $this->api['proposals']['7'];
        $this->sync();
        $this->assertSame('angenommen', $this->repo()->find($local['id'])['vorschlaege'][0]['status']);
        $this->assertSame($acceptedId, $this->repo()->find($local['id'])['vorschlaege'][0]['term_id']);

        $this->api['cursor'] = 11;
        $this->api['pages'] = ['10' => ['data' => [
            ['cursor' => 11, 'action' => 'published', 'term_id' => $acceptedId, 'revision' => 1, 'merged_into' => null, 'term' => $this->term($acceptedId, 'LOKX', 'Lokaler Begriff')],
        ], 'meta' => ['since' => 10, 'next_cursor' => 11, 'has_more' => false, 'release' => 3]]];
        $this->sync();
        $published = $this->repo()->find($acceptedId);
        $this->assertSame('LOKX', $published['abk_offiziell']);
        $this->assertSame(['bereich:schule'], $published['genutzt_in'], 'lokale Daten übernommen');
        $this->assertSame('nur lokal', $published['notiz']);
        $this->assertContains($local['id'], $published['fruehere_ids']);
        $hidden = $this->repo()->find($local['id']);
        $this->assertFalse($hidden['aktiv'], 'lokaler Eintrag ausgeblendet, nicht gelöscht');
        $this->assertSame(1, collect($this->repo()->all())->filter(fn (array $e): bool => Glossary::active($e) && ($e['abk_offiziell'] ?? '') === 'LOKX')->count(), 'keine Dublette');
    }

    public function test_propose_correction_sends_only_changed_fields(): void
    {
        $this->sync();
        $this->withToken();
        $vsa = $this->vsaId();
        $result = app(PluginManager::class)->runAction('eduksx-glossary', 'propose', ['eintrag' => $vsa, 'begruendung' => '', '_changes' => [
            'official_abbreviation' => 'VSA', 'official_name' => 'Volksschulamt Kanton Zürich', 'short_description' => 'Amt für die Volksschule',
            'alternative_aliases' => 'Volksschulamt', 'categories' => ['beh'],
        ]], null);
        $this->assertSame('success', $result['status'], (string) $result['message']);
        $this->assertSame(['term' => $vsa, 'changes' => ['short_description' => 'Amt für die Volksschule'], 'reason' => ''], $this->api['sent'][0]);
        $this->assertSame('korrektur', $this->repo()->find($vsa)['vorschlaege'][0]['art']);

        // ohne Änderung: nichts gesendet
        $result = app(PluginManager::class)->runAction('eduksx-glossary', 'propose', ['eintrag' => $vsa, '_changes' => ['official_abbreviation' => 'VSA', 'official_name' => 'Volksschulamt Kanton Zürich', 'alternative_aliases' => ['Volksschulamt'], 'categories' => ['beh']]], null);
        $this->assertSame('warning', $result['status']);
        $this->assertCount(1, $this->api['sent']);
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
