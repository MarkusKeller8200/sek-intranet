<?php

namespace App\Data;

use Illuminate\Support\Facades\File;

/**
 * Tabellen, die nicht in der Datenablage stehen, sondern als Dateien im Code (#79):
 * die Sprachkataloge des Sprachkonzepts (resources/js/i18n/*.json, je Modul modules/<Ordner>/resources/js/i18n).
 * Für die Übersicht «Datenstrukturen» – gleiche Form wie die Sammlungen, aber nur lesend.
 */
final class CodeCatalogs
{
    /**
     * @return list<array{name: string, label: string, file: string, count: int, translated: array{en: int, gsw: int}, kind: string}>
     */
    public function i18n(): array
    {
        $files = [...File::glob(resource_path('js/i18n/*.json')), ...File::glob(base_path('modules/*/resources/js/i18n/*.json'))];
        $list = [];
        foreach ($files as $path) {
            $data = json_decode((string) File::get($path), true);
            if (! is_array($data)) {
                continue;
            }
            $name = pathinfo($path, PATHINFO_FILENAME);
            $isIgnore = array_is_list($data);
            $en = $gsw = 0;
            if (! $isIgnore) {
                foreach ($data as $entry) {
                    $en += is_array($entry) && trim((string) ($entry[0] ?? '')) !== '' ? 1 : 0;
                    $gsw += is_array($entry) && trim((string) ($entry[1] ?? '')) !== '' ? 1 : 0;
                }
            }
            $list[] = [
                'name' => $name,
                'label' => $isIgnore ? 'Nicht übersetzen (Namen, Fachbegriffe)' : 'Sprachkatalog «'.$name.'»',
                'file' => ltrim(str_replace(base_path(), '', $path), '/'),
                'count' => count($data),
                'translated' => ['en' => $en, 'gsw' => $gsw],
                'kind' => $isIgnore ? 'ignore' : 'catalog',
            ];
        }

        return $list;
    }
}
