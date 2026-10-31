<?php

namespace App\Plugins;

use App\Support\ApiDocs;
use InvalidArgumentException;

/**
 * Erzeugt aus einer OpenAPI-Beschreibung Plugin-Endpunkte (docs/KONZEPT-API-ZU-PLUGIN.md):
 * ergänzt ein bestehendes plugin.json um fehlende Endpunkte oder legt ein neues Plugin an.
 *
 * Grundsätze: Vorhandenes wird nie überschrieben, alle neuen Endpunkte sind «partial»,
 * Zugangsdaten nur als password-Einstellungen, neue Plugins sind nach der Installation aus.
 */
final class PluginGenerator
{
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * OpenAPI → Bausteine eines Manifests.
     *
     * @param  array<string, mixed>  $spec
     * @return array{title: string, description: string, base_url: string, auth: array<string, mixed>, auth_settings: list<array<string, mixed>>, endpoints: list<array<string, mixed>>}
     */
    public static function describe(array $spec): array
    {
        if (! isset($spec['openapi']) && ! isset($spec['swagger'])) {
            throw new InvalidArgumentException('Keine OpenAPI-Beschreibung (Feld «openapi» fehlt).');
        }
        $info = is_array($spec['info'] ?? null) ? $spec['info'] : [];
        $title = trim((string) ($info['title'] ?? 'API')) ?: 'API';
        $servers = is_array($spec['servers'] ?? null) ? $spec['servers'] : [];
        $baseUrl = is_array($servers[0] ?? null) ? (string) ($servers[0]['url'] ?? '') : '';

        [$auth, $authSettings] = self::auth($spec);

        $endpoints = [];
        $used = [];
        foreach ((array) ($spec['paths'] ?? []) as $path => $item) {
            if (! is_array($item)) {
                continue;
            }
            $shared = is_array($item['parameters'] ?? null) ? $item['parameters'] : [];
            foreach (self::METHODS as $method) {
                $op = $item[$method] ?? null;
                if (! is_array($op)) {
                    continue;
                }
                $endpoints[] = self::endpoint(strtoupper($method), (string) $path, $op, $shared, $title, $used);
            }
        }

        return [
            'title' => $title,
            'description' => trim((string) ($info['description'] ?? '')),
            'base_url' => $baseUrl,
            'auth' => $auth,
            'auth_settings' => $authSettings,
            'endpoints' => $endpoints,
        ];
    }

    /**
     * Fehlende Endpunkte an ein bestehendes Manifest anhängen (gleiche Methode + Pfad = vorhanden).
     *
     * @param  array<string, mixed>  $manifest
     * @param  list<array<string, mixed>>  $endpoints
     * @param  string|null  $host  Schlüssel aus service.hosts für alle neuen Endpunkte (weitere Adresse des Herstellers)
     * @return array{manifest: array<string, mixed>, added: list<array<string, mixed>>, skipped: list<string>}
     */
    public static function merge(array $manifest, array $endpoints, ?string $host = null): array
    {
        $key = fn (array $e): string => ((string) ($e['host'] ?? PluginManifest::DEFAULT_HOST)).' '.ApiDocs::normalize((string) ($e['method'] ?? 'GET'), (string) ($e['path'] ?? ''));
        $service = is_array($manifest['service'] ?? null) ? $manifest['service'] : [];
        $existing = is_array($service['endpoints'] ?? null) ? array_values(array_filter($service['endpoints'], 'is_array')) : [];

        $known = [];
        $used = [];
        foreach ($existing as $e) {
            $known[$key($e)] = (string) ($e['id'] ?? '');
            $used[(string) ($e['id'] ?? '')] = true;
        }

        $added = [];
        $skipped = [];
        foreach ($endpoints as $endpoint) {
            if ($host !== null && $host !== PluginManifest::DEFAULT_HOST) {
                // «host» direkt nach «path», damit das Manifest lesbar bleibt
                $endpoint = array_slice($endpoint, 0, 3, true) + ['host' => $host] + $endpoint;
            }
            $k = $key($endpoint);
            $shown = trim((string) preg_replace('/^'.preg_quote(PluginManifest::DEFAULT_HOST, '/').' /', '', $k));
            if (isset($known[$k])) {
                $skipped[] = "{$shown} (vorhanden als «{$known[$k]}»)";

                continue;
            }
            // ID neu vergeben, falls sie im Manifest schon belegt ist
            $base = (string) $endpoint['id'];
            $id = $base;
            for ($i = 2; isset($used[$id]); $i++) {
                $id = "{$base}-{$i}";
            }
            $used[$id] = true;
            $known[$k] = $id;
            $endpoint['id'] = $id;
            $existing[] = $endpoint;
            $added[] = $endpoint;
        }

        $service['endpoints'] = $existing;
        $manifest['service'] = $service;

        return ['manifest' => $manifest, 'added' => $added, 'skipped' => $skipped];
    }

    /**
     * Dateien für ein neues Plugin plugins/<Ordner>/.
     *
     * @param  array{title: string, description: string, base_url: string, auth: array<string, mixed>, auth_settings: list<array<string, mixed>>, endpoints: list<array<string, mixed>>}  $described
     * @return array{manifest: array<string, mixed>, files: array<string, string>}
     */
    public static function scaffold(string $folder, array $described): array
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]{1,40}$/', $folder) !== 1) {
            throw new InvalidArgumentException('Ordnername: mit Grossbuchstaben beginnen, nur Buchstaben und Ziffern (z. B. «EscolaWeb»).');
        }
        $id = strtolower($folder);
        $hasAuth = ($described['auth']['type'] ?? 'none') !== 'none';

        $manifest = [
            'id' => $id,
            'name' => $described['title'],
            'version' => '0.1',
            'api' => PluginManifest::API_VERSION,
            'category' => 'other',
            'author' => 'Generiert mit intranet:plugin-from-openapi',
            'description' => ($described['description'] !== '' ? $described['description'].' ' : '')
                .'Entwurf aus einer OpenAPI-Beschreibung – Endpunkte mit der Hersteller-Doku prüfen.',
            'service' => [
                'name' => $described['title'],
                'auth' => $described['auth'],
                'auth_note' => $hasAuth
                    ? 'Anmeldung aus der OpenAPI-Beschreibung übernommen; Zugangsdaten werden verschlüsselt gespeichert.'
                    : 'Anmeldung nicht erkannt (Aufzeichnungen enthalten keine Zugangsdaten): service.auth von Hand ergänzen.',
                'endpoints' => $described['endpoints'],
            ],
            'settings' => array_merge([[
                'key' => 'base_url',
                'type' => 'url',
                'label' => 'Adresse von '.$described['title'],
                'required' => true,
                'placeholder' => $described['base_url'] !== '' ? $described['base_url'] : 'https://',
            ]], $described['auth_settings']),
        ];

        PluginManifest::fromArray($manifest, $folder);

        $php = <<<PHP
        <?php

        namespace Plugins\\{$folder};

        use App\\Plugins\\BasePlugin;

        /**
         * {$described['title']} – generiert mit intranet:plugin-from-openapi.
         *
         * «Testen» ruft den ersten GET-Endpunkt ohne Platzhalter auf (BasePlugin::test()).
         * Aktionen, Kacheln und Ereignisse hier ergänzen (siehe PLUGINS.md); schreibende
         * Aufrufe nur über \$ctx->call() – sie verlangen die Freigabe «allow_write».
         */
        class Plugin extends BasePlugin {}

        PHP;

        $endpointLines = implode("\n", array_map(
            fn (array $e): string => "| `{$e['method']}` | `{$e['path']}` | `{$e['id']}` | {$e['label']} |",
            $described['endpoints'],
        ));
        $readme = <<<MD
        # {$described['title']} (Plugin-Entwurf)

        Generiert mit `php artisan intranet:plugin-from-openapi`. Alle Endpunkte haben Status `partial`.

        ## Vor dem Einsatz

        1. Endpunkte mit der Hersteller-Dokumentation abgleichen, `permission` und `use` ergänzen, dann `status: full`.
        2. Anmeldung (`service.auth`) prüfen; Zugangsdaten nur als `password`-Einstellung.
        3. `data_notice` ergänzen, falls Personendaten betroffen sind (IDG Kanton Zürich, ADR-005).
        4. Aktionen in `Plugin.php` und Tests mit erfundenen Daten (HTTP-Fakes) schreiben.
        5. Pull-Request; nach dem Deployment im Intranet einrichten und einschalten.

        ## Endpunkte

        | Methode | Pfad | ID | Beschreibung |
        | --- | --- | --- | --- |
        {$endpointLines}

        MD;

        return [
            'manifest' => $manifest,
            'files' => [
                'plugin.json' => self::encode($manifest),
                'Plugin.php' => $php,
                'README.md' => $readme,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @param  array<string, mixed>  $op
     * @param  array<mixed>  $shared
     * @param  array<string, true>  $used
     * @return array<string, mixed>
     */
    private static function endpoint(string $method, string $path, array $op, array $shared, string $title, array &$used): array
    {
        $params = [];
        $seen = [];
        foreach (array_merge($shared, is_array($op['parameters'] ?? null) ? $op['parameters'] : []) as $p) {
            if (! is_array($p) || ! in_array($p['in'] ?? '', ['path', 'query'], true) || ! isset($p['name'])) {
                continue;
            }
            $name = (string) $p['name'];
            $schema = is_array($p['schema'] ?? null) ? $p['schema'] : [];
            $seen[$name] = true;
            $params[$name] = array_filter([
                'name' => $name,
                'required' => ($p['in'] === 'path' || ($p['required'] ?? false) === true) ? true : null,
                'default' => isset($schema['default']) && is_scalar($schema['default']) ? (string) $schema['default'] : null,
                'example' => isset($p['example']) && is_scalar($p['example']) ? (string) $p['example'] : null,
                'help' => isset($p['description']) && is_string($p['description']) ? mb_substr($p['description'], 0, 200) : null,
            ], fn (mixed $v): bool => $v !== null);
        }
        preg_match_all('/\{([^}]+)\}/', $path, $m);
        foreach ($m[1] as $name) {
            if (! isset($seen[$name])) {
                $params[$name] = ['name' => $name, 'required' => true];
            }
        }

        $summary = trim((string) ($op['summary'] ?? ''));
        $label = $summary !== '' ? $summary : (is_string($op['operationId'] ?? null) ? $op['operationId'] : "{$method} {$path}");

        return array_filter([
            'id' => ApiDocs::endpointId($method, $path, $used),
            'method' => $method,
            'path' => $path,
            'label' => mb_substr($label, 0, 120),
            'status' => 'partial',
            'use' => 'API-Tester',
            'note' => "Aus OpenAPI («{$title}») übernommen – mit der Hersteller-Doku prüfen.",
            'danger' => $method !== 'GET' ? true : null,
            'params' => $params === [] ? null : array_values($params),
        ], fn (mixed $v): bool => $v !== null);
    }

    /**
     * Erstes Sicherheitsschema → service.auth und nötige Einstellungen.
     *
     * @param  array<string, mixed>  $spec
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private static function auth(array $spec): array
    {
        $components = is_array($spec['components'] ?? null) ? $spec['components'] : [];
        $schemes = is_array($components['securitySchemes'] ?? null) ? $components['securitySchemes'] : [];
        $scheme = null;
        foreach ($schemes as $candidate) {
            if (is_array($candidate)) {
                $scheme = $candidate;
                break;
            }
        }
        $secret = fn (string $key, string $label): array => ['key' => $key, 'type' => 'password', 'label' => $label, 'required' => true];
        $text = fn (string $key, string $label): array => ['key' => $key, 'type' => 'text', 'label' => $label, 'required' => true];

        if ($scheme === null) {
            return [['type' => 'none'], []];
        }
        $type = (string) ($scheme['type'] ?? '');
        $httpScheme = strtolower((string) ($scheme['scheme'] ?? ''));

        if ($type === 'http' && $httpScheme === 'bearer') {
            return [['type' => 'bearer', 'token_setting' => 'api_token'], [$secret('api_token', 'API-Token')]];
        }
        if ($type === 'http' && $httpScheme === 'basic') {
            return [
                ['type' => 'basic', 'username_setting' => 'username', 'password_setting' => 'password'],
                [$text('username', 'Benutzername'), $secret('password', 'Passwort')],
            ];
        }
        if ($type === 'apiKey' && ($scheme['in'] ?? '') === 'header') {
            return [
                ['type' => 'header', 'header' => (string) ($scheme['name'] ?? 'Authorization'), 'setting' => 'api_key'],
                [$secret('api_key', 'API-Schlüssel')],
            ];
        }
        $flows = is_array($scheme['flows'] ?? null) ? $scheme['flows'] : [];
        if ($type === 'oauth2' && is_array($flows['clientCredentials'] ?? null)) {
            return [
                [
                    'type' => 'oauth2_client_credentials',
                    'token_url' => (string) ($flows['clientCredentials']['tokenUrl'] ?? ''),
                    'client_id_setting' => 'client_id',
                    'client_secret_setting' => 'client_secret',
                ],
                [$text('client_id', 'Client-ID'), $secret('client_secret', 'Client-Secret')],
            ];
        }

        return [['type' => 'none'], []];
    }
}
