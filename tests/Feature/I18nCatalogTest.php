<?php

namespace Tests\Feature;

use App\Modules\ModuleRegistry;
use Tests\TestCase;

/** Sprachkataloge (docs/KONZEPT-SPRACHEN.md): gültig, vollständig, gleiche Platzhalter. */
class I18nCatalogTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalogs(): array
    {
        $catalogs = [];
        foreach (glob(resource_path('js/i18n/*.json')) ?: [] as $file) {
            if (basename($file) === 'ignore.json') {
                continue;
            }
            $data = json_decode((string) file_get_contents($file), true);
            $this->assertIsArray($data, basename($file).' ist kein gültiges JSON-Objekt');
            /** @var array<string, mixed> $data */
            $catalogs[basename($file)] = $data;
        }

        return $catalogs;
    }

    public function test_every_entry_has_english_and_zurich_german(): void
    {
        $catalogs = $this->catalogs();
        $this->assertNotEmpty($catalogs);

        foreach ($catalogs as $file => $entries) {
            foreach ($entries as $german => $translations) {
                $this->assertIsArray($translations, "{$file}: «{$german}»");
                $this->assertCount(2, $translations, "{$file}: «{$german}» braucht [English, Züritüütsch]");
                foreach ($translations as $text) {
                    $this->assertIsString($text);
                    $this->assertNotSame('', trim($text), "{$file}: «{$german}» hat eine leere Übersetzung");
                    $this->assertSame(substr_count($german, '{n}'), substr_count($text, '{n}'), "{$file}: «{$german}» – Anzahl {n} stimmt nicht");
                }
            }
        }
    }

    public function test_no_text_appears_in_two_catalogs(): void
    {
        $seen = [];
        foreach ($this->catalogs() as $file => $entries) {
            foreach (array_keys($entries) as $german) {
                $this->assertArrayNotHasKey($german, $seen, "«{$german}» steht in {$file} und ".($seen[$german] ?? ''));
                $seen[$german] = $file;
            }
        }
    }

    public function test_module_titles_are_translated(): void
    {
        $keys = [];
        foreach ($this->catalogs() as $entries) {
            $keys += $entries;
        }
        // Menüpunkte der Module stehen in deren eigenen Katalogen (modules/<Ordner>/resources/js/i18n)
        foreach (glob(app(ModuleRegistry::class)->path().'/*/resources/js/i18n/*.json') ?: [] as $file) {
            $keys += (array) json_decode((string) file_get_contents($file), true);
        }

        // Wie im Browser: Zahlen werden zu {n}
        $key = fn (string $text): string => (string) preg_replace("/\\d+(?:['’.,]\\d+)*/u", '{n}', $text);

        foreach ((array) config('intranet.modules') as $group) {
            $this->assertArrayHasKey($key($group['title']), $keys, "Menütitel «{$group['title']}» fehlt im Katalog");
            foreach ((array) $group['items'] as $item) {
                $this->assertArrayHasKey($key($item['title']), $keys, "Menüpunkt «{$item['title']}» fehlt im Katalog");
            }
        }
    }

    public function test_language_concept_page_is_available_to_administration(): void
    {
        $this->signInAs('admin');
        $this->get('/sprachkonzept')->assertOk();
    }

    public function test_pages_are_declared_as_swiss_german(): void
    {
        $this->signInAs('lehrperson');

        $html = (string) $this->get('/start')->getContent();
        $this->assertStringContainsString('<html lang="de-CH"', $html);
        $this->assertStringContainsString('<meta http-equiv="content-language" content="de-CH">', $html);
        $this->assertSame('de', app()->getLocale());
    }
}
