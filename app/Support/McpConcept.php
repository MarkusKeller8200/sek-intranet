<?php

namespace App\Support;

/**
 * Konzept MCP-Schnittstelle (docs/KONZEPT-MCP.md): Werkzeuge werden aus der
 * OpenAPI-Beschreibung abgeleitet, damit API und MCP nie auseinanderlaufen.
 */
class McpConcept
{
    /** Werkzeugnamen je Operation (Methode + Pfad). */
    private const NAMES = [
        'get /me' => 'whoami',
        'get /people' => 'search_people',
        'post /people' => 'create_person',
        'get /people/{id}' => 'get_person',
        'patch /people/{id}' => 'update_person',
        'delete /people/{id}' => 'delete_person',
        'get /announcements' => 'list_announcements',
        'get /events' => 'list_events',
        'get /documents' => 'list_documents',
        'get /options' => 'get_options',
    ];

    /** Phase, in der ein Werkzeug freigeschaltet würde. */
    private const PHASE = [
        'get' => 1,
        'post' => 3,
        'patch' => 3,
        'delete' => 4,
    ];

    /**
     * @return list<array{name: string, method: string, path: string, summary: string, tag: string, kind: string, phase: int, annotations: array<string, bool>, parameters: list<string>}>
     */
    public static function tools(): array
    {
        $spec = OpenApi::spec();
        $tools = [];

        foreach ((array) $spec['paths'] as $path => $operations) {
            foreach ((array) $operations as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'patch', 'put', 'delete'], true) || ! is_array($operation)) {
                    continue;
                }
                $key = $method.' '.$path;
                $readOnly = $method === 'get';
                $parameters = [];
                foreach ((array) ($operation['parameters'] ?? []) as $parameter) {
                    if (is_array($parameter) && isset($parameter['name']) && is_string($parameter['name'])) {
                        $parameters[] = $parameter['name'];
                    }
                }
                if (str_contains((string) $path, '{id}')) {
                    array_unshift($parameters, 'id');
                }
                if (isset($operation['requestBody'])) {
                    $parameters[] = 'Felder der Person';
                }

                $tags = (array) ($operation['tags'] ?? []);
                $tools[] = [
                    'name' => self::NAMES[$key] ?? str_replace(['/', '{', '}'], ['_', '', ''], $method.$path),
                    'method' => strtoupper($method),
                    'path' => (string) $path,
                    'summary' => (string) ($operation['summary'] ?? ''),
                    'tag' => (string) ($tags[0] ?? ''),
                    'kind' => $readOnly ? 'lesen' : ($method === 'delete' ? 'löschen' : 'schreiben'),
                    // Personendaten erst nach Klärung Datenschutz (Phase 2), Inhalte zuerst
                    'phase' => $readOnly && ($tags[0] ?? '') === 'Personen' ? 2 : (self::PHASE[$method] ?? 4),
                    // MCP-Tool-Annotations (Hinweise an den Client, keine Sicherheitsgrenze)
                    'annotations' => [
                        'readOnlyHint' => $readOnly,
                        'destructiveHint' => $method === 'delete',
                        'idempotentHint' => in_array($method, ['get', 'patch', 'delete'], true),
                        'openWorldHint' => false,
                    ],
                    'parameters' => array_values(array_unique($parameters)),
                ];
            }
        }

        return $tools;
    }
}
