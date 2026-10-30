<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use App\Plugins\PluginManager;
use App\Plugins\Support\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Escola-Import per Datei. Testdateien: erfundene Personen im Spaltenformat der
 * Escola-Adressliste, inklusive erfundener schützenswerter Werte, die nie
 * übernommen werden dürfen.
 */
class PluginEscolaImportTest extends TestCase
{
    private function upload(string $extension = 'xlsx'): UploadedFile
    {
        $path = base_path("tests/Fixtures/escola/adressliste-test.{$extension}");

        return new UploadedFile($path, "Adressliste.{$extension}", null, null, true);
    }

    private function enable(bool $allowWrite = false): void
    {
        $manager = app(PluginManager::class);
        $manager->saveSettings('escola', ['_allow_write' => $allowWrite]);
        $manager->setEnabled('escola', true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(array $payload, string $extension = 'xlsx'): array
    {
        return $this->post('/plugins/escola/aktion/import-file', [
            'payload' => json_encode($payload),
            'file_file' => $this->upload($extension),
        ], ['Accept' => 'application/json'])->assertOk()->json();
    }

    public function test_reader_returns_only_whitelisted_columns_for_xlsx_and_csv(): void
    {
        foreach (['xlsx', 'csv'] as $extension) {
            $table = SpreadsheetReader::read(base_path("tests/Fixtures/escola/adressliste-test.{$extension}"), "a.{$extension}", ['Name', 'Rollen', 'E-Mail Geschäft']);

            $this->assertCount(117, $table['header'], $extension);
            $this->assertCount(7, $table['rows'], $extension);
            $this->assertSame(['Name', 'E-Mail Geschäft', 'Rollen'], array_keys($table['rows'][0]), $extension);
            $this->assertSame('Behörde', $table['rows'][5]['Name'], "Umlaute ({$extension})");
            $this->assertStringNotContainsString('GEHEIM', json_encode($table, JSON_UNESCAPED_UNICODE) ?: '', $extension);
        }
    }

    public function test_preview_maps_schools_functions_and_skips_authorities_and_missing_email(): void
    {
        $this->enable();
        $this->signInAs('admin');

        $result = $this->send(['options' => ['include_authorities' => false]]);

        $this->assertTrue($result['preview']);
        $this->assertStringContainsString('Ausgelassen: 1 ohne Geschäfts-E-Mail, 1 Behörde/RPK.', $result['message']);

        $rows = collect($result['rows'])->keyBy('_key');
        $this->assertSame('ändern', $rows['daniel.probe@sekbuelach.example']['_change']);
        $this->assertSame('neu', $rows['nora.beispiel@sekbuelach.example']['_change']);
        $this->assertSame('mettmenriet', $rows['nora.beispiel@sekbuelach.example']['school']);
        $this->assertSame('Lehrperson', $rows['nora.beispiel@sekbuelach.example']['function']);
        $this->assertSame('Schulleitung', $rows['felix.muster@sekbuelach.example']['function']);
        $this->assertSame('verwaltung', $rows['lea.test@sekbuelach.example']['school']);
        $this->assertSame('gesamt', $rows['sam.doppel@sekbuelach.example']['school']);
        $this->assertArrayNotHasKey('rita.behoerde@sekbuelach.example', $rows->all());

        $runs = (string) file_get_contents(app(JsonStore::class)->filePath('plugin_runs'));
        $this->assertStringNotContainsString('GEHEIM', $runs);
        $this->assertStringNotContainsString('756.0000', $runs);
    }

    public function test_authorities_can_be_included_and_csv_works(): void
    {
        $this->enable();
        $this->signInAs('admin');

        $rows = collect($this->send(['options' => ['include_authorities' => true]], 'csv')['rows'])->keyBy('_key');

        $this->assertSame('neu', $rows['rita.behoerde@sekbuelach.example']['_change']);
        $this->assertSame('Präsidentin (Test)', $rows['rita.behoerde@sekbuelach.example']['function']);
    }

    public function test_apply_needs_write_permission_and_stores_no_sensitive_data(): void
    {
        $this->enable(allowWrite: false);
        $this->signInAs('admin');

        $this->post('/plugins/escola/aktion/import-file', [
            'payload' => json_encode(['apply' => true, 'selected' => ['nora.beispiel@sekbuelach.example']]),
            'file_file' => $this->upload(),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->enable(allowWrite: true);
        $result = $this->send(['apply' => true, 'selected' => ['nora.beispiel@sekbuelach.example', 'felix.muster@sekbuelach.example']]);
        $this->assertSame('2 Personen angelegt, 0 geändert.', $result['message']);

        $nora = app(UserRepository::class)->findByEmail('nora.beispiel@sekbuelach.example');
        $this->assertSame('Nora Beispiel', $nora['name']);
        $this->assertSame('mitarbeitende', $nora['role']);
        $this->assertSame('mitarbeitende', app(UserRepository::class)->findByEmail('felix.muster@sekbuelach.example')['role'], 'Escola-Admin wird nicht Intranet-Admin');

        $stored = (string) file_get_contents(app(JsonStore::class)->filePath('users'));
        foreach (['GEHEIM', '756.0000', 'privat@example.test', 'CH00'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
    }

    public function test_wrong_file_is_rejected_with_a_clear_message(): void
    {
        $this->enable();
        $this->signInAs('admin');

        $wrong = UploadedFile::fake()->createWithContent('liste.csv', "Spalte A;Spalte B\n1;2\n");
        $result = $this->post('/plugins/escola/aktion/import-file', ['payload' => '{}', 'file_file' => $wrong], ['Accept' => 'application/json'])->json();

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('Es fehlen die Spalten: Name, Vorname, E-Mail Geschäft, Rollen', $result['message']);

        $result = $this->postJson('/plugins/escola/aktion/import-file', ['options' => []])->json();
        $this->assertStringContainsString('Bitte die Escola-Adressliste wählen', $result['message']);
    }

    public function test_private_contact_details_only_with_option_and_never_sensitive_data(): void
    {
        $this->enable(allowWrite: true);
        $this->signInAs('admin');

        $this->send(['options' => ['include_private' => true], 'apply' => true, 'selected' => ['lea.test@sekbuelach.example']]);

        $lea = app(UserRepository::class)->findByEmail('lea.test@sekbuelach.example');
        $this->assertSame(['Lea', 'Test', 'Lea Test'], [$lea['first_name'], $lea['last_name'], $lea['name']]);
        $this->assertSame('privat@example.test', $lea['private_email']);
        $this->assertSame('lea.test', $lea['username']);
        $this->assertSame('904', $lea['escola_id']);

        $stored = (string) file_get_contents(app(JsonStore::class)->filePath('users'));
        foreach (['GEHEIM', '756.0000', 'CH00'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
    }
}
