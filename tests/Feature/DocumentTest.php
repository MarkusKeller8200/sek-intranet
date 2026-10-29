<?php

namespace Tests\Feature;

use App\Data\Contracts\DocumentRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    public function test_documents_index_hides_internal_file_path(): void
    {
        Storage::fake('local');
        $this->signInAs('redaktion');

        $this->post('/dokumente', [
            'title' => 'Merkblatt',
            'category' => 'formulare',
            'kind' => 'file',
            'file' => UploadedFile::fake()->create('merkblatt.pdf', 120, 'application/pdf'),
        ])->assertRedirect('/dokumente');

        $this->get('/dokumente')->assertInertia(fn (Assert $page) => $page
            ->component('documents/index')
            ->has('documents', 6)
            ->where('documents', fn ($docs) => collect($docs)->every(fn ($d) => ! array_key_exists('file_path', $d))));
    }

    public function test_upload_download_replace_and_delete_file(): void
    {
        Storage::fake('local');
        $this->signInAs('redaktion');

        $this->post('/dokumente', [
            'title' => 'Hausordnung',
            'category' => 'reglemente',
            'kind' => 'file',
            'file' => UploadedFile::fake()->createWithContent('hausordnung.txt', 'Version 1'),
        ])->assertRedirect('/dokumente');

        $doc = collect(app(DocumentRepository::class)->all())->firstWhere('title', 'Hausordnung');
        $first = $doc['file_path'];
        Storage::disk('local')->assertExists($first);
        $this->assertSame('hausordnung.txt', $doc['file_name']);

        $download = $this->get("/dokumente/{$doc['id']}/download")->assertOk();
        $this->assertStringContainsString('hausordnung.txt', (string) $download->headers->get('content-disposition'));

        // Bearbeiten ohne neue Datei: Datei bleibt
        $this->put("/dokumente/{$doc['id']}", [
            'title' => 'Hausordnung 2026', 'category' => 'reglemente', 'kind' => 'file',
        ])->assertRedirect('/dokumente');
        $this->assertSame($first, app(DocumentRepository::class)->find($doc['id'])['file_path']);

        // Neue Datei ersetzt die alte
        $this->put("/dokumente/{$doc['id']}", [
            'title' => 'Hausordnung 2026', 'category' => 'reglemente', 'kind' => 'file',
            'file' => UploadedFile::fake()->createWithContent('hausordnung-v2.txt', 'Version 2'),
        ])->assertRedirect('/dokumente');
        $second = app(DocumentRepository::class)->find($doc['id'])['file_path'];
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);

        $this->delete("/dokumente/{$doc['id']}")->assertRedirect('/dokumente');
        Storage::disk('local')->assertMissing($second);
        $this->assertNull(app(DocumentRepository::class)->find($doc['id']));
    }

    public function test_link_requires_valid_url(): void
    {
        $this->signInAs('redaktion');

        $this->post('/dokumente', ['title' => 'Link', 'category' => 'links', 'kind' => 'link', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('url');
        $this->post('/dokumente', ['title' => 'Link', 'category' => 'links', 'kind' => 'link'])
            ->assertSessionHasErrors('url');
    }

    public function test_file_kind_requires_upload_on_create(): void
    {
        $this->signInAs('redaktion');

        $this->post('/dokumente', ['title' => 'Ohne Datei', 'category' => 'formulare', 'kind' => 'file'])
            ->assertSessionHasErrors('file');
    }
}
