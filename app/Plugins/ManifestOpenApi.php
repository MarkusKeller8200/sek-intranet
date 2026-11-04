<?php

namespace App\Plugins;

/**
 * Endpunkte eines Plugin-Manifests als OpenAPI 3.1 (für die API-Ansicht mit Scalar).
 * Enthält nur die Beschreibung aus plugin.json, nie Einstellungswerte oder Zugangsdaten.
 */
final class ManifestOpenApi
{
    public const STATUS_LABELS = ['full' => 'Vollständig', 'partial' => 'Teilweise geprüft', 'planned' => 'Geplant'];

    /**
     * @return array<string, mixed>
     */
    public static function build(PluginManifest $manifest): array
    {
        $service = $manifest->service();
        $server = self::serverPlaceholder($manifest);

        $paths = [];
        foreach ($manifest->endpoints() as $endpoint) {
            $method = strtolower((string) ($endpoint['method'] ?? 'GET'));
            $path = (string) $endpoint['path'];
            $status = (string) ($endpoint['status'] ?? 'full');

            $description = array_filter([
                isset($endpoint['use']) ? 'Verwendung: '.$endpoint['use'] : null,
                isset($endpoint['permission']) ? 'Berechtigung: '.$endpoint['permission'] : null,
                isset($endpoint['note']) ? (string) $endpoint['note'] : null,
                'Status: '.(self::STATUS_LABELS[$status] ?? $status),
            ]);

            $host = (string) ($endpoint['host'] ?? PluginManifest::DEFAULT_HOST);
            $hostUrl = $manifest->hosts()[$host] ?? null;
            $paths[$path][$method] = array_filter([
                'operationId' => (string) $endpoint['id'],
                'summary' => (string) ($endpoint['label'] ?? $endpoint['id']),
                'description' => implode("\n\n", $description),
                'tags' => [self::STATUS_LABELS[$status] ?? $status],
                'deprecated' => $status === 'planned' ? true : null,
                'parameters' => self::parameters($path, $endpoint),
                'requestBody' => self::requestBody($endpoint),
                'responses' => ['200' => ['description' => 'OK']],
                'servers' => $hostUrl !== null ? [['url' => $hostUrl]] : null,
            ], fn (mixed $v): bool => $v !== null && $v !== []);
            // Anmeldung nur, wo sie gilt (service.auth.hosts)
            if (! $manifest->authAppliesTo($host)) {
                $paths[$path][$method]['security'] = [];
            }
        }

        $spec = [
            'openapi' => '3.1.0',
            'info' => array_filter([
                'title' => (string) ($service['name'] ?? $manifest->name()),
                'version' => (string) ($manifest->data['version'] ?? '1.0'),
                'description' => trim(($manifest->data['description'] ?? '')."\n\n".($service['auth_note'] ?? '')),
            ]),
            'servers' => [['url' => $server, 'description' => 'Beispieladresse – die echte steht in den Plugin-Einstellungen']],
            'paths' => $paths === [] ? new \stdClass : $paths,
        ];
        if (isset($service['docs'])) {
            $spec['externalDocs'] = ['url' => (string) $service['docs'], 'description' => 'Hersteller-Dokumentation'];
        }

        $scheme = self::securityScheme($manifest->auth());
        if ($scheme !== null) {
            $spec['components'] = ['securitySchemes' => ['plugin' => $scheme]];
            $spec['security'] = [['plugin' => []]];
        }

        return $spec;
    }

    /**
     * Body mit Beispiel (endpoints[].body) und Feldern (endpoints[].fields) – Scalar füllt «Test Request» damit vor.
     *
     * @param  array<string, mixed>  $endpoint
     * @return array<string, mixed>|null
     */
    private static function requestBody(array $endpoint): ?array
    {
        $fields = is_array($endpoint['fields'] ?? null) ? $endpoint['fields'] : [];
        if (! is_array($endpoint['body'] ?? null) && $fields === []) {
            return null;
        }

        $properties = [];
        $required = [];
        foreach ($fields as $field) {
            if (! is_array($field) || ! is_string($field['name'] ?? null)) {
                continue;
            }
            $properties[$field['name']] = array_filter([
                'type' => is_string($field['type'] ?? null) ? $field['type'] : 'string',
                'description' => is_string($field['help'] ?? null) ? $field['help'] : null,
                'enum' => is_array($field['choices'] ?? null) ? array_values($field['choices']) : null,
                'example' => $field['example'] ?? null,
            ], fn (mixed $v): bool => $v !== null);
            if (($field['required'] ?? false) === true) {
                $required[] = $field['name'];
            }
        }

        $schema = array_filter([
            'type' => 'object',
            'properties' => $properties === [] ? null : $properties,
            'required' => $required === [] ? null : $required,
        ], fn (mixed $v): bool => $v !== null);
        $body = is_array($endpoint['body'] ?? null) ? $endpoint['body'] : null;

        return ['content' => ['application/json' => array_filter([
            'schema' => $schema,
            'example' => $body === [] ? new \stdClass : $body,
        ], fn (mixed $v): bool => $v !== null)]];
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @return list<array<string, mixed>>
     */
    private static function parameters(string $path, array $endpoint): array
    {
        $params = [];
        $seen = [];
        $definitions = is_array($endpoint['params'] ?? null) ? $endpoint['params'] : [];
        foreach ($definitions as $param) {
            if (! is_array($param) || ! isset($param['name'])) {
                continue;
            }
            $name = (string) $param['name'];
            $inPath = str_contains($path, '{'.$name.'}');
            $seen[$name] = true;
            $params[] = array_filter([
                'name' => $name,
                'in' => $inPath ? 'path' : 'query',
                'required' => $inPath || ($param['required'] ?? false) === true,
                'description' => isset($param['help']) ? (string) $param['help'] : null,
                'schema' => array_filter(['type' => 'string', 'default' => isset($param['default']) ? (string) $param['default'] : null]),
                'example' => isset($param['example']) ? (string) $param['example'] : null,
            ], fn (mixed $v): bool => $v !== null);
        }
        preg_match_all('/\{([^}]+)\}/', $path, $m);
        foreach ($m[1] as $name) {
            if (! isset($seen[$name])) {
                $params[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
            }
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $auth
     * @return array<string, mixed>|null
     */
    private static function securityScheme(array $auth): ?array
    {
        return match ($auth['type'] ?? 'none') {
            'bearer' => ['type' => 'http', 'scheme' => 'bearer'],
            'basic' => ['type' => 'http', 'scheme' => 'basic'],
            'header' => ['type' => 'apiKey', 'in' => 'header', 'name' => (string) ($auth['header'] ?? 'Authorization')],
            'oauth2_client_credentials' => ['type' => 'oauth2', 'flows' => ['clientCredentials' => [
                'tokenUrl' => (string) ($auth['token_url'] ?? '/oauth/token'),
                'scopes' => new \stdClass,
            ]]],
            'oauth2_password' => ['type' => 'oauth2', 'flows' => ['password' => [
                'tokenUrl' => (string) ($auth['token_url'] ?? '/oauth/token'),
                'scopes' => new \stdClass,
            ]]],
            default => null,
        };
    }

    private static function serverPlaceholder(PluginManifest $manifest): string
    {
        if (is_string($manifest->service()['base'] ?? null)) {
            return (string) $manifest->service()['base'];
        }
        foreach ($manifest->settings() as $setting) {
            if (($setting['key'] ?? '') === 'base_url' && is_string($setting['placeholder'] ?? null)) {
                return $setting['placeholder'];
            }
        }

        return 'https://example.org';
    }
}
