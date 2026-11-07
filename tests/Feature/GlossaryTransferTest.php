<?php

namespace Tests\Feature;

use App\Data\Contracts\GlossaryRepository;
use App\Plugins\Support\SpreadsheetReader;
use App\Support\GlossaryTransfer;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Vollständiger Export des Glossars und Export/Import je Liste (#157).
 */
class GlossaryTransferTest extends TestCase
{
    private function repo(): GlossaryRepository
    {
        return app(GlossaryRepository::class);
    }

    /**
     * @return array{header: list<string>, rows: list<array<string, string>>}
     */
    private function read(TestResponse $response, array $columns): array
    {
        $path = tempnam(sys_get_temp_dir(), 'gx').'.xlsx';
        file_put_contents($path, (string) $response->baseResponse->getContent());

        return SpreadsheetReader::read($path, 'x.xlsx', $columns);
    }

    private function upload(TestResponse $response): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'gx').'.xlsx';
        file_put_contents($path, (string) $response->baseResponse->getContent());

        return new UploadedFile($path, 'export.xlsx', null, null, true);
    }

    public function test_full_export_with_all_entries_keys_and_timestamps(): void
    {
        $vsa = collect($this->repo()->all())->firstWhere('abk_offiziell', 'VSA');
        $this->repo()->update($vsa['id'], ['aktiv' => false, 'genutzt_in' => ['bereich:personal'], 'dubletten_ok' => ['abk:vsa']]);

        $this->signInAs('lehrperson');
        $this->post('/glossar/export', ['voll' => true])->assertForbidden();

        $this->signInAs('verwaltung');
        $response = $this->post('/glossar/export', ['voll' => true])->assertOk();
        $this->assertStringContainsString('glossar-vollstaendig-', (string) $response->headers->get('Content-Disposition'));
        $transfer = app(GlossaryTransfer::class);
        $table = $this->read($response, $transfer->fullHeader());
        $this->assertSame($transfer->fullHeader(), $table['header']);
        $this->assertCount(141, $table['rows'], 'auch inaktive');
        $row = collect($table['rows'])->firstWhere('ID', $vsa['id']);
        $this->assertSame('nein', $row['Aktiv']);
        $this->assertSame('bereich:personal', $row['Intern genutzt in (Schlüssel)']);
        $this->assertSame('abk:vsa', $row['Doppelte geprüft']);
        $this->assertNotSame('', $row['Erstellt']);
        $this->assertNotSame('', $row['Geändert']);
    }

    public function test_full_export_imports_without_changes_and_old_export_unchanged(): void
    {
        $this->signInAs('verwaltung');
        $old = $this->read($this->post('/glossar/export')->assertOk(), ['ID', 'Kategorien', 'Intern genutzt in (Schlüssel)']);
        $this->assertNotContains('Intern genutzt in (Schlüssel)', $old['header'], 'bisheriger Export unverändert');

        $response = $this->post('/glossar/import', ['datei' => $this->upload($this->post('/glossar/export', ['voll' => true]))])->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('glossary/import')->where('plan', []));
    }

    public function test_list_export_and_round_trip_changes_nothing(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/glossar/listen/kategorien/export')->assertForbidden();

        $this->signInAs('verwaltung');
        $this->get('/glossar/listen/gibt-es-nicht/export')->assertNotFound();
        $response = $this->get('/glossar/listen/kategorien/export')->assertOk();
        $table = $this->read($response, array_values(GlossaryTransfer::LIST_FIELDS));
        $this->assertSame(array_values(GlossaryTransfer::LIST_FIELDS), $table['header']);
        $this->assertCount(10, $table['rows']);
        $this->assertSame('1', $table['rows'][0]['Reihenfolge']);
        $this->assertNotSame('', $table['rows'][0]['Farbe']);

        $preview = $this->post('/glossar/listen/kategorien/import', ['datei' => $this->upload($response)])->assertOk();
        $preview->assertInertia(fn (Assert $page) => $page->component('glossary/import')->where('plan', [])
            ->where('zurueck', '/glossar/listen?liste=kategorien'));
    }

    public function test_list_import_with_preview_new_changed_errors_never_delete(): void
    {
        $this->signInAs('verwaltung');
        $before = $this->repo()->listItems('herkunft');
        $first = $before[0];
        $header = array_values(GlossaryTransfer::LIST_FIELDS);
        $row = fn (array $v): array => array_map(fn (string $h): string => $v[$h] ?? '', $header);
        $content = SpreadsheetWriter::xlsx($header, [
            $row(['ID' => $first['id'], 'Kürzel' => $first['kuerzel'], 'Bezeichnung' => 'Geänderte Bezeichnung', 'Farbe' => 'teal', 'Reihenfolge' => '7']),
            $row(['Kürzel' => 'NEUQ', 'Bezeichnung' => 'Neue Quelle', 'URL' => 'https://example.org']),
            $row(['Kürzel' => 'NEUQ', 'Bezeichnung' => 'Doppelt in der Datei']),
            $row(['Kürzel' => 'FARB', 'Bezeichnung' => 'Falsche Farbe', 'Farbe' => 'pink']),
            $row(['ID' => 'gibt-es-nicht', 'Kürzel' => 'X', 'Bezeichnung' => 'X']),
            $row(['Kürzel' => 'URL', 'Bezeichnung' => 'Ohne https', 'URL' => 'ftp://x']),
        ], 'Herkunft');
        $path = tempnam(sys_get_temp_dir(), 'gl').'.xlsx';
        file_put_contents($path, $content);

        $response = $this->post('/glossar/listen/herkunft/import', ['datei' => new UploadedFile($path, 'herkunft.xlsx', null, null, true)])->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('plan', fn ($plan): bool => collect($plan)->pluck('art')->sort()->values()->all() === ['fehler', 'fehler', 'fehler', 'fehler', 'geaendert', 'neu']));
        $props = $response->viewData('page')['props'];
        $this->assertSame($first['bezeichnung'], $this->repo()->listItems('herkunft')[0]['bezeichnung'], 'Vorschau speichert nichts');

        $this->post('/glossar/listen/kategorien/import/'.$props['token'])->assertNotFound();
        $this->post($props['ziel'])->assertRedirect('/glossar/listen?liste=herkunft');
        $after = collect($this->repo()->listItems('herkunft'));
        $changed = $after->firstWhere('id', $first['id']);
        $this->assertSame('Geänderte Bezeichnung', $changed['bezeichnung']);
        $this->assertSame('teal', $changed['farbe']);
        $this->assertSame(7, $changed['position']);
        $this->assertSame('https://example.org', $after->firstWhere('kuerzel', 'NEUQ')['url']);
        $this->assertCount(count($before) + 1, $after, 'nie gelöscht');
        $this->post($props['ziel'])->assertNotFound();
    }

    public function test_json_export_command(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gj').'.json';
        $this->artisan('intranet:glossar-export', ['--datei' => $path])->assertSuccessful();
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertSame('sek-intranet-glossar', $data['format']);
        $this->assertCount(141, $data['eintraege']);
        $this->assertCount(10, $data['kategorien']);
        $this->assertArrayHasKey('created_at', $data['eintraege'][0]);
        $this->assertArrayHasKey('genutzt_in', $data['eintraege'][0]);
    }
}
