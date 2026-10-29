<?php

namespace Plugins\Ida;

use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use Illuminate\Support\Facades\Http;

/**
 * IDA (eigenes Portal). Die IDA-API ist noch nicht festgelegt (IDA ADR-002: Inertia,
 * API-Stil offen). Bis dahin: Verbindungsprüfung über /up und vorgemerkte Ereignisse.
 */
class Plugin extends BasePlugin
{
    public function test(PluginContext $ctx): array
    {
        // /up braucht kein Token; Anmeldung nur setzen, wenn eines hinterlegt ist.
        $request = $ctx->secret('api_token') !== null ? $ctx->http() : Http::baseUrl($ctx->baseUrl())->timeout(10);
        $response = $request->get('/up');

        return $response->successful()
            ? ['ok' => true, 'message' => 'IDA ist erreichbar. Weitere Funktionen folgen mit der IDA-API.']
            : ['ok' => false, 'message' => "IDA antwortet mit HTTP {$response->status()}."];
    }

    public function onEvent(string $event, array $data, PluginContext $ctx): void
    {
        if ($ctx->setting('report_people', false) !== true) {
            return;
        }

        // Sobald IDA eine API hat: hier an /api/v1/persons melden.
        $ctx->log("{$event} für ".($data['email'] ?? '?').': vorgemerkt – IDA-API noch nicht verfügbar.');
    }
}
