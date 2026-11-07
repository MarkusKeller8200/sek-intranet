<?php

namespace App\Support;

use App\Data\Contracts\GlossaryRepository;

/**
 * Vollständiger Datenstand des Glossars (#157) für die Übernahme ins EduKSX-Glossary:
 *
 * - Vollexport der Einträge (auch inaktive) mit allen Feldern und den Spalten «Intern genutzt in (Schlüssel)»,
 *   «Doppelte geprüft», «Erstellt», «Geändert». Die zusätzlichen Spalten stören den bisherigen Import nicht.
 * - Export und Import je Liste (Kategorien, Herkunft) mit allen Feldern des Formulars; der Import zeigt eine
 *   Vorschau (neu / geändert / Fehler) und löscht nie.
 * - Rohdaten als JSON (Befehl intranet:glossar-export).
 */
final class GlossaryTransfer
{
    /** Zusätzliche Spalten des Vollexports. */
    public const FULL_EXTRA = [
        'genutzt_in_keys' => 'Intern genutzt in (Schlüssel)',
        'dubletten_ok' => 'Doppelte geprüft',
        'created_at' => 'Erstellt',
        'updated_at' => 'Geändert',
    ];

    /** Spalten je Liste (wie im Formular der Seite «Listen»). */
    public const LIST_FIELDS = [
        'id' => 'ID',
        'kuerzel' => 'Kürzel',
        'bezeichnung' => 'Bezeichnung',
        'label' => 'Label',
        'farbe' => 'Farbe',
        'beschreibung' => 'Beschreibung',
        'url' => 'URL',
        'notiz' => 'Notiz',
        'position' => 'Reihenfolge',
    ];

    public const LIST_NAMES = ['kategorien' => 'Kategorien', 'herkunft' => 'Herkunft'];

    public function __construct(
        private readonly GlossaryRepository $glossary,
        private readonly Glossary $service,
    ) {}

    // ---------- Einträge ----------

    /**
     * @return list<string>
     */
    public function fullHeader(): array
    {
        return ['ID', ...array_values(Glossary::FIELDS), ...array_values(self::FULL_EXTRA)];
    }

    /**
     * Alle Einträge, auch inaktive, sortiert wie die Tabelle.
     *
     * @return list<list<string>>
     */
    public function fullRows(): array
    {
        $raw = array_column($this->glossary->all(), null, 'id');
        $rows = [];
        foreach ($this->service->rows(true) as $row) {
            $e = $raw[$row['id']] ?? [];
            $rows[] = [
                (string) $row['id'],
                ...array_map(fn (string $f): string => self::text($row[$f] ?? ''), array_keys(Glossary::FIELDS)),
                implode('; ', array_map('strval', (array) ($e['genutzt_in'] ?? []))),
                implode('; ', array_map('strval', (array) ($e['dubletten_ok'] ?? []))),
                (string) ($e['created_at'] ?? ''),
                (string) ($e['updated_at'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Rohdaten für den JSON-Export: Einträge mit allen gespeicherten Feldern und beide Listen.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $entries = $this->glossary->all();
        usort($entries, fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

        return [
            'format' => 'sek-intranet-glossar',
            'version' => 1,
            'exportiert' => now()->toIso8601String(),
            'quelle' => (string) config('app.url'),
            'bereiche' => $this->service->areaOptions(),
            'kategorien' => $this->glossary->listItems('kategorien'),
            'herkunft' => $this->glossary->listItems('herkunft'),
            'eintraege' => $entries,
        ];
    }

    // ---------- Listen ----------

    /**
     * @return list<list<string>>
     */
    public function listRows(string $list): array
    {
        return array_map(
            fn (array $item): array => array_map(fn (string $f): string => self::text($item[$f] ?? ''), array_keys(self::LIST_FIELDS)),
            $this->glossary->listItems($list),
        );
    }

    /**
     * Vorschau eines Listen-Imports. Zeilen ohne ID sind neu, Zeilen mit bekannter ID werden geändert, unveränderte
     * Zeilen fehlen in der Vorschau. Nie löschen.
     *
     * @param  list<array<string, string>>  $rows  Zeilen nach Spaltenname
     * @return list<array{zeile: int, art: string, id: string, titel: string, meldungen: list<string>, aenderungen: array<string, string>, daten?: array<string, mixed>}>
     */
    public function listPlan(string $list, array $rows): array
    {
        $items = array_column($this->glossary->listItems($list), null, 'id');
        $byLabel = array_flip(self::LIST_FIELDS);
        // Kürzel müssen eindeutig bleiben: bestehende plus die der Datei
        $kuerzel = [];
        foreach ($items as $id => $item) {
            $kuerzel[mb_strtolower((string) ($item['kuerzel'] ?? ''))] = (string) $id;
        }
        $plan = [];
        foreach ($rows as $n => $row) {
            $fields = [];
            foreach ($row as $label => $value) {
                if (isset($byLabel[$label])) {
                    $fields[$byLabel[$label]] = trim($value);
                }
            }
            $id = (string) ($fields['id'] ?? '');
            unset($fields['id']);
            $existing = $id !== '' ? ($items[$id] ?? null) : null;
            $title = trim(($fields['kuerzel'] ?? (string) ($existing['kuerzel'] ?? '')).' – '.($fields['bezeichnung'] ?? (string) ($existing['bezeichnung'] ?? '')), ' –');
            $errors = [];
            if ($id !== '' && $existing === null) {
                $errors[] = 'ID gibt es nicht (Zeile ohne ID = neuer Eintrag).';
            }
            $data = $errors === [] ? $this->checkListItem($fields, $existing, $errors) : [];
            $key = mb_strtolower((string) ($data['kuerzel'] ?? ''));
            if ($errors === [] && isset($kuerzel[$key]) && $kuerzel[$key] !== ($id !== '' ? $id : null)) {
                $errors[] = 'Kürzel «'.(string) $data['kuerzel'].'» gibt es schon.';
            }
            if ($errors !== []) {
                $plan[] = ['zeile' => $n + 2, 'art' => 'fehler', 'id' => $id, 'titel' => $title, 'meldungen' => $errors, 'aenderungen' => []];

                continue;
            }
            $kuerzel[$key] = $id !== '' ? $id : 'neu:'.$n;
            $changes = $existing === null
                ? array_filter($data, fn ($v): bool => $v !== '')
                : array_filter($data, fn ($v, string $f): bool => (string) ($existing[$f] ?? '') !== (string) $v, ARRAY_FILTER_USE_BOTH);
            if ($existing !== null && $changes === []) {
                continue;
            }
            $plan[] = [
                'zeile' => $n + 2,
                'art' => $existing === null ? 'neu' : 'geaendert',
                'id' => $id,
                'titel' => $title,
                'meldungen' => [],
                'aenderungen' => array_map(fn ($v): string => (string) $v, $changes),
                'daten' => $existing === null ? $data : $changes,
            ];
        }

        return $plan;
    }

    /**
     * Vorschau übernehmen.
     *
     * @param  array<mixed>  $plan
     * @return array{neu: int, geaendert: int, log: list<array{0: array<string, mixed>|null, 1: array<string, mixed>}>}
     */
    public function applyListPlan(string $list, array $plan): array
    {
        $new = 0;
        $changed = 0;
        $log = [];
        $known = array_column($this->glossary->listItems($list), null, 'id');
        foreach ($plan as $p) {
            if (! is_array($p)) {
                continue;
            }
            /** @var array<string, mixed> $data */
            $data = (array) ($p['daten'] ?? []);
            if (($p['art'] ?? '') === 'neu') {
                $log[] = [null, $this->glossary->saveListItem($list, null, $data)];
                $new++;
            } elseif (($p['art'] ?? '') === 'geaendert' && isset($known[(string) $p['id']])) {
                $log[] = [$known[(string) $p['id']], $this->glossary->saveListItem($list, (string) $p['id'], $data)];
                $changed++;
            }
        }

        return ['neu' => $new, 'geaendert' => $changed, 'log' => $log];
    }

    /**
     * Felder einer Listenzeile prüfen (Regeln wie im Formular). Fehlende Spalten behalten den bisherigen Wert.
     *
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>|null  $existing
     * @param  list<string>  $errors
     * @return array<string, string|int>
     */
    private function checkListItem(array $fields, ?array $existing, array &$errors): array
    {
        $value = fn (string $f): string => array_key_exists($f, $fields) ? $fields[$f] : (string) ($existing[$f] ?? '');
        $data = [];
        $max = ['kuerzel' => 20, 'label' => 30, 'bezeichnung' => 200, 'beschreibung' => 2000, 'url' => 500, 'notiz' => 5000];
        foreach ($max as $f => $limit) {
            $v = $value($f);
            if (mb_strlen($v) > $limit) {
                $errors[] = self::LIST_FIELDS[$f]." ist zu lang (max. {$limit} Zeichen).";
            }
            $data[$f] = $v;
        }
        foreach (['kuerzel', 'bezeichnung'] as $f) {
            if ($data[$f] === '') {
                $errors[] = self::LIST_FIELDS[$f].' fehlt.';
            }
        }
        if ($data['url'] !== '' && preg_match('#^https?://\S+$#i', $data['url']) !== 1) {
            $errors[] = 'Die URL muss mit http:// oder https:// beginnen.';
        }
        $color = mb_strtolower($value('farbe'));
        if ($color !== '' && ! in_array($color, Glossary::COLORS, true)) {
            $errors[] = "Farbe «{$color}» gibt es nicht (".implode(', ', Glossary::COLORS).').';
        }
        $data['farbe'] = $color;
        $position = $value('position');
        if ($position !== '') {
            if (preg_match('/^\d{1,4}$/', $position) !== 1) {
                $errors[] = 'Reihenfolge: ganze Zahl.';
            } else {
                $data['position'] = (int) $position;
            }
        } elseif ($existing !== null && isset($existing['position'])) {
            $data['position'] = (int) $existing['position'];
        }

        return $data;
    }

    private static function text(mixed $value): string
    {
        return is_bool($value) ? ($value ? 'ja' : 'nein') : (is_scalar($value) ? (string) $value : '');
    }
}
