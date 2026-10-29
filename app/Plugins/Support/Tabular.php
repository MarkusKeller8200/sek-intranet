<?php

namespace App\Plugins\Support;

/**
 * Baut aus beliebigen JSON-Datensätzen eine Tabelle für ActionResult::table().
 * Für Dienste, deren Antwortfelder (noch) nicht genau beschrieben sind.
 */
final class Tabular
{
    /**
     * @param  list<array<string, mixed>>  $records
     * @param  list<string>  $prefer  Felder, die zuerst erscheinen sollen (Punkt für verschachtelt: «general.name»)
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}
     */
    public static function from(array $records, array $prefer = [], int $maxColumns = 7): array
    {
        $keys = [];
        foreach ($prefer as $key) {
            foreach ($records as $record) {
                if (self::get($record, $key) !== null) {
                    $keys[] = $key;
                    break;
                }
            }
        }

        foreach (array_slice($records, 0, 20) as $record) {
            foreach ($record as $key => $value) {
                if (count($keys) >= $maxColumns) {
                    break 2;
                }
                if (is_scalar($value) && ! in_array($key, $keys, true)) {
                    $keys[] = (string) $key;
                }
            }
        }

        $keys = array_slice(array_values(array_unique($keys)), 0, $maxColumns);

        return [
            'columns' => array_map(fn (string $k): array => ['key' => str_replace('.', '_', $k), 'label' => self::label($k)], $keys),
            'rows' => array_map(function (array $record) use ($keys): array {
                $row = [];
                foreach ($keys as $key) {
                    $value = self::get($record, $key);
                    $row[str_replace('.', '_', $key)] = is_scalar($value) || $value === null ? $value : json_encode($value);
                }

                return $row;
            }, $records),
        ];
    }

    /**
     * Wert aus verschachteltem Array, Pfad mit Punkten.
     *
     * @param  array<string, mixed>  $record
     */
    public static function get(array $record, string $path): mixed
    {
        $value = $record;
        foreach (explode('.', $path) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    private static function label(string $key): string
    {
        $last = (string) preg_replace('/.*\./', '', $key);

        return ucfirst((string) preg_replace('/(?<!^)[A-Z_]/', ' $0', str_replace('_', '', $last)));
    }
}
