<?php

namespace App\Plugins;

use RuntimeException;

/**
 * Basisklasse aller Plugins. Ein Plugin überschreibt nur, was es anbietet.
 * Alles Weitere (Formulare, Endpunkte, Anmeldung) beschreibt plugin.json.
 */
abstract class BasePlugin
{
    /**
     * Verbindung prüfen (Knopf «Testen»). Standard: ersten prüfbaren GET-Endpunkt aufrufen.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(PluginContext $ctx): array
    {
        foreach ($ctx->manifest->endpoints() as $endpoint) {
            if (strtoupper((string) ($endpoint['method'] ?? 'GET')) === 'GET' && ($endpoint['test'] ?? true) !== false
                && ($endpoint['status'] ?? 'full') !== 'planned' && ! str_contains((string) $endpoint['path'], '{')) {
                $response = $ctx->call((string) $endpoint['id']);

                return [
                    'ok' => $response->successful(),
                    'message' => $response->successful()
                        ? "Verbindung in Ordnung ({$endpoint['label']}, HTTP {$response->status()})."
                        : "Fehler bei «{$endpoint['label']}»: HTTP {$response->status()}.",
                ];
            }
        }

        return ['ok' => false, 'message' => 'Kein prüfbarer Endpunkt beschrieben.'];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        throw new RuntimeException("Aktion «{$action}» ist nicht umgesetzt.");
    }

    /**
     * Daten für eine Kachel auf der Startseite.
     *
     * @return array{items: list<array{label: string, value: string|int, hint?: string}>, note?: string}
     */
    public function widget(string $widget, PluginContext $ctx): array
    {
        return ['items' => []];
    }

    /**
     * Reaktion auf ein Ereignis im Intranet (siehe PluginManifest::EVENTS).
     * Läuft nach der eigentlichen Änderung; Fehler landen nur im Protokoll.
     *
     * @param  array<string, mixed>  $data
     */
    public function onEvent(string $event, array $data, PluginContext $ctx): void
    {
        //
    }

    /**
     * Kurzer Statustext für die Plugin-Karte, z. B. «Verbunden mit Tenant …».
     */
    public function status(PluginContext $ctx): ?string
    {
        return null;
    }
}
