<?php

namespace Tests\Feature;

use App\Data\SchemaInspector;
use Tests\TestCase;

/**
 * Datenstrukturen immer nachführen: Jede Sammlung, die der Code verwendet, und jedes
 * gespeicherte Feld muss im Schema (config/intranet.php → schema) beschrieben sein.
 */
class SchemaCompletenessTest extends TestCase
{
    public function test_every_collection_used_in_code_is_in_the_schema(): void
    {
        $schema = array_keys((array) config('intranet.schema'));
        $found = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            preg_match_all("/(?:COLLECTION|STATES|RUNS)\\s*=\\s*'([a-z_]+)'|protected string \\\$collection = '([a-z_]+)'/", $code, $matches);
            foreach (array_filter([...$matches[1], ...$matches[2]]) as $name) {
                $found[$name] = $file->getFilename();
            }
        }

        $this->assertNotEmpty($found);
        foreach ($found as $name => $where) {
            $this->assertContains($name, $schema, "Sammlung «{$name}» ({$where}) fehlt in config/intranet.php → schema");
        }
    }

    public function test_demo_data_matches_the_schema(): void
    {
        $inspector = app(SchemaInspector::class);

        $this->assertSame([], $inspector->unknownCollections());
        foreach ($inspector->inspect() as $collection) {
            $this->assertSame([], $collection['unknown_fields'], "Sammlung {$collection['name']} hat Felder ausserhalb des Schemas");
        }
    }

    public function test_every_schema_field_has_a_label_and_valid_type(): void
    {
        $types = ['uuid', 'string', 'text', 'email', 'url', 'bool', 'int', 'date', 'datetime', 'enum', 'ref', 'json'];

        foreach ((array) config('intranet.schema') as $name => $collection) {
            $this->assertNotEmpty($collection['label'] ?? null, "{$name}: label fehlt");
            foreach ((array) $collection['fields'] as $field => $definition) {
                $this->assertNotEmpty($definition['label'] ?? null, "{$name}.{$field}: label fehlt");
                $this->assertContains($definition['type'] ?? null, $types, "{$name}.{$field}: unbekannter Typ");
                if (($definition['type'] ?? null) === 'ref') {
                    $this->assertArrayHasKey((string) ($definition['references'] ?? ''), (array) config('intranet.schema'), "{$name}.{$field}: Verweis ins Leere");
                }
                if (isset($definition['group'])) {
                    $this->assertArrayHasKey($definition['group'], (array) ($collection['groups'] ?? []), "{$name}.{$field}: unbekannte Gruppe");
                }
            }
        }
    }
}
