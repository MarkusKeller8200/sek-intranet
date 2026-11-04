<?php

namespace App\Support;

/**
 * OpenAPI-3.1-Beschreibung der REST-API, aus dem Schema (config/intranet.php) erzeugt.
 * Öffentlich unter /api/openapi.json; die Intranet-Seite «API» zeigt sie mit Tester an.
 */
final class OpenApi
{
    /**
     * @return array<string, mixed>
     */
    public static function spec(): array
    {
        $person = self::personSchema();
        $list = fn (string $ref): array => [
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => ['$ref' => $ref]],
                'meta' => ['$ref' => '#/components/schemas/Meta'],
            ],
        ];
        $paging = [
            ['name' => 'limit', 'in' => 'query', 'description' => 'Anzahl (1–1000, Standard 100)', 'example' => 10, 'schema' => ['type' => 'integer', 'default' => 100]],
            ['name' => 'offset', 'in' => 'query', 'description' => 'Ab Eintrag (für Seiten)', 'schema' => ['type' => 'integer', 'default' => 0]],
        ];
        $errors = [
            '401' => ['$ref' => '#/components/responses/Unauthorized'],
            '403' => ['$ref' => '#/components/responses/Forbidden'],
        ];
        $idParam = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']];
        $example = [
            'first_name' => 'Nora', 'last_name' => 'Beispiel', 'email' => 'nora.beispiel@sekbuelach.example',
            'school' => 'mettmenriet', 'function' => 'Lehrperson', 'role' => 'mitarbeitende', 'active' => true,
        ];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'SekBülach Intranet API',
                'version' => '1.0.0',
                'description' => "REST-API des Intranet-Prototyps. Anmeldung mit einem API-Token (Intranet → Prototyp → API).\n\n"
                    .'Rechte wie im Intranet: Ein Token handelt im Namen seiner Besitzerin oder seines Besitzers. '
                    .'«read» erlaubt GET, «write» zusätzlich POST, PATCH und DELETE. Felder der Gruppen «Privat & Notfall» '
                    .'und «Intranet & Systeme» erhalten nur Tokens der Administration. Prototyp: nur erfundene Testdaten.',
            ],
            'servers' => [['url' => rtrim((string) config('app.url'), '/').'/api/v1']],
            'security' => [['bearer' => []]],
            'tags' => [
                ['name' => 'Konto', 'description' => 'Wer bin ich, welche Rechte hat das Token'],
                ['name' => 'Personen', 'description' => 'Personalverzeichnis (Schreiben nur Administration)'],
                ['name' => 'Inhalte', 'description' => 'Mitteilungen, Termine, Dokumente (nur lesen)'],
                ['name' => 'Stammdaten', 'description' => 'Auswahllisten'],
            ],
            'paths' => [
                '/me' => ['get' => [
                    'tags' => ['Konto'], 'summary' => 'Angemeldete Person und Token',
                    'responses' => ['200' => ['description' => 'OK']] + $errors,
                ]],
                '/people' => [
                    'get' => [
                        'tags' => ['Personen'], 'summary' => 'Personen auflisten',
                        'description' => 'Sortiert nach Nachname. Ohne Administration nur aktive Personen und ohne Admin-Felder.',
                        'parameters' => [
                            ['name' => 'search', 'in' => 'query', 'description' => 'Suche in Name, E-Mail, Funktion, Kürzel', 'example' => 'Muster', 'schema' => ['type' => 'string']],
                            ['name' => 'school', 'in' => 'query', 'description' => 'Schulhaus', 'schema' => ['type' => 'string', 'enum' => array_keys((array) config('intranet.schools'))]],
                            ['name' => 'active', 'in' => 'query', 'description' => 'Nur aktive (true) oder inaktive (false)', 'example' => 'true', 'schema' => ['type' => 'boolean']],
                            ['name' => 'person_type', 'in' => 'query', 'description' => 'Personenart (Schüler:innen und Eltern nur mit Recht «view-students»)', 'schema' => ['type' => 'string', 'enum' => array_keys((array) config('intranet.person_types'))]],
                            ...$paging,
                        ],
                        'responses' => ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => $list('#/components/schemas/Person')]]]] + $errors,
                    ],
                    'post' => [
                        'tags' => ['Personen'], 'summary' => 'Person anlegen', 'description' => 'Recht «write» und Administration.',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PersonInput'], 'example' => $example]]],
                        'responses' => [
                            '201' => ['description' => 'Angelegt', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['$ref' => '#/components/schemas/Person']]]]]],
                            '422' => ['$ref' => '#/components/responses/Invalid'],
                        ] + $errors,
                    ],
                ],
                '/people/{id}' => [
                    'parameters' => [$idParam],
                    'get' => [
                        'tags' => ['Personen'], 'summary' => 'Eine Person',
                        'responses' => ['200' => ['description' => 'OK'], '404' => ['description' => 'Nicht gefunden']] + $errors,
                    ],
                    'patch' => [
                        'tags' => ['Personen'], 'summary' => 'Person ändern (nur gesendete Felder)', 'description' => 'Recht «write» und Administration. Änderungen erscheinen im Änderungsverlauf (Quelle «API»).',
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PersonInput'], 'example' => ['function' => 'Klassenlehrperson 2b']]]],
                        'responses' => ['200' => ['description' => 'Geändert'], '404' => ['description' => 'Nicht gefunden'], '422' => ['$ref' => '#/components/responses/Invalid']] + $errors,
                    ],
                    'delete' => [
                        'tags' => ['Personen'], 'summary' => 'Person löschen', 'description' => 'Recht «write» und Administration. Das eigene Konto kann nicht gelöscht werden.',
                        'responses' => ['204' => ['description' => 'Gelöscht'], '404' => ['description' => 'Nicht gefunden'], '422' => ['description' => 'Eigenes Konto']] + $errors,
                    ],
                ],
                '/announcements' => ['get' => [
                    'tags' => ['Inhalte'], 'summary' => 'Veröffentlichte Mitteilungen', 'parameters' => $paging,
                    'responses' => ['200' => ['description' => 'OK']] + $errors,
                ]],
                '/events' => ['get' => [
                    'tags' => ['Inhalte'], 'summary' => 'Kommende Termine', 'parameters' => $paging,
                    'responses' => ['200' => ['description' => 'OK']] + $errors,
                ]],
                '/documents' => ['get' => [
                    'tags' => ['Inhalte'], 'summary' => 'Dokumente und Links', 'parameters' => $paging,
                    'responses' => ['200' => ['description' => 'OK']] + $errors,
                ]],
                '/options' => ['get' => [
                    'tags' => ['Stammdaten'], 'summary' => 'Auswahllisten (Schulhäuser, Rollen, Kategorien)',
                    'responses' => ['200' => ['description' => 'OK']] + $errors,
                ]],
            ],
            'components' => [
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'sbi_…', 'description' => 'API-Token aus Intranet → Prototyp → API']],
                'schemas' => [
                    'Person' => $person,
                    'PersonInput' => ['type' => 'object', 'properties' => array_diff_key((array) $person['properties'], array_flip(['id', 'name', 'created_at', 'updated_at']))],
                    'Meta' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer']]],
                    'Error' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string'], 'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']]]],
                ],
                'responses' => [
                    'Unauthorized' => ['description' => 'Kein oder ungültiges Token', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    'Forbidden' => ['description' => 'Recht fehlt (Token oder Rolle)', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    'Invalid' => ['description' => 'Ungültige Werte, je Feld eine Meldung', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function personSchema(): array
    {
        $properties = [
            'id' => ['type' => 'string', 'format' => 'uuid', 'readOnly' => true],
        ];

        foreach (PersonFields::fields(true) as $field) {
            $schema = match ($field['type']) {
                'bool' => ['type' => 'boolean'],
                'email' => ['type' => ['string', 'null'], 'format' => 'email'],
                'date' => ['type' => ['string', 'null'], 'format' => 'date'],
                'enum' => ['type' => 'string', 'enum' => array_keys($field['options'] ?? [])],
                default => ['type' => ['string', 'null']],
            };
            $description = $field['label'];
            if ($field['admin']) {
                $description .= ' – nur Administration';
            }
            if ($field['computed']) {
                $description .= ' – aus Vor- und Nachname gebildet';
                $schema['readOnly'] = true;
            }
            $properties[$field['key']] = $schema + ['description' => $description];
        }

        $properties['created_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];
        $properties['updated_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];

        return [
            'type' => 'object',
            'required' => array_values(array_map(fn (array $f): string => $f['key'], array_filter(PersonFields::fields(true), fn (array $f): bool => $f['required']))),
            'properties' => $properties,
        ];
    }
}
