<?php

namespace App\Support;

use App\Data\Contracts\GlossaryRepository;
use App\Modules\ModuleRegistry;
use InvalidArgumentException;

/**
 * Glossar (docs/KONZEPT-GLOSSAR.md): Felder, Anzeige, Prüfen und Speichern von Änderungen (Tabelle, Formular,
 * Excel-Import), Sperre der offiziellen Felder, Doppelte finden und zusammenführen.
 */
final class Glossary
{
    public const STATUS = ['aktuell' => 'aktuell', 'historisch' => 'historisch', 'zu_pruefen' => 'zu prüfen'];

    /** Offizielle Felder: ändern nur mit Recht edit-glossary-official (leer → Wert setzen ist erlaubt). */
    public const OFFICIAL = ['abk_offiziell', 'aliase_offiziell', 'bez_offiziell'];

    /** Mehrfachwerte (Text mit «;» in Tabelle und Excel). */
    public const MULTI = ['kategorien', 'aliase_offiziell', 'herkunft', 'genutzt_in'];

    /** Feld → Spaltenname (Tabelle, Excel, Import). */
    public const FIELDS = [
        'kategorien' => 'Kategorien',
        'abk_offiziell' => 'Abkürzung offiziell',
        'aliase_offiziell' => 'Aliase offiziell',
        'abk_eigen' => 'Abkürzung eigene',
        'alias_eigen' => 'Alias eigene',
        'bez_offiziell' => 'Bezeichnung offiziell',
        'bez_eigen' => 'Bezeichnung eigene',
        'kurzbeschreibung' => 'Kurzbeschreibung',
        'beschreibung' => 'Beschreibung',
        'url' => 'URL',
        'notiz' => 'Notiz',
        'herkunft' => 'Herkunft',
        'genutzt_in' => 'Intern genutzt in',
        'status' => 'Status',
        'suchbegriffe' => 'Suchbegriffe',
        'gueltig_ab' => 'Gültig ab',
        'gueltig_bis' => 'Gültig bis',
    ];

    public function __construct(
        private readonly GlossaryRepository $glossary,
        private readonly ModuleRegistry $modules,
    ) {}

    // ---------- Listen und Anzeige ----------

    /**
     * @return array<string, string> id → «KÜRZEL – Bezeichnung»
     */
    public function listOptions(string $list): array
    {
        $options = [];
        foreach ($this->glossary->listItems($list) as $item) {
            $options[(string) $item['id']] = trim((string) ($item['kuerzel'] ?? '').' – '.(string) ($item['bezeichnung'] ?? ''), ' –');
        }

        return $options;
    }

    /**
     * @return array<string, string> id → kurzes Label (Tag in der Tabelle), sonst Abkürzung
     */
    public function labels(string $list): array
    {
        $labels = [];
        foreach ($this->glossary->listItems($list) as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $labels[(string) $item['id']] = $label !== '' ? $label : (string) ($item['kuerzel'] ?? $item['id']);
        }

        return $labels;
    }

    /**
     * Bereiche des Intranets für «Intern genutzt in»: Hauptmenü und Module.
     *
     * @return array<string, string> Schlüssel → Bezeichnung
     */
    public function areaOptions(): array
    {
        $options = [];
        foreach ((array) config('intranet.modules', []) as $key => $group) {
            if (is_array($group) && isset($group['title'])) {
                $options['bereich:'.$key] = (string) $group['title'];
            }
        }
        foreach ($this->modules->all() as $manifest) {
            $options['modul:'.$manifest->key] = 'Modul '.$manifest->name;
        }

        return $options;
    }

    /**
     * Angezeigte Abkürzung: eigene, sonst offizielle.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function shortName(array $entry): string
    {
        $own = trim((string) ($entry['abk_eigen'] ?? ''));

        return $own !== '' ? $own : trim((string) ($entry['abk_offiziell'] ?? ''));
    }

    /**
     * Angezeigte Bezeichnung: eigene, sonst offizielle.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function displayName(array $entry): string
    {
        $own = trim((string) ($entry['bez_eigen'] ?? ''));

        return $own !== '' ? $own : trim((string) ($entry['bez_offiziell'] ?? ''));
    }

    /**
     * Zeilen für die Tabelle: Mehrfachwerte als Text, Kennzeichen «mehrdeutig».
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        $entries = $this->glossary->all();
        $lists = ['kategorien' => $this->listOptions('kategorien'), 'herkunft' => $this->labels('herkunft'), 'genutzt_in' => $this->areaOptions()];
        $count = [];
        foreach ($entries as $e) {
            foreach (self::abbreviations($e) as $a) {
                $count[$a] = ($count[$a] ?? 0) + 1;
            }
        }
        $rows = [];
        foreach ($entries as $e) {
            $row = ['id' => (string) $e['id']];
            foreach (array_keys(self::FIELDS) as $field) {
                $row[$field] = $this->cellText($field, $e[$field] ?? null, $lists);
            }
            $row['mehrdeutig'] = array_filter(self::abbreviations($e), fn (string $a): bool => ($count[$a] ?? 0) > 1) !== [];
            $row['anzeige'] = self::shortName($e);
            $rows[] = $row;
        }
        usort($rows, fn (array $a, array $b): int => strnatcasecmp((string) $a['anzeige'], (string) $b['anzeige']));

        return $rows;
    }

    /**
     * @param  array<string, array<string, string>>  $lists
     */
    private function cellText(string $field, mixed $value, array $lists): string
    {
        if (in_array($field, self::MULTI, true)) {
            $names = $lists[$field] ?? null;

            return implode('; ', array_map(fn ($v): string => $names !== null ? ($names[(string) $v] ?? (string) $v) : (string) $v, is_array($value) ? $value : []));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  array<string, mixed>  $e
     * @return list<string> Abkürzungen eines Eintrags (klein, offiziell und eigene)
     */
    private static function abbreviations(array $e): array
    {
        return array_values(array_unique(array_filter([
            mb_strtolower(trim((string) ($e['abk_offiziell'] ?? ''))),
            mb_strtolower(trim((string) ($e['abk_eigen'] ?? ''))),
        ])));
    }

    // ---------- Prüfen und Speichern ----------

    /**
     * Felder prüfen und in Speicherform bringen. Mehrfachwerte als Liste oder Text mit «;».
     *
     * @param  array<string, mixed>  $input  nur die zu ändernden Felder
     * @param  array<string, mixed>|null  $existing  null = neuer Eintrag
     * @return array{data: array<string, mixed>}|array{errors: array<string, string>}
     */
    public function check(array $input, ?array $existing, bool $canOfficial): array
    {
        $data = [];
        $errors = [];
        $lists = ['kategorien' => $this->glossary->listItems('kategorien'), 'herkunft' => $this->glossary->listItems('herkunft')];
        $areas = $this->areaOptions();
        foreach ($input as $field => $value) {
            if (! isset(self::FIELDS[$field])) {
                $errors[$field] = "Feld «{$field}» gibt es nicht.";

                continue;
            }
            if (in_array($field, self::MULTI, true)) {
                $items = is_array($value) ? array_values(array_map('strval', $value)) : self::split((string) $value);
                $value = match ($field) {
                    'kategorien', 'herkunft' => $this->resolve($items, $lists[$field], $errors, $field),
                    'genutzt_in' => $this->resolveArea($items, $areas, $errors),
                    default => array_values(array_unique(array_map(fn (string $s): string => mb_substr($s, 0, 40), $items))),
                };
            } else {
                $value = trim(is_scalar($value) ? (string) $value : '');
                $max = in_array($field, ['beschreibung', 'notiz'], true) ? 20000 : ($field === 'kurzbeschreibung' ? 160 : 500);
                if (mb_strlen($value) > $max) {
                    $errors[$field] = self::FIELDS[$field]." ist zu lang (max. {$max} Zeichen).";
                }
                if ($field === 'url' && $value !== '' && preg_match('#^https?://\S+$#i', $value) !== 1) {
                    $errors[$field] = 'Die URL muss mit http:// oder https:// beginnen.';
                }
                if ($field === 'status') {
                    $key = array_search($value, self::STATUS, true);
                    $value = isset(self::STATUS[$value]) ? $value : ($key !== false ? $key : $value);
                    if (! isset(self::STATUS[$value])) {
                        $errors[$field] = 'Status: aktuell, historisch oder zu prüfen.';
                    }
                }
                if (in_array($field, ['gueltig_ab', 'gueltig_bis'], true)) {
                    $value = $value === '' ? null : self::date($value);
                    if ($value === false) {
                        $errors[$field] = self::FIELDS[$field].': Datum als TT.MM.JJJJ oder JJJJ-MM-TT.';
                    }
                }
            }
            if (in_array($field, self::OFFICIAL, true) && ! $canOfficial && $existing !== null) {
                $before = $existing[$field] ?? null;
                $empty = $before === null || $before === '' || $before === [];
                if (! $empty && $before !== $value) {
                    $errors[$field] = self::FIELDS[$field].' ist offiziell und gesperrt (Recht «offizielle Begriffe pflegen»).';
                }
            }
            $data[$field] = $value;
        }
        $merged = $data + ($existing ?? []);
        if (trim((string) ($merged['abk_offiziell'] ?? '')) === '' && trim((string) ($merged['abk_eigen'] ?? '')) === ''
            && trim((string) ($merged['bez_offiziell'] ?? '')) === '' && trim((string) ($merged['bez_eigen'] ?? '')) === '' && $existing !== null) {
            $errors['abk_eigen'] = 'Mindestens eine Abkürzung oder Bezeichnung ist nötig.';
        }

        return $errors !== [] ? ['errors' => $errors] : ['data' => $data];
    }

    /**
     * Leerer neuer Eintrag (Felder vollständig).
     *
     * @return array<string, mixed>
     */
    public static function blank(): array
    {
        return ['kategorien' => [], 'abk_offiziell' => '', 'aliase_offiziell' => [], 'abk_eigen' => '', 'alias_eigen' => '',
            'bez_offiziell' => '', 'bez_eigen' => '', 'kurzbeschreibung' => '', 'beschreibung' => '', 'url' => '', 'notiz' => '',
            'herkunft' => [], 'genutzt_in' => [], 'status' => 'aktuell', 'suchbegriffe' => '', 'gueltig_ab' => null, 'gueltig_bis' => null,
            'dubletten_ok' => []];
    }

    /**
     * @return list<string>
     */
    public static function split(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;\n]/', $text) ?: []), fn (string $s): bool => $s !== ''));
    }

    private static function date(string $value): string|false
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return false;
    }

    /**
     * Kürzel, Bezeichnung oder «KÜRZEL – Bezeichnung» → ID der Liste.
     *
     * @param  list<string>  $items
     * @param  list<array<string, mixed>>  $list
     * @param  array<string, string>  $errors
     * @return list<string>
     */
    private function resolve(array $items, array $list, array &$errors, string $field): array
    {
        $ids = [];
        foreach ($items as $item) {
            $needle = mb_strtolower(trim($item));
            $found = null;
            foreach ($list as $row) {
                $label = mb_strtolower(trim((string) ($row['kuerzel'] ?? '').' – '.(string) ($row['bezeichnung'] ?? ''), ' –'));
                if (in_array($needle, [mb_strtolower((string) $row['id']), mb_strtolower((string) ($row['kuerzel'] ?? '')), mb_strtolower(trim((string) ($row['label'] ?? ''))), mb_strtolower((string) ($row['bezeichnung'] ?? '')), $label], true)) {
                    $found = (string) $row['id'];
                    break;
                }
            }
            if ($found === null) {
                $errors[$field] = self::FIELDS[$field].": «{$item}» gibt es nicht in der Liste.";

                continue;
            }
            $ids[] = $found;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $items
     * @param  array<string, string>  $areas
     * @param  array<string, string>  $errors
     * @return list<string>
     */
    private function resolveArea(array $items, array $areas, array &$errors): array
    {
        $ids = [];
        foreach ($items as $item) {
            $key = isset($areas[$item]) ? $item : array_search(mb_strtolower($item), array_map('mb_strtolower', $areas), true);
            if ($key === false) {
                $errors['genutzt_in'] = "Intern genutzt in: «{$item}» gibt es nicht.";

                continue;
            }
            $ids[] = (string) $key;
        }

        return array_values(array_unique($ids));
    }

    // ---------- Doppelte ----------

    /**
     * Gruppen mit gleicher Abkürzung bzw. gleicher Bezeichnung, die noch nicht als «mehrdeutig belassen» gelten.
     *
     * @return list<array{key: string, art: string, wert: string, ids: list<string>}>
     */
    public function duplicates(): array
    {
        $groups = [];
        foreach ($this->glossary->all() as $e) {
            foreach (self::abbreviations($e) as $a) {
                $groups['abk:'.$a][] = $e;
            }
            $name = mb_strtolower(self::displayName($e));
            if ($name !== '') {
                $groups['bez:'.$name][] = $e;
            }
        }
        $result = [];
        foreach ($groups as $key => $members) {
            $ids = array_values(array_unique(array_map(fn (array $m): string => (string) $m['id'], $members)));
            if (count($ids) < 2) {
                continue;
            }
            $done = array_filter($members, fn (array $m): bool => in_array($key, (array) ($m['dubletten_ok'] ?? []), true));
            if (count($done) === count($members)) {
                continue;
            }
            $result[] = ['key' => $key, 'art' => str_starts_with($key, 'abk:') ? 'Abkürzung' : 'Bezeichnung', 'wert' => substr($key, 4), 'ids' => $ids];
        }
        usort($result, fn (array $a, array $b): int => [$a['art'], $a['wert']] <=> [$b['art'], $b['wert']]);

        return $result;
    }

    /**
     * Gruppe als «mehrdeutig belassen» markieren (erscheint nicht mehr als offen).
     *
     * @param  list<string>  $ids
     */
    public function keepApart(string $key, array $ids): void
    {
        foreach ($ids as $id) {
            $e = $this->glossary->find((string) $id);
            if ($e !== null) {
                $ok = array_values(array_unique([...(array) ($e['dubletten_ok'] ?? []), $key]));
                $this->glossary->update((string) $id, ['dubletten_ok' => $ok]);
            }
        }
    }

    /**
     * Einträge zusammenführen: Ziel bleibt, Mehrfachwerte werden vereinigt, Texte aus der gewählten Quelle,
     * die anderen Einträge werden gelöscht. Frühere IDs bleiben am Ziel vermerkt.
     *
     * @param  list<string>  $ids
     * @param  array<string, string>  $choices  Feld → ID des Eintrags, dessen Text gilt
     * @return array<string, mixed> zusammengeführter Eintrag
     */
    public function merge(array $ids, string $targetId, array $choices): array
    {
        $entries = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $entries[(string) $id] = $this->glossary->find((string) $id) ?? throw new InvalidArgumentException("Eintrag {$id} gibt es nicht.");
        }
        if (count($entries) < 2 || ! isset($entries[$targetId])) {
            throw new InvalidArgumentException('Mindestens zwei Einträge und ein Ziel aus der Auswahl sind nötig.');
        }
        $target = $entries[$targetId];
        $update = [];
        foreach ([...self::MULTI, 'dubletten_ok'] as $field) {
            $all = [];
            foreach ($entries as $e) {
                $all = [...$all, ...(array) ($e[$field] ?? [])];
            }
            $update[$field] = array_values(array_unique(array_map('strval', $all)));
        }
        $terms = array_filter(array_map(fn (array $e): string => trim((string) ($e['suchbegriffe'] ?? '')), $entries));
        $update['suchbegriffe'] = implode(', ', array_unique($terms));
        foreach (array_keys(self::FIELDS) as $field) {
            if (in_array($field, [...self::MULTI, 'suchbegriffe'], true)) {
                continue;
            }
            $source = $choices[$field] ?? null;
            if ($source !== null && isset($entries[$source])) {
                $update[$field] = $entries[$source][$field] ?? null;
            } elseif (($target[$field] ?? '') === '' || ($target[$field] ?? null) === null) {
                foreach ($entries as $e) {
                    if (($e[$field] ?? '') !== '' && ($e[$field] ?? null) !== null) {
                        $update[$field] = $e[$field];
                        break;
                    }
                }
            }
        }
        $others = array_values(array_diff(array_keys($entries), [$targetId]));
        $update['fruehere_ids'] = array_values(array_unique([...(array) ($target['fruehere_ids'] ?? []), ...$others]));
        $merged = $this->glossary->update($targetId, $update);
        foreach ($others as $id) {
            $this->glossary->delete($id);
        }

        return $merged;
    }
}
