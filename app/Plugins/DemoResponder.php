<?php

namespace App\Plugins;

/**
 * Plugins mit Demo-Modus (z. B. DeepSign): Solange kein Zugang vorhanden ist oder auf dem Testserver keine echten
 * Daten an den Dienst gehen dürfen, liefert das Plugin für jeden Endpunkt eine simulierte Antwort. Es geht dann
 * keine Anfrage an den Dienst und keine Anmeldung; Aktionen, Verbindungstest und API-Tester laufen trotzdem.
 */
interface DemoResponder
{
    /**
     * Ist der Demo-Modus für diesen Lauf eingeschaltet (in der Regel eine Einstellung «demo»)?
     */
    public function demoActive(PluginContext $ctx): bool;

    /**
     * Simulierte Antwort für einen Endpunkt aus dem Manifest.
     *
     * @param  array<string, mixed>  $endpoint  Endpunkt aus plugin.json
     * @param  array<string, string>  $pathParams  eingesetzte Pfadparameter ({documentId} → Wert)
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|null  $body  Objekt oder Liste
     * @return array{status: int, json?: mixed}
     */
    public function demoResponse(array $endpoint, array $pathParams, array $query, ?array $body, PluginContext $ctx): array;
}
