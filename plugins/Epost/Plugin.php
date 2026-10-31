<?php

namespace Plugins\Epost;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use App\Plugins\Support\Tabular;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * ePost Communication Platform der Schweizerischen Post (KLARA Public API).
 * Quelle: developer.epost.ch (Business-Doku) und die OpenAPI-Beschreibung unter /openapi?query=ePost
 * (Test: api-test.klara-epost.tech, Produktion: api.epost.ch).
 *
 * Anmeldung: Mandanten über POST /core/latest/tenants (Benutzername/Passwort), dann Token über
 * POST /core/latest/token (grant_type password, tenant_id, company_id) – erledigt PluginContext
 * (Anmeldetyp oauth2_password). Im Prototyp nur lesende Aktionen; Versand folgt.
 */
class Plugin extends BasePlugin
{
    public function test(PluginContext $ctx): array
    {
        if ($ctx->secret('api_key') === null && ($ctx->setting('tenant_id') === null || $ctx->setting('company_id') === null)) {
            $tenants = self::tenants($ctx);

            return ['ok' => false, 'message' => $tenants === []
                ? 'Anmeldung möglich, aber keine Mandanten gefunden.'
                : 'Anmeldung möglich. Mandant und Firma noch eintragen (Aktion «Mandanten anzeigen»): '.count($tenants).' gefunden.'];
        }

        try {
            $response = $ctx->call('monitoring', ['limit' => 1]);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return $response->successful()
            ? ['ok' => true, 'message' => 'Verbunden mit '.self::environment($ctx).', Versandübersicht lesbar.']
            : ['ok' => false, 'message' => self::failure($response)];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        return match ($action) {
            'tenants' => $this->tenantList($ctx),
            'inbox' => $this->inbox($ctx, self::limit($options)),
            'deliveries' => $this->deliveries($ctx, self::limit($options)),
            default => parent::action($action, $ctx, $options),
        };
    }

    private function tenantList(PluginContext $ctx): ActionResult
    {
        $rows = array_map(fn (array $t): array => [
            'company_name' => (string) ($t['company_name'] ?? ''),
            'tenant_id' => (string) ($t['tenant_id'] ?? ''),
            'company_id' => (string) ($t['company_id'] ?? ''),
        ], self::tenants($ctx));

        return ActionResult::table(count($rows).' Mandanten. Mandant und Firma in den Einstellungen eintragen.', [
            ['key' => 'company_name', 'label' => 'Firma'],
            ['key' => 'tenant_id', 'label' => 'Mandant (tenant_id)'],
            ['key' => 'company_id', 'label' => 'Firma (company_id)'],
        ], $rows);
    }

    private function inbox(PluginContext $ctx, int $limit): ActionResult
    {
        $count = $ctx->call('letters-count');
        $letters = $ctx->call('letters', ['limit' => $limit, 'offset' => 0]);
        if (! $letters->successful()) {
            throw new RuntimeException(self::failure($letters));
        }

        $records = array_slice(self::records((array) $letters->json()), 0, $limit);
        $table = Tabular::from($records, ['letterTitle', 'receivedDateTime', 'readStatus', 'fileName', 'registeredStatus', 'remainingDayToDelete']);
        $unread = $count->successful() ? self::number($count->json()) : null;

        return ActionResult::table(
            ($unread !== null ? "{$unread} ungelesen. " : '').count($records).' Briefe angezeigt.',
            $table['columns'],
            $table['rows'],
        );
    }

    /**
     * Versandübersicht ohne Empfängerdaten (unhashedCredentials werden nicht angezeigt).
     */
    private function deliveries(PluginContext $ctx, int $limit): ActionResult
    {
        $response = $ctx->call('monitoring', ['limit' => $limit, 'offset' => 0, 'sort' => 'DESC']);
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }

        $rows = array_map(fn (array $d): array => [
            'id' => (string) ($d['deliveryId'] ?? $d['id'] ?? ''),
            'title' => (string) ($d['documentTitle'] ?? ''),
            'channel' => (string) ($d['channel'] ?? ''),
            'status' => (string) ($d['status'] ?? ''),
            'created' => (string) ($d['createdTime'] ?? ''),
            'delivered' => (string) ($d['deliveredTime'] ?? ''),
            'cost' => is_numeric($d['cost'] ?? null) ? number_format((float) $d['cost'], 2, '.', "'") : '',
        ], self::records((array) $response->json()));

        return ActionResult::table(count($rows).' Sendungen (ohne Empfängerdaten).', [
            ['key' => 'id', 'label' => 'Versand'],
            ['key' => 'title', 'label' => 'Titel'],
            ['key' => 'channel', 'label' => 'Kanal'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'created', 'label' => 'Erstellt'],
            ['key' => 'delivered', 'label' => 'Zugestellt'],
            ['key' => 'cost', 'label' => 'Kosten'],
        ], $rows);
    }

    /**
     * Mandanten des Kontos (POST /core/latest/tenants mit Benutzername und Passwort).
     *
     * @return list<array<string, mixed>>
     */
    private static function tenants(PluginContext $ctx): array
    {
        $username = (string) $ctx->setting('username', '');
        $password = $ctx->secret('password');
        if ($username === '' || $password === null) {
            throw new RuntimeException('Benutzername und Passwort fehlen (Einstellungen).');
        }

        $ctx->log('POST /core/latest/tenants');
        try {
            $response = Http::baseUrl($ctx->baseUrl())->asForm()->acceptJson()->connectTimeout(5)->timeout(20)
                ->post('/core/latest/tenants', ['username' => $username, 'password' => $password]);
        } catch (Throwable $e) {
            throw new RuntimeException('ePost nicht erreichbar: '.$ctx->redact($e->getMessage()));
        }
        $ctx->log("→ HTTP {$response->status()}");

        if (! $response->successful()) {
            throw new RuntimeException(in_array($response->status(), [400, 401, 403], true)
                ? "Anmeldung abgelehnt (HTTP {$response->status()}). Benutzername und Passwort prüfen."
                : self::failure($response));
        }

        return array_values(array_filter((array) $response->json(), 'is_array'));
    }

    private static function environment(PluginContext $ctx): string
    {
        return str_contains($ctx->baseUrl(), 'api-test.') ? 'der ePost-Testumgebung' : 'ePost (Produktion)';
    }

    private static function failure(Response $response): string
    {
        return match (true) {
            $response->status() === 401 => 'Anmeldung abgelehnt (HTTP 401). Zugangsdaten, Mandant und Firma prüfen.',
            $response->status() === 403 => 'Kein Zugriff (HTTP 403). Ist das Widget «eLetter: Versand» bzw. der digitale Briefkasten aktiviert?',
            default => "ePost antwortet mit HTTP {$response->status()}.",
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function limit(array $options): int
    {
        return max(1, min(100, (int) ($options['limit'] ?? 20) ?: 20));
    }

    private static function number(mixed $json): ?int
    {
        if (is_numeric($json)) {
            return (int) $json;
        }
        if (is_array($json)) {
            foreach (['count', 'unreadCount', 'total', 'unread'] as $key) {
                if (is_numeric($json[$key] ?? null)) {
                    return (int) $json[$key];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $json
     * @return list<array<string, mixed>>
     */
    private static function records(array $json): array
    {
        foreach (['content', 'letters', 'items', 'results', 'data'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                $json = $json[$key];
                break;
            }
        }

        return array_values(array_filter($json, 'is_array'));
    }
}
