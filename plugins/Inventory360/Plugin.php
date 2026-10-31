<?php

namespace Plugins\Inventory360;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use App\Plugins\Support\Tabular;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Inventory360 (enteksystems), API 2.0.
 * Endpunkte aus der API-2.0-Referenz (docs.enteksystems.de/inventory360/api/2.0/, apidoc, Stand 24.11.2025).
 * Token unverändert in «Authorization» (ohne Präfix). Listen mit per_page liefern {_query, results}, sonst eine
 * Liste. Die Referenz beschreibt die Antwortfelder kaum – Spalten werden deshalb erkannt (Tabular).
 */
class Plugin extends BasePlugin
{
    public function test(PluginContext $ctx): array
    {
        $response = $ctx->call('hardware', ['per_page' => 1]);

        if ($response->successful()) {
            $total = $response->json('_query.total');

            return ['ok' => true, 'message' => is_numeric($total)
                ? "Verbunden, Hardware-Inventar lesbar ({$total} Geräte)."
                : 'Verbunden, Hardware-Inventar lesbar.'];
        }

        return ['ok' => false, 'message' => self::failure($response)];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        return match ($action) {
            'search' => $this->search($ctx, $options),
            'device' => $this->device($ctx, trim((string) ($options['inventorynumber'] ?? ''))),
            'categories' => $this->categories($ctx),
            default => parent::action($action, $ctx, $options),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function search(PluginContext $ctx, array $options): ActionResult
    {
        $response = $ctx->call('hardware', array_filter([
            'search' => trim((string) ($options['search'] ?? '')),
            'per_page' => min(200, (int) ($options['per_page'] ?? 50) ?: 50),
            'page' => 1,
        ]));
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }

        $json = (array) $response->json();
        $records = self::records($json);
        $table = Tabular::from($records, ['inventorynumber', 'name', 'type', 'model', 'manufacturer', 'serialnumber', 'status', 'location', 'room']);
        $total = $json['_query']['total'] ?? null;
        $message = is_numeric($total) && (int) $total > count($records)
            ? count($records)." von {$total} Einträgen (erste Seite)."
            : count($records).' Einträge.';

        return ActionResult::table($message, $table['columns'], $table['rows']);
    }

    private function device(PluginContext $ctx, string $number): ActionResult
    {
        if ($number === '' || preg_match('/^[A-Za-z0-9._-]{1,40}$/', $number) !== 1) {
            return ActionResult::warning('Bitte eine gültige Inventarnummer angeben.');
        }

        $response = $ctx->call('hardware-get', ['inventorynumber' => $number]);
        if ($response->status() === 404) {
            return ActionResult::warning("Kein Gerät mit Inventarnummer {$number} gefunden.");
        }
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }

        $json = (array) $response->json();
        $record = isset($json['data']) && is_array($json['data']) ? $json['data'] : $json;
        $rows = [];
        foreach ($record as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $rows[] = ['field' => (string) $key, 'value' => (string) $value];
            }
        }

        return ActionResult::table("Gerät {$number}", [
            ['key' => 'field', 'label' => 'Feld'],
            ['key' => 'value', 'label' => 'Wert'],
        ], $rows);
    }

    private function categories(PluginContext $ctx): ActionResult
    {
        $response = $ctx->call('hardwarecategories');
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }

        $records = self::records((array) $response->json());
        $table = Tabular::from($records, ['id', 'name', 'title']);

        return ActionResult::table(count($records).' Kategorien.', $table['columns'], $table['rows']);
    }

    private static function failure(Response $response): string
    {
        return in_array($response->status(), [401, 403], true)
            ? "Anmeldung abgelehnt (HTTP {$response->status()}). Token prüfen; laut Referenz in «Authorization» ohne Präfix."
            : "Inventory360 antwortet mit HTTP {$response->status()}.";
    }

    /**
     * Liste aus der Antwort holen: direkt als Liste oder unter «results» (mit per_page), «data» bzw. «items».
     *
     * @param  array<mixed>  $json
     * @return list<array<string, mixed>>
     */
    private static function records(array $json): array
    {
        foreach (['results', 'data', 'items', 'hardware'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                $json = $json[$key];
                break;
            }
        }

        return array_values(array_filter($json, 'is_array'));
    }
}
