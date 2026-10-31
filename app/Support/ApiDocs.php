<?php

namespace App\Support;

use App\Data\Store\JsonStore;
use App\Plugins\PluginManager;
use Throwable;

/**
 * Aufgezeichnete API-Dokumentationen (API-Recorder, Sammlung api_docs) und ihr Abgleich mit Plugins.
 *
 * Eine Dokumentation kann einem Plugin zugewiesen werden. Dann wird je Endpunkt geprüft, ob das Plugin
 * ihn bereits kennt (gleiche Methode und gleicher Pfad im Manifest). Für fehlende Endpunkte entsteht ein
 * Entwurf im Format von plugin.json → service.endpoints (docs/KONZEPT-API-ZU-PLUGIN.md).
 */
final class ApiDocs
{
    public const COLLECTION = 'api_docs';

    public function __construct(private readonly JsonStore $store, private readonly PluginManager $plugins) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $docs = $this->store->all(self::COLLECTION);
        usort($docs, fn (array $a, array $b): int => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));

        return $docs;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return $this->store->find(self::COLLECTION, $id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forPlugin(string $pluginId): array
    {
        return array_values(array_filter($this->all(), fn (array $d): bool => ($d['plugin_id'] ?? null) === $pluginId));
    }

    /**
     * Installierte Plugins mit ihren Endpunkten (für Auswahl und Abgleich im Browser).
     *
     * @return list<array{id: string, name: string, endpoints: list<array{id: string, method: string, path: string, label: string}>}>
     */
    public function pluginCatalog(): array
    {
        $catalog = [];
        foreach (array_keys($this->plugins->all()) as $id) {
            try {
                $manifest = $this->plugins->manifest((string) $id);
            } catch (Throwable) {
                continue;
            }
            $catalog[] = [
                'id' => (string) $id,
                'name' => $manifest->name(),
                'endpoints' => array_map(fn (array $e): array => [
                    'id' => (string) ($e['id'] ?? ''),
                    'method' => strtoupper((string) ($e['method'] ?? 'GET')),
                    'path' => (string) ($e['path'] ?? ''),
                    'label' => (string) ($e['label'] ?? ''),
                ], $manifest->endpoints()),
            ];
        }

        return $catalog;
    }

    public static function pluginExists(string $id): bool
    {
        return array_key_exists($id, app(PluginManager::class)->all());
    }

    /** Pfad für den Vergleich: Platzhalter vereinheitlichen, Schrägstrich am Ende weg, Kleinschreibung. */
    public static function normalize(string $method, string $path): string
    {
        $path = (string) preg_replace('/\{[^}]*\}/', '{}', strtolower(rtrim($path, '/')));

        return strtoupper($method).' '.($path === '' ? '/' : $path);
    }

    /**
     * Abgleich: je Endpunkt der Dokumentation die passende Plugin-Endpunkt-ID oder null (fehlt).
     *
     * @param  array<string, mixed>  $doc
     * @return list<array{key: string, method: string, path: string, summary: string, endpoint: string|null}>
     */
    public function compare(array $doc): array
    {
        $pluginId = $doc['plugin_id'] ?? null;
        $known = [];
        if (is_string($pluginId) && self::pluginExists($pluginId)) {
            foreach ($this->plugins->manifest($pluginId)->endpoints() as $e) {
                $known[self::normalize((string) ($e['method'] ?? 'GET'), (string) ($e['path'] ?? ''))] = (string) ($e['id'] ?? '');
            }
        }

        return array_map(function (mixed $op) use ($known): array {
            $op = is_array($op) ? $op : [];
            $method = strtoupper((string) ($op['method'] ?? 'GET'));
            $path = (string) ($op['path'] ?? '');

            return [
                'key' => (string) ($op['key'] ?? ''),
                'method' => $method,
                'path' => $path,
                'summary' => (string) ($op['summary'] ?? ''),
                'endpoint' => $known[self::normalize($method, $path)] ?? null,
            ];
        }, array_values((array) ($doc['operations'] ?? [])));
    }

    /**
     * Entwurf für plugin.json → service.endpoints mit den Endpunkten, die dem Plugin noch fehlen.
     * Status immer «partial» (aus Aufzeichnung, nicht aus der Hersteller-Doku bestätigt).
     *
     * @param  array<string, mixed>  $doc
     * @return list<array<string, mixed>>
     */
    public function endpointDraft(array $doc): array
    {
        $operations = [];
        foreach ((array) ($doc['operations'] ?? []) as $op) {
            if (is_array($op)) {
                $operations[(string) ($op['key'] ?? '')] = $op;
            }
        }
        $draft = [];
        $used = [];
        foreach ($this->compare($doc) as $row) {
            if ($row['endpoint'] !== null) {
                continue;
            }
            $op = $operations[$row['key']] ?? [];
            $id = self::endpointId($row['method'], $row['path'], $used);
            $params = [];
            preg_match_all('/\{([^}]+)\}/', $row['path'], $m);
            foreach ($m[1] as $name) {
                $params[] = ['name' => $name, 'required' => true];
            }
            foreach (array_keys((array) ($op['query'] ?? [])) as $name) {
                $params[] = ['name' => (string) $name];
            }
            $draft[] = array_filter([
                'id' => $id,
                'method' => $row['method'],
                'path' => $row['path'],
                'label' => $row['summary'] !== '' ? $row['summary'] : $row['method'].' '.$row['path'],
                'status' => 'partial',
                'use' => 'API-Tester',
                'note' => 'Aus dem API-Recorder («'.($doc['title'] ?? '').'»); Schema aus Beispielen abgeleitet, mit der Hersteller-Doku prüfen.',
                'params' => $params === [] ? null : $params,
            ], fn (mixed $v): bool => $v !== null);
        }

        return $draft;
    }

    /**
     * Aufzeichnung als OpenAPI 3.1 (Struktur wie im Browser, resources/js/lib/api-recorder/openapi.ts),
     * Grundlage für den Generator intranet:plugin-from-openapi.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    public static function toOpenApi(array $doc): array
    {
        $paths = [];
        foreach ((array) ($doc['operations'] ?? []) as $op) {
            if (! is_array($op)) {
                continue;
            }
            $path = (string) ($op['path'] ?? '/');
            $params = [];
            preg_match_all('/\{([^}]+)\}/', $path, $m);
            foreach ($m[1] as $name) {
                $params[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
            }
            foreach (array_keys((array) ($op['query'] ?? [])) as $name) {
                $params[] = ['name' => (string) $name, 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']];
            }
            $status = (string) ((int) ($op['status'] ?? 0) ?: 200);
            $response = ['description' => 'OK'];
            if (is_array($op['responseSchema'] ?? null) && is_string($op['responseType'] ?? null)) {
                $response['content'] = [$op['responseType'] => ['schema' => $op['responseSchema']]];
            }
            $operation = array_filter([
                'summary' => (string) ($op['summary'] ?? '') ?: null,
                'tags' => ($op['tag'] ?? '') !== '' ? [(string) $op['tag']] : null,
                'parameters' => $params ?: null,
                'responses' => [$status => $response],
            ], fn (mixed $v): bool => $v !== null);
            if (is_array($op['requestSchema'] ?? null) && is_string($op['requestType'] ?? null)) {
                $operation['requestBody'] = ['content' => [$op['requestType'] => ['schema' => $op['requestSchema']]]];
            }
            $paths[$path][strtolower((string) ($op['method'] ?? 'get'))] = $operation;
        }

        return [
            'openapi' => '3.1.0',
            'info' => array_filter(['title' => (string) ($doc['title'] ?? 'API'), 'version' => '1.0', 'description' => (string) ($doc['description'] ?? '') ?: null]),
            'servers' => ($doc['base_url'] ?? '') !== '' ? [['url' => (string) $doc['base_url']]] : [],
            'paths' => $paths,
        ];
    }

    /**
     * Endpunkt-ID aus dem Pfad (api/, rest/ und v1/ weggelassen, Platzhalter → «one»), eindeutig innerhalb $used.
     *
     * @param  array<string, true>  $used
     */
    public static function endpointId(string $method, string $path, array &$used): string
    {
        $parts = array_values(array_filter(explode('/', (string) preg_replace('/\{[^}]*\}/', 'one', strtolower($path))), fn (string $p): bool => $p !== '' && preg_match('/^(api|rest|v\d+)$/', $p) !== 1));
        $base = (string) preg_replace('/[^a-z0-9]+/', '-', implode('-', $parts));
        $base = trim(($method === 'GET' ? '' : strtolower($method).'-').$base, '-') ?: 'endpoint';
        $id = $base;
        for ($i = 2; isset($used[$id]); $i++) {
            $id = "{$base}-{$i}";
        }
        $used[$id] = true;

        return $id;
    }
}
