<?php

namespace App\Data;

use App\Data\Store\JsonStore;
use App\Modules\ModuleRegistry;

/**
 * Vergleicht das in config/intranet.php beschriebene Schema mit den
 * tatsächlich gespeicherten Datensätzen. Grundlage für die Seite
 * «Datenstrukturen» und für die spätere Datenbank-Migration.
 */
final class SchemaInspector
{
    /**
     * Vorschlag für PostgreSQL-Spaltentypen je Schema-Typ.
     */
    private const POSTGRES_TYPES = [
        'uuid' => 'uuid',
        'string' => 'varchar(255)',
        'text' => 'text',
        'email' => 'varchar(255)',
        'url' => 'varchar(2048)',
        'bool' => 'boolean',
        'int' => 'bigint',
        'date' => 'date',
        'datetime' => 'timestamptz',
        'enum' => 'varchar(50) + CHECK',
        'ref' => 'uuid (FK)',
        'json' => 'jsonb',
    ];

    public function __construct(
        private readonly JsonStore $store,
        private readonly ?ModuleRegistry $modules = null,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function inspect(): array
    {
        /** @var array<string, array<string, mixed>> $schema */
        $schema = config('intranet.schema', []);
        $result = [];

        // Sammlungen der Module: eigener Datenspeicher je Modul (<data_path>/modules/<key>)
        $sources = array_fill_keys(array_keys($schema), [$this->store, null]);
        foreach ($this->modules?->all() ?? [] as $manifest) {
            foreach ($manifest->collections as $collection => $definition) {
                $schema[$collection] = $definition;
                $sources[$collection] = [$this->modules->store($manifest->key), $manifest->name];
            }
        }

        foreach ($schema as $collection => $definition) {
            [$store, $module] = $sources[$collection];
            $records = $store->all($collection);
            /** @var array<string, array<string, mixed>> $fields */
            $fields = $definition['fields'] ?? [];
            $known = array_merge(['id', 'created_at', 'updated_at'], array_keys($fields));

            $fieldRows = [];
            /** @var array<string, array<string, mixed>> $groups */
            $groups = (array) ($definition['groups'] ?? []);

            foreach ($fields as $name => $field) {
                $group = is_string($field['group'] ?? null) ? $field['group'] : null;
                $type = is_string($field['type'] ?? null) ? $field['type'] : 'string';
                $filled = count(array_filter($records, fn (array $r): bool => ($r[$name] ?? null) !== null && ($r[$name] ?? '') !== ''));

                $fieldRows[] = [
                    'name' => $name,
                    'label' => $field['label'] ?? $name,
                    'type' => $type,
                    'required' => ($field['required'] ?? false) === true,
                    'unique' => ($field['unique'] ?? false) === true,
                    'references' => $field['references'] ?? null,
                    'values' => $this->values($field['values'] ?? null),
                    'postgres' => self::POSTGRES_TYPES[$type] ?? 'text',
                    'filled' => $filled,
                    'group' => $group !== null ? (string) ($groups[$group]['label'] ?? $group) : null,
                    'admin_only' => $group !== null && ($groups[$group]['admin'] ?? false) === true,
                    'computed' => ($field['computed'] ?? false) === true,
                ];
            }

            $unknown = [];

            foreach ($records as $record) {
                foreach (array_keys($record) as $key) {
                    if (! in_array($key, $known, true)) {
                        $unknown[$key] = ($unknown[$key] ?? 0) + 1;
                    }
                }
            }

            $result[] = [
                'name' => $collection,
                'label' => $definition['label'] ?? $collection,
                'description' => $definition['description'] ?? null,
                'file' => $this->relative($store->filePath($collection)),
                'exists' => $store->exists($collection),
                'module' => $module,
                'count' => count($records),
                'fields' => $fieldRows,
                'unknown_fields' => $unknown,
            ];
        }

        return $result;
    }

    /**
     * Gespeicherte Sammlungen, die im Schema fehlen (müssen nachgeführt werden).
     *
     * @return list<string>
     */
    public function unknownCollections(): array
    {
        return array_values(array_diff($this->store->collections(), array_keys((array) config('intranet.schema', []))));
    }

    /**
     * @return list<string>
     */
    private function values(mixed $values): array
    {
        if (is_string($values)) {
            $values = config("intranet.{$values}", []);
        }

        return is_array($values) ? array_map('strval', array_keys($values)) : [];
    }

    private function relative(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
