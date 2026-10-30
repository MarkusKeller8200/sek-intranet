<?php

namespace Tests\Feature;

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

        foreach ((array) config('intranet.modules') as $group) {
            $this->assertArrayHasKey($group['title'], $keys, "Menütitel «{$group['title']}» fehlt im Katalog");
            foreach ((array) $group['items'] as $item) {
                $this->assertArrayHasKey($item['title'], $keys, "Menüpunkt «{$item['title']}» fehlt im Katalog");
            }
        }
    }

    public function test_language_concept_page_is_available_to_administration(): void
    {
        $this->signInAs('admin');
        $this->get('/sprachkonzept')->assertOk();
    }
}
