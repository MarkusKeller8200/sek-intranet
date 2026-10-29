<?php

namespace Plugins\Inventory360;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use App\Plugins\Support\Tabular;
use RuntimeException;

/**
 * Inventory360 (enteksystems), API 2.0.
 * Stand: Endpunkte aus der Admin-Doku (docs.enteksystems.de/inventory360/latest/admin/api/).
 * Die API-2.0-Referenz war bei der Umsetzung nicht erreichbar – Antwortfelder werden
 * deshalb automatisch erkannt (Tabular), Personen-Abgleich folgt.
 */
class Plugin extends BasePlugin
{
    public function test(PluginContext $ctx): array
    {
        $response = $ctx->call('hardware', ['per_page' => 1]);

        return match (true) {
            $response->successful() => ['ok' => true, 'message' => 'Verbunden, Hardware-Inventar lesbar.'],
            in_array($response->status(), [401, 403], true) => ['ok' => false, 'message' => "Anmeldung abgelehnt (HTTP {$response->status()}). Token, Kopfzeile und Präfix prüfen."],
            default => ['ok' => false, 'message' => "Inventory360 antwortet mit HTTP {$response->status()}."],
        };
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        if ($action !== 'search') {
            return parent::action($action, $ctx, $options);
        }

        $response = $ctx->call('hardware', array_filter([
            'search' => trim((string) ($options['search'] ?? '')),
            'per_page' => (int) ($options['per_page'] ?? 50) ?: 50,
        ]));

        if (! $response->successful()) {
            throw new RuntimeException("Inventory360 antwortet mit HTTP {$response->status()}.");
        }

        $records = self::records((array) $response->json());
        $table = Tabular::from($records, ['inventorynumber', 'inventory_number', 'name', 'model', 'manufacturer', 'serialnumber', 'serial_number', 'location', 'user']);

        return ActionResult::table(count($records).' Einträge.', $table['columns'], $table['rows']);
    }

    /**
     * Liste aus der Antwort holen, unabhängig davon, ob sie direkt oder unter «data»/«items» steht.
     *
     * @param  array<mixed>  $json
     * @return list<array<string, mixed>>
     */
    private static function records(array $json): array
    {
        foreach (['data', 'items', 'results', 'hardware'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                $json = $json[$key];
                break;
            }
        }

        return array_values(array_filter($json, 'is_array'));
    }
}
