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
     * OpenAPI → Bausteine eines Manifests. Akzeptiert auch die Plugin-Beschreibung einer API aus EduKSX-Connectors
     * (GET /plugin.json, sek-intranet#229).
     *
     * @param  array<string, mixed>  $spec
     * @return array{title: string, description: string, base_url: string, auth: array<string, mixed>, auth_settings: list<array<string, mixed>>, endpoints: list<array<string, mixed>>, connector?: array<string, mixed>}
     */
    public static function describe(array $spec): array
    {
        if (self::isConnectorManifest($spec)) {
            return self::describeConnector($spec);
        }
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
     * Plugin-Beschreibung aus EduKSX-Connectors? (GET /plugin.json einer Connector-API, Format wie plugin.json mit «x-eduksx»)
     *
     * @param  array<string, mixed>  $data
     */
    public static function isConnectorManifest(array $data): bool
    {
        $service = $data['service'] ?? null;

        return ($data['api'] ?? null) === PluginManifest::API_VERSION
            && is_array($data['x-eduksx'] ?? null)
            && is_array($service) && is_array($service['endpoints'] ?? null);
    }

    /**
     * Connector-Beschreibung → Bausteine eines Manifests. Beschriftungen, Beispielwerte, Felder, «danger» und Status
     * kommen aus dem Code der Connector-API (Entscheid EduKSX-Connectors#42) und werden übernommen.
     * «probelauf» wird zum Parameter mit Vorgabe «true», damit der API-Tester zuerst nur den Plan zeigt.
     *
     * @param  array<string, mixed>  $m
     * @return array{title: string, description: string, base_url: string, auth: array<string, mixed>, auth_settings: list<array<string, mixed>>, endpoints: list<array<string, mixed>>, connector: array<string, mixed>}
     */
    public static function describeConnector(array $m): array
    {
        /** @var array<string, mixed> $service */
        $service = $m['service'];
        /** @var array<string, mixed> $x */
        $x = $m['x-eduksx'];
        $api = (string) ($x['api'] ?? $m['id'] ?? 'connector');
        $version = (string) ($m['version'] ?? '');
        $keep = ['id', 'method', 'path', 'label', 'status', 'use', 'permission', 'note', 'danger', 'effect', 'params', 'body', 'fields', 'file', 'group'];

        $endpoints = [];
        $used = [];
        foreach ((array) $service['endpoints'] as $e) {
            if (! is_array($e) || ! is_string($e['path'] ?? null)) {
                continue;
            }
            $endpoint = array_intersect_key($e, array_flip($keep));
            $endpoint['method'] = strtoupper((string) ($e['method'] ?? 'GET'));
            $eid = $e['id'] ?? null;
            $id = is_string($eid) && $eid !== '' && ! isset($used[$eid]) ? $eid : ApiDocs::endpointId($endpoint['method'], $e['path'], $used);
            $used[$id] = true;
            $endpoint['id'] = $id;
            $endpoint['status'] = in_array($e['status'] ?? null, PluginManifest::ENDPOINT_STATUS, true) ? $e['status'] : 'partial';
            $notes = array_filter([is_string($e['note'] ?? null) ? $e['note'] : null]);
            if (($e['probelauf'] ?? false) === true) {
                $params = is_array($endpoint['params'] ?? null) ? $endpoint['params'] : [];
                $params[] = ['name' => 'probelauf', 'default' => 'true', 'choices' => ['true', 'false'],
                    'help' => 'Probelauf: zeigt nur den Plan und ändert nichts. «false» schreibt (Schreibfreigabe der API nötig).'];
                $endpoint['params'] = $params;
            }
            if (is_array($e['form'] ?? null)) {
                $names = array_map(fn (mixed $f): string => is_array($f) ? (string) ($f['name'] ?? '') : '', $e['form']);
                $notes[] = 'Formularfelder: '.implode(', ', array_filter($names)).'. Der API-Tester sendet nur die Datei; '
                    .'alle Felder in der Testplattform von EduKSX-Connectors.';
            }
            $notes[] = "Aus EduKSX-Connectors ({$api} {$version}).";
            $endpoint['note'] = implode("\n", $notes);
            $endpoints[] = $endpoint;
        }

        $settings = array_values(array_filter((array) ($m['settings'] ?? []), fn (mixed $s): bool => is_array($s) && ($s['key'] ?? '') !== 'base_url'));
        $example = '';
        foreach ((array) ($m['settings'] ?? []) as $s) {
            if (is_array($s) && ($s['key'] ?? '') === 'base_url') {
                $example = (string) ($s['example'] ?? '');
            }
        }
        $auth = is_array($service['auth'] ?? null) ? $service['auth'] : ['type' => 'header', 'header' => 'X-API-Key', 'setting' => 'api_key'];

        return [
            'title' => (string) ($m['name'] ?? $api),
            'description' => (string) ($m['description'] ?? ''),
            'base_url' => (string) ($service['base'] ?? $example),
            'auth' => $auth,
            'auth_settings' => $settings,
            'endpoints' => $endpoints,
            'connector' => [
                'api' => $api,
                'id' => (string) ($m['id'] ?? 'eduksx-'.$api),
                'version' => $version,
                'category' => in_array($m['category'] ?? null, ['identity', 'devices', 'inventory', 'school', 'other'], true) ? $m['category'] : 'other',
                'data_notice' => is_string($m['data_notice'] ?? null) ? $m['data_notice'] : null,
                'auth_note' => is_string($service['auth_note'] ?? null) ? $service['auth_note'] : null,
                'sitzung' => is_array($x['sitzung'] ?? null) ? $x['sitzung'] : null,
            ],
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
     * @param  array{title: string, description: string, base_url: string, auth: array<string, mixed>, auth_settings: list<array<string, mixed>>, endpoints: list<array<string, mixed>>, connector?: array<string, mixed>}  $described
     * @return array{manifest: array<string, mixed>, files: array<string, string>}
     */
    public static function scaffold(string $folder, array $described): array
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]{1,40}$/', $folder) !== 1) {
            throw new InvalidArgumentException('Ordnername: mit Grossbuchstaben beginnen, nur Buchstaben und Ziffern (z. B. «EscolaWeb»).');
        }
        $id = strtolower($folder);
        $hasAuth = ($described['auth']['type'] ?? 'none') !== 'none';
        $connector = $described['connector'] ?? null;
        if (is_array($connector)) {
            return self::scaffoldConnector($folder, $described, $connector);
        }

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
     * Neues Plugin aus der Beschreibung einer Connector-API (EduKSX-Connectors, sek-intranet#229).
     *
     * @param  array{title: string, description: string, base_url: string, auth: array<string, mixed>, auth_settings: list<array<string, mixed>>, endpoints: list<array<string, mixed>>}  $described
     * @param  array<string, mixed>  $connector
     * @return array{manifest: array<string, mixed>, files: array<string, string>}
     */
    private static function scaffoldConnector(string $folder, array $described, array $connector): array
    {
        $id = (string) $connector['id'];
        if (preg_match('/^[a-z][a-z0-9_-]{1,40}$/', $id) !== 1) {
            $id = strtolower($folder);
        }
        $sitzung = is_array($connector['sitzung'] ?? null) ? $connector['sitzung'] : null;
        $version = (string) ($connector['version'] ?? '');
        $api = (string) ($connector['api'] ?? '');
        $header = is_array($sitzung) ? (string) ($sitzung['header'] ?? '') : '';
        $manifest = array_filter([
            'id' => $id,
            'name' => $described['title'],
            'version' => $version,
            'api' => PluginManifest::API_VERSION,
            'category' => (string) $connector['category'],
            'author' => 'EduKSX-Connectors (übernommen mit intranet:plugin-from-openapi)',
            'description' => $described['description'],
            'data_notice' => $connector['data_notice'] ?? null,
            'service' => array_filter([
                'name' => $described['title'].' (EduKSX-Connectors)',
                'docs' => 'https://github.com/MarkusKeller8200/EduKSX-Connectors/tree/main/apis/'.rawurlencode($api),
                'auth' => $described['auth'],
                'auth_note' => trim((is_string($connector['auth_note'] ?? null) ? $connector['auth_note'] : '').($sitzung !== null
                    ? " Diese API braucht zusätzlich eine Anmeldesitzung (Kopfzeile {$header}); im Intranet noch nicht unterstützt – Aufrufe ohne Sitzung antworten mit 401."
                    : '')),
                'endpoints' => $described['endpoints'],
            ], fn (mixed $v): bool => $v !== null && $v !== ''),
            'settings' => array_merge([[
                'key' => 'base_url',
                'type' => 'url',
                'label' => 'Adresse von '.$described['title'],
                'required' => true,
                'placeholder' => 'https://',
                'help' => 'Adresse der Connector-API (lokal z. B. '.($described['base_url'] !== '' ? $described['base_url'] : 'http://127.0.0.1:8080')
                    .'); im Intranet nur https://.',
            ]], $described['auth_settings']),
        ], fn (mixed $v): bool => $v !== null && $v !== '');

        PluginManifest::fromArray($manifest, $folder);

        $php = <<<PHP
        <?php

        namespace Plugins\\{$folder};

        use App\\Plugins\\BasePlugin;

        /**
         * {$described['title']} aus EduKSX-Connectors – übernommen mit intranet:plugin-from-openapi.
         *
         * Endpunkte, Beschriftungen und Beispielwerte kommen aus GET /plugin.json der Connector-API und werden mit
         * `--plugin={$id}` aktualisiert (nur neue Endpunkte). «Testen» ruft den ersten GET-Endpunkt ohne Platzhalter
         * auf. Schreibende Aufrufe nur über \$ctx->call() – sie verlangen die Freigabe «allow_write».
         */
        class Plugin extends BasePlugin {}

        PHP;

        $endpointLines = implode("\n", array_map(
            fn (array $e): string => "| `{$e['method']}` | `{$e['path']}` | `{$e['id']}` | {$e['label']} | {$e['status']} |",
            $described['endpoints'],
        ));
        $readme = <<<MD
        # {$described['title']} (EduKSX-Connectors)

        Übernommen aus der Plugin-Beschreibung der Connector-API `apis/{$api}` (Version {$version}) mit
        `php artisan intranet:plugin-from-openapi <{$api}-plugin.json> --neu={$folder}`.

        ## Aktualisieren

        1. In EduKSX-Connectors: `python3 shared/plugin_export.py apis/{$api} > {$api}-plugin.json` (oder in der
           Testplattform «plugin.json herunterladen»).
        2. Hier: `php artisan intranet:plugin-from-openapi {$api}-plugin.json --plugin={$id}` – ergänzt nur neue
           Endpunkte und meldet eine neue Connector-Version; dann `version` in `plugin.json` nachführen.

        ## Einrichten

        Adresse der Connector-API (`https://…`) und deren API-Key (`X-API-Key`) unter Einstellungen hinterlegen.
        Schreibende Endpunkte starten im API-Tester mit `probelauf=true`.

        ## Endpunkte

        | Methode | Pfad | ID | Beschreibung | Status |
        | --- | --- | --- | --- | --- |
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
        // Ergänzungen aus EduKSX-Connectors (openapi_extra «x-eduksx»): Beschriftung, danger, effect, Status, Zweck
        $x = is_array($op['x-eduksx'] ?? null) ? $op['x-eduksx'] : [];
        foreach (array_merge($shared, is_array($op['parameters'] ?? null) ? $op['parameters'] : []) as $p) {
            if (! is_array($p) || ! in_array($p['in'] ?? '', ['path', 'query'], true) || ! isset($p['name'])) {
                continue;
            }
            $name = (string) $p['name'];
            if ($name === 'probelauf' && $x !== []) {
                // Connector-APIs: im Tester zuerst nur den Plan zeigen
                $seen[$name] = true;
                $params[$name] = ['name' => 'probelauf', 'default' => 'true', 'choices' => ['true', 'false'],
                    'help' => 'Probelauf: zeigt nur den Plan und ändert nichts.'];

                continue;
            }
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

        $summary = trim((string) ($x['label'] ?? $op['summary'] ?? ''));
        $label = $summary !== '' ? $summary : (is_string($op['operationId'] ?? null) ? $op['operationId'] : "{$method} {$path}");
        $effect = in_array($x['effect'] ?? null, ['read', 'write'], true) ? $x['effect'] : null;
        $danger = is_bool($x['danger'] ?? null) ? $x['danger'] : ($effect !== null ? $effect === 'write' : $method !== 'GET');
        $status = in_array($x['status'] ?? null, PluginManifest::ENDPOINT_STATUS, true) ? $x['status'] : 'partial';

        return array_filter([
            'id' => ApiDocs::endpointId($method, $path, $used),
            'method' => $method,
            'path' => $path,
            'label' => mb_substr($label, 0, 120),
            'status' => $status,
            'use' => is_string($x['use'] ?? null) ? $x['use'] : 'API-Tester',
            'permission' => is_string($x['permission'] ?? null) ? $x['permission'] : null,
            'effect' => $effect,
            'note' => is_string($x['note'] ?? null) ? $x['note'] : "Aus OpenAPI («{$title}») übernommen – mit der Hersteller-Doku prüfen.",
            'danger' => $danger ? true : null,
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
