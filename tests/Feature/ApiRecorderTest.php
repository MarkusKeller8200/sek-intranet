<?php

namespace Tests\Feature;

use App\Data\Store\JsonStore;
use App\Http\Controllers\ApiRecorderController;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiRecorderTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function doc(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Test-API (erfunden)',
            'base_url' => 'https://example.org',
            'description' => '',
            'operations' => [[
                'key' => 'GET https://example.org/api/kurse/{id}',
                'method' => 'GET',
                'path' => '/api/kurse/{id}',
                'summary' => 'Kurs laden',
                'tag' => 'kurse',
                'status' => 200,
                'query' => [],
                'requestType' => null,
                'requestSchema' => null,
                'responseType' => 'application/json',
                'responseSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                'requestExample' => null,
                'responseExample' => null,
                'samples' => 2,
            ]],
        ];
    }

    public function test_recorder_page_is_for_administration_only(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/api-recorder')->assertForbidden();
        $this->postJson('/api-recorder', $this->doc())->assertForbidden();

        $this->signInAs('admin');
        $this->get('/api-recorder')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('api-recorder/index')
            ->has('docs', 0));
        $this->get('/api-recorder/neu')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('api-recorder')
            ->where('current', null)
            ->where('plugins', fn ($plugins): bool => collect($plugins)->contains('id', 'jamf'))
            ->where('origin', rtrim((string) config('app.url'), '/')));
        $this->get('/api-recorder/gibt-es-nicht')->assertNotFound();
    }

    public function test_saved_api_is_listed_and_reopened_with_its_results(): void
    {
        $this->signInAs('admin');

        $doc = $this->postJson('/api-recorder', $this->doc(['start_url' => 'https://example.org/app']))->assertOk()->json('doc');
        $this->assertNotEmpty($doc['recorded_at']);

        $this->get('/api-recorder')->assertInertia(fn (Assert $page) => $page
            ->component('api-recorder/index')
            ->where('docs.0.title', 'Test-API (erfunden)')
            ->where('docs.0.start_url', 'https://example.org/app')
            ->where('docs.0.plugin_name', null));

        // Wieder öffnen: gespeicherte Endpunkte kommen mit
        $this->get("/api-recorder/{$doc['id']}")->assertInertia(fn (Assert $page) => $page
            ->component('api-recorder')
            ->where('current.id', $doc['id'])
            ->where('current.operations.0.path', '/api/kurse/{id}')
            ->where('current.operations.0.samples', 2));

        // Weiter aufzeichnen: ein Endpunkt mehr
        $more = $this->doc(['id' => $doc['id']]);
        $more['operations'][] = ['key' => 'POST https://example.org/api/kurse', 'method' => 'POST', 'path' => '/api/kurse', 'summary' => '', 'tag' => 'kurse', 'status' => 201];
        $this->postJson('/api-recorder', $more)->assertOk()->assertJsonCount(2, 'doc.operations');
    }

    public function test_documentation_is_assigned_to_a_plugin_and_compared(): void
    {
        $this->signInAs('admin');

        $doc = $this->doc(['plugin_id' => 'jamf', 'title' => 'Jamf (Aufzeichnung)']);
        $doc['operations'][] = ['key' => 'GET https://jamf.example/api/v1/jamf-pro-version', 'method' => 'GET', 'path' => '/api/v1/jamf-pro-version/', 'summary' => 'Version', 'tag' => 'v1', 'status' => 200];
        $doc['operations'][0]['query'] = ['page' => 'integer'];
        $id = $this->postJson('/api-recorder', $doc)->assertOk()->json('doc.id');

        $this->get('/api-recorder')->assertInertia(fn (Assert $page) => $page
            ->where('docs.0.plugin_name', 'Jamf Pro')
            ->where('docs.0.missing', 1));

        // Plugin-Seite zeigt die Aufzeichnung mit Abgleich
        $this->get('/plugins/jamf')->assertInertia(fn (Assert $page) => $page
            ->where('recordings.0.id', $id)
            ->where('recordings.0.operations.0.endpoint', null)
            ->where('recordings.0.operations.1.endpoint', 'version'));

        // Entwurf enthält nur den fehlenden Endpunkt, Status partial
        $draft = $this->get("/api-recorder/{$id}/plugin-entwurf")->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="jamf-aufzeichnung-plugin-endpunkte.json"')
            ->json();
        $this->assertSame('jamf', $draft['plugin']);
        $this->assertCount(1, $draft['endpoints']);
        $this->assertSame('kurse-one', $draft['endpoints'][0]['id']);
        $this->assertSame('partial', $draft['endpoints'][0]['status']);
        $this->assertSame([['name' => 'id', 'required' => true], ['name' => 'page']], $draft['endpoints'][0]['params']);

        // Unbekanntes Plugin wird abgelehnt
        $this->postJson('/api-recorder', $this->doc(['plugin_id' => 'gibt-es-nicht']))->assertStatus(422);
    }

    public function test_documentation_is_saved_updated_and_deleted(): void
    {
        $this->signInAs('admin');

        $id = $this->postJson('/api-recorder', $this->doc())->assertOk()
            ->assertJsonPath('doc.operations.0.path', '/api/kurse/{id}')
            ->json('doc.id');

        $this->postJson('/api-recorder', $this->doc(['id' => $id, 'title' => 'Umbenannt']))->assertOk();
        $docs = app(JsonStore::class)->all(ApiRecorderController::COLLECTION);
        $this->assertCount(1, $docs);
        $this->assertSame('Umbenannt', $docs[0]['title']);

        $this->get('/api-recorder')->assertInertia(fn (Assert $page) => $page->has('docs', 1));

        $this->deleteJson("/api-recorder/{$id}")->assertOk();
        $this->assertSame([], app(JsonStore::class)->all(ApiRecorderController::COLLECTION));
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->signInAs('admin');

        $this->postJson('/api-recorder', $this->doc(['title' => '']))->assertStatus(422);
        $this->postJson('/api-recorder', $this->doc(['base_url' => 'javascript:alert(1)']))->assertStatus(422);
        $this->postJson('/api-recorder', $this->doc(['start_url' => 'javascript:alert(1)']))->assertStatus(422);
        $bad = $this->doc();
        $bad['operations'][0]['method'] = 'HACK';
        $this->postJson('/api-recorder', $bad)->assertStatus(422);
    }

    public function test_recorder_scripts_keep_secrets_out(): void
    {
        // Leerraum vereinheitlichen (Formatierung bricht Zeilen um)
        $read = fn (string $path): string => (string) preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path($path)));
        $openapi = $read('js/lib/api-recorder/openapi.ts');
        $capture = $read('js/lib/api-recorder/capture.ts');

        // Kopfzeilen mit Geheimnissen werden maskiert, Beispielwerte nur maskiert übernommen
        $this->assertStringContainsString('authorization|cookie|token', $openapi);
        $this->assertStringContainsString('pass(?:word|wort)?|secret|token', $openapi);
        // Meldungen gehen nur an das Intranet (Ziel-Origin) und werden nur vom selbst geöffneten Fenster angenommen
        $this->assertMatchesRegularExpression('/postMessage\(\s*\{ type: TYPE, entry \},\s*ORIGIN,?\s*\)/', $capture);
        $page = $read('js/pages/api-recorder.tsx');
        $this->assertStringContainsString('event.source !== popup.current', $page);
    }
}
