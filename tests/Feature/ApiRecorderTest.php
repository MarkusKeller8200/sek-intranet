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
            ->component('api-recorder')
            ->has('docs', 0)
            ->where('origin', rtrim((string) config('app.url'), '/')));
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
        $bad = $this->doc();
        $bad['operations'][0]['method'] = 'HACK';
        $this->postJson('/api-recorder', $bad)->assertStatus(422);
    }

    public function test_recorder_scripts_keep_secrets_out(): void
    {
        $openapi = (string) file_get_contents(resource_path('js/lib/api-recorder/openapi.ts'));
        $capture = (string) file_get_contents(resource_path('js/lib/api-recorder/capture.ts'));

        // Kopfzeilen mit Geheimnissen werden maskiert, Beispielwerte nur maskiert übernommen
        $this->assertStringContainsString('authorization|cookie|token', $openapi);
        $this->assertStringContainsString('pass(?:word|wort)?|secret|token', $openapi);
        // Meldungen gehen nur an das Intranet (Ziel-Origin) und werden nur vom selbst geöffneten Fenster angenommen
        $this->assertStringContainsString('postMessage({ type: TYPE, entry }, ORIGIN)', $capture);
        $page = (string) file_get_contents(resource_path('js/pages/api-recorder.tsx'));
        $this->assertStringContainsString('event.source !== popup.current', $page);
    }
}
