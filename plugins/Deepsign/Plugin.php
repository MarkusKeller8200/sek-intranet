<?php

namespace Plugins\Deepsign;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\DemoResponder;
use App\Plugins\PluginContext;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * DeepSign (DeepCloud AG): elektronische Unterschrift. Konzept: docs/KONZEPT-DEEPSIGN.md, Meilenstein
 * «DeepSign – Integration» (#35). Quelle: https://apidocs.deepcloud.swiss/deepsign-api-docs/index.html
 *
 * Anmeldung: Token über deepcloud.swiss (grant_type password: Service-Benutzer + Partner-Client) – erledigt
 * PluginContext (Anmeldetyp oauth2_password, Token bis kurz vor Ablauf zwischengespeichert).
 *
 * Demo-Modus: simulierte Antworten für alle Endpunkte, ohne Anfrage an DeepSign und ohne Anmeldung. Auf einem
 * Server ausserhalb der Schweiz (INTRANET_SERVER_IN_SWITZERLAND=false) immer eingeschaltet – dort dürfen keine
 * echten Dokumente an DeepSign gehen.
 */
class Plugin extends BasePlugin implements DemoResponder
{
    /** Feste IDs der Demo-Daten (auch Beispielwerte im API-Tester). */
    public const DEMO_DOCUMENT = '3f1c2a9e-5b7d-4e2a-9c61-1d2e3f4a5b6c';

    public const DEMO_SIGNEE = '7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d';

    public const DEMO_OBSERVER = 'c4d5e6f7-8a9b-4c0d-9e1f-2a3b4c5d6e7f';

    private const DOCUMENT_STATUS = ['draft', 'in-progress', 'signed', 'withdrawn', 'rejected'];

    /** Umgebungen (Einstellung «environment» = Adresse der Anmeldung). */
    public const PRODUCTION = 'https://deepcloud.swiss';

    public const TEST = 'https://int.deepcloud.swiss';

    public function demoActive(PluginContext $ctx): bool
    {
        return ! self::swissServer() || $ctx->setting('demo', true) !== false;
    }

    public function status(PluginContext $ctx): ?string
    {
        if (! self::swissServer()) {
            return 'Demo-Modus (Server ausserhalb der Schweiz: nichts geht an DeepSign).';
        }

        return $ctx->setting('demo', true) !== false ? 'Demo-Modus (simulierte Antworten).' : null;
    }

    public function test(PluginContext $ctx): array
    {
        try {
            $response = $ctx->call('overview', ['limit' => 1]);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => self::failure($response)];
        }

        $count = (int) ($response->json('size') ?? count((array) $response->json('documents')));
        $message = ($ctx->demo() ? 'Demo-Modus: ' : 'Verbunden mit DeepSign: ')."{$count} Dokumente sichtbar.";

        // Service-Benutzer und Firmen (users/me, nur Produktion dokumentiert) – nur als Zusatz
        if ($ctx->demo() || self::environment($ctx) === self::PRODUCTION) {
            try {
                $me = $ctx->call('me');
                if ($me->successful()) {
                    $name = (string) ($me->json('display_name') ?? $me->json('email') ?? $me->json('username') ?? '');
                    $companies = array_values(array_filter(array_map(
                        fn (mixed $c): string => is_array($c) ? (string) ($c['display_name'] ?? $c['name'] ?? '') : '',
                        (array) $me->json('companies'),
                    )));
                    $message .= ($name !== '' ? " Service-Benutzer: {$name}" : '').($companies !== [] ? ' ('.implode(', ', $companies).')' : '').'.';
                }
            } catch (Throwable) {
                // users/me ist nur ein Zusatz
            }
        }

        return ['ok' => true, 'message' => str_replace('..', '.', $message)];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        return match ($action) {
            'service-user' => $this->serviceUser($ctx),
            'documents' => $this->documents($ctx, (string) ($options['status'] ?? '')),
            'modes' => $this->modes($ctx),
            default => parent::action($action, $ctx, $options),
        };
    }

    /**
     * Link zu DeepAdmin: Die Schule meldet sich mit ihrem DeepCloud-Konto an und erlaubt dem Intranet den Zugriff.
     * DeepAdmin zeigt Benutzername und Passwort des neuen Service-Benutzers einmal an (copy_paste).
     */
    private function serviceUser(PluginContext $ctx): ActionResult
    {
        $clientId = $ctx->secret('client_id');
        if ($clientId === null) {
            return ActionResult::warning('Zuerst den Partner-Client (client_id) von DeepCloud in den Einstellungen eintragen.');
        }

        $url = self::serviceUserUrl(self::environment($ctx), $clientId);

        return ActionResult::ok(
            'In DeepAdmin ('.(self::environment($ctx) === self::PRODUCTION ? 'Produktion' : 'Test').') mit dem DeepCloud-Konto der Schule anmelden '
            .'und den Zugriff bestätigen. Benutzername und Passwort erscheinen nur einmal: in die Einstellungen kopieren, speichern, '
            .'dann «Verbindung testen».'
        )->withLink('DeepAdmin öffnen', $url);
    }

    public static function serviceUserUrl(string $environment, string $clientId): string
    {
        $admin = $environment === self::TEST ? 'https://admin.int.deepbox.swiss' : 'https://admin.deepbox.swiss';

        return $admin.'/third-party-connection/?'.http_build_query([
            'response_type' => 'service_account',
            'client_id' => $clientId,
            'response_mode' => 'copy_paste',
        ]);
    }

    private static function environment(PluginContext $ctx): string
    {
        return $ctx->setting('environment') === self::TEST ? self::TEST : self::PRODUCTION;
    }

    private function documents(PluginContext $ctx, string $status): ActionResult
    {
        $params = ['limit' => 100, 'order' => 'creationTime desc'];
        if (in_array($status, self::DOCUMENT_STATUS, true)) {
            $params['filterDocumentStatus'] = $status;
        }
        $response = $ctx->call('overview', $params);
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }

        $rows = [];
        foreach ((array) $response->json('documents') as $doc) {
            if (! is_array($doc)) {
                continue;
            }
            $policy = is_array($doc['policy'] ?? null) ? $doc['policy'] : [];
            $rows[] = [
                'name' => (string) ($doc['documentName'] ?? ''),
                'status' => self::statusLabel((string) ($doc['documentStatus'] ?? '')),
                'sign_status' => self::statusLabel((string) ($doc['signStatus'] ?? '')),
                'withdraw' => ($policy['canWithdraw'] ?? false) === true ? 'ja' : 'nein',
                'id' => (string) ($doc['documentId'] ?? ''),
            ];
        }

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '').count($rows).' Dokumente.', [
            ['key' => 'name', 'label' => 'Dokument'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'sign_status', 'label' => 'Unterschriften'],
            ['key' => 'withdraw', 'label' => 'Zurückziehen möglich'],
            ['key' => 'id', 'label' => 'ID'],
        ], $rows);
    }

    private function modes(PluginContext $ctx): ActionResult
    {
        $response = $ctx->call('available-modes');
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }

        $names = ['timestamp' => 'Einfach (Zeitstempel)', 'advanced' => 'Fortgeschritten (2FA)', 'qualified' => 'Qualifiziert'];
        $rows = [];
        foreach ((array) $response->json() as $mode => $jurisdictions) {
            if (! is_array($jurisdictions) || $jurisdictions === []) {
                $rows[] = ['mode' => $names[$mode] ?? (string) $mode, 'jurisdiction' => 'ohne', 'authority' => '–'];

                continue;
            }
            foreach ($jurisdictions as $jurisdiction => $authorities) {
                $rows[] = [
                    'mode' => $names[$mode] ?? (string) $mode,
                    'jurisdiction' => $jurisdiction === 'zertes' ? 'Schweiz (ZertES)' : ($jurisdiction === 'eidas' ? 'EU (eIDAS)' : (string) $jurisdiction),
                    'authority' => implode(', ', array_map(fn (mixed $a): string => match ($a) {
                        'ras' => 'MobileID', 'did' => 'DeepID', default => is_scalar($a) ? (string) $a : '',
                    }, (array) $authorities)),
                ];
            }
        }

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '').count($rows).' Kombinationen.', [
            ['key' => 'mode', 'label' => 'Signaturstufe'],
            ['key' => 'jurisdiction', 'label' => 'Rechtsraum'],
            ['key' => 'authority', 'label' => 'Identifikation'],
        ], $rows);
    }

    // ---------- Demo-Modus ----------

    public function demoResponse(array $endpoint, array $pathParams, array $query, ?array $body, PluginContext $ctx): array
    {
        $doc = $pathParams['documentId'] ?? null;
        if ($doc !== null && $doc !== self::DEMO_DOCUMENT && ! str_starts_with($doc, 'demo-')) {
            return self::demoError(404, 'Document not found (Demo-Modus kennt nur '.self::DEMO_DOCUMENT.').');
        }
        $signee = $pathParams['signeeId'] ?? null;
        if ($signee !== null && $signee !== self::DEMO_SIGNEE) {
            return self::demoError(404, 'Signee not found (Demo-Modus kennt nur '.self::DEMO_SIGNEE.').');
        }

        return match ((string) $endpoint['id']) {
            'me' => ['status' => 200, 'json' => [
                'display_name' => 'Intranet SekBülach (Demo)', 'email' => '0f1e2d3c-demo@in.deepcloud.swiss',
                'companies' => [['group_id' => '0c1d2e3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f', 'display_name' => 'Sekundarschule Bülach (Demo)']],
            ]],
            'overview' => ['status' => 200, 'json' => self::demoOverview((string) ($query['filterDocumentStatus'] ?? ''), (int) ($query['limit'] ?? 10), (int) ($query['offset'] ?? 0))],
            'available-modes' => ['status' => 200, 'json' => [
                'timestamp' => (object) [],
                'advanced' => ['zertes' => ['ras', 'did'], 'eidas' => ['did']],
                'qualified' => ['zertes' => ['ras', 'did'], 'eidas' => ['ras', 'did']],
            ]],
            'document' => ['status' => 200, 'json' => self::demoDocument(self::demoStatusOf((string) $doc), $ctx, null, (string) $doc)],
            'document-upload' => trim((string) ($body['initiatorAliasName'] ?? '')) === ''
                ? self::demoError(400, 'initiatorAliasName is required.')
                : ['status' => 201, 'json' => self::demoDocument('draft', $ctx, $body, 'demo-'.substr(hash('sha256', (string) json_encode($body)), 0, 12))],
            'scan-predefined' => ['status' => 200, 'json' => ['signatureMode' => 'timestamp', 'jurisdiction' => 'zertes', 'signatureFields' => [
                ['signFieldName' => 'Unterschrift_Mitarbeiterin', 'signatureType' => 'signature', 'email' => 'anna.muster@sekbuelach.example', 'signOrder' => 0,
                    'autographPosition' => ['pageNumber' => 1, 'x' => 350, 'y' => 80, 'width' => 180, 'height' => 60]],
            ]]],
            'signees' => ['status' => 200, 'json' => self::demoSignees()],
            'signee' => ['status' => 200, 'json' => self::demoSignees()[0]],
            'signee-add' => ! filter_var((string) ($body['email'] ?? ''), FILTER_VALIDATE_EMAIL)
                ? self::demoError(400, 'email is invalid.')
                : ['status' => 201, 'json' => array_merge(self::demoSignees()[0], [
                    'signeeId' => 'demo-'.substr(hash('sha256', (string) ($body['email'] ?? '')), 0, 12),
                    'email' => (string) ($body['email'] ?? ''), 'signOrder' => (int) ($body['signOrder'] ?? 0), 'signStatus' => 'on-hold',
                    'initiatorComment' => (string) ($body['comment'] ?? ''), 'signedTime' => null,
                ])],
            'signee-update' => ['status' => 200, 'json' => array_merge(self::demoSignees()[0], array_intersect_key((array) $body, array_flip(['email', 'signOrder', 'autographPosition'])))],
            'signee-delete' => ['status' => 200, 'json' => self::demoSignees()[0]],
            'observer-add' => ['status' => 201, 'json' => ['observerId' => self::DEMO_OBSERVER, 'documentId' => $doc, 'email' => (string) ($body['email'] ?? ''), 'isAdmin' => (bool) ($body['isAdmin'] ?? false), 'language' => 'de']],
            'document-access-url' => ['status' => 200, 'json' => ['url' => 'https://app.deepsign.swiss/demo/'.self::DEMO_DOCUMENT, 'validUntil' => now()->addMinutes(5)->toIso8601String()]],
            'document-update', 'document-start', 'document-withdraw', 'document-delete', 'signee-resend', 'observer-delete' => ['status' => 204],
            default => self::demoError(501, 'Im Demo-Modus nicht simuliert.'),
        };
    }

    /**
     * @return array{documents: list<array<string, mixed>>, pagination: array{offset: int, limit: int}, size: int}
     */
    private static function demoOverview(string $status, int $limit, int $offset): array
    {
        $docs = [
            ['documentId' => self::DEMO_DOCUMENT, 'documentName' => 'Monatsrapport Oktober 2026 – Muster Anna.pdf', 'documentStatus' => 'in-progress', 'signStatus' => 'in-progress'],
            ['documentId' => 'demo-signed-0926', 'documentName' => 'Monatsrapport September 2026 – Muster Anna.pdf', 'documentStatus' => 'signed', 'signStatus' => 'signed'],
            ['documentId' => 'demo-draft-1126', 'documentName' => 'Monatsrapport November 2026 – Beispiel Beat.pdf', 'documentStatus' => 'draft', 'signStatus' => 'pending'],
        ];
        $docs = array_values(array_filter($docs, fn (array $d): bool => $status === '' || $d['documentStatus'] === $status));
        $page = array_slice($docs, max(0, $offset), max(1, $limit));

        return [
            'documents' => array_map(fn (array $d): array => $d + [
                'documentMimeType' => 'application/pdf',
                'thumbnailUrl' => null,
                'signKey' => null,
                'policy' => ['canWithdraw' => $d['documentStatus'] === 'in-progress', 'canDelete' => $d['documentStatus'] === 'draft'],
            ], $page),
            'pagination' => ['offset' => $offset, 'limit' => $limit],
            'size' => count($docs),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private static function demoDocument(string $status, PluginContext $ctx, ?array $body = null, string $id = self::DEMO_DOCUMENT): array
    {
        $signees = $status === 'draft' ? [] : self::demoSignees();
        $name = 'Monatsrapport Oktober 2026 – Muster Anna.pdf';
        foreach (self::demoOverview('', 100, 0)['documents'] as $d) {
            if ($d['documentId'] === $id) {
                $name = (string) $d['documentName'];
            }
        }

        return [
            'documentId' => $id,
            'documentName' => $name,
            'documentMimeType' => 'application/pdf',
            // Im echten Betrieb ca. 1 Minute gültig, ohne Anmeldung abrufbar
            'documentUrl' => $status === 'signed' ? 'https://api.sign.deepbox.swiss/demo/download/'.$id : null,
            'thumbnailUrl' => null,
            'creationTime' => '2026-11-01T08:00:00Z',
            'startTime' => $status === 'draft' ? null : '2026-11-01T08:05:00Z',
            'completionTime' => $status === 'signed' ? '2026-10-31T16:40:00Z' : null,
            'documentStatus' => $status,
            'signStatus' => match ($status) {
                'draft' => 'pending', 'signed' => 'signed', default => 'in-progress',
            },
            'signatureMode' => (string) ($body['signatureMode'] ?? $ctx->setting('signature_mode', 'timestamp')),
            'jurisdiction' => (string) ($body['jurisdiction'] ?? $ctx->setting('jurisdiction', 'zertes')),
            'comment' => (string) ($body['comment'] ?? ''),
            'initiatorDisplayName' => (string) ($body['initiatorAliasName'] ?? $ctx->setting('initiator_alias', 'Sekundarschule Bülach')),
            'signees' => $signees,
            'signeesOrdered' => array_map(fn (array $s): array => [$s], $signees),
            'observers' => [],
            'attachmentsAllowed' => false,
        ];
    }

    private static function demoStatusOf(string $id): string
    {
        foreach (self::demoOverview('', 100, 0)['documents'] as $d) {
            if ($d['documentId'] === $id) {
                return (string) $d['documentStatus'];
            }
        }

        return 'draft';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function demoSignees(): array
    {
        return [
            [
                'signeeId' => self::DEMO_SIGNEE, 'documentId' => self::DEMO_DOCUMENT, 'email' => 'anna.muster@sekbuelach.example',
                'signOrder' => 0, 'signStatus' => 'signed', 'signedTime' => '2026-11-01T09:12:00Z', 'language' => 'de',
                'signUrl' => null, 'autographPosition' => ['pageNumber' => 1, 'x' => 350, 'y' => 80, 'width' => 180, 'height' => 60],
                'policy' => ['canModifyAutographPosition' => false],
            ],
            [
                'signeeId' => 'demo-signee-sl', 'documentId' => self::DEMO_DOCUMENT, 'email' => 'beat.beispiel@sekbuelach.example',
                'signOrder' => 1, 'signStatus' => 'pending', 'signedTime' => null, 'language' => 'de',
                'signUrl' => null, 'autographPosition' => ['pageNumber' => 1, 'x' => 350, 'y' => 160, 'width' => 180, 'height' => 60],
                'policy' => ['canModifyAutographPosition' => false],
            ],
        ];
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    private static function demoError(int $status, string $message): array
    {
        return ['status' => $status, 'json' => ['status' => $status, 'message' => $message]];
    }

    // ---------- Hilfen ----------

    private static function swissServer(): bool
    {
        return (bool) config('intranet.server_in_switzerland');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Entwurf',
            'in-progress' => 'in Arbeit',
            'signed' => 'unterschrieben',
            'withdrawn' => 'zurückgezogen',
            'rejected' => 'abgelehnt',
            'on-hold' => 'wartet (Reihenfolge)',
            'pending' => 'offen',
            default => $status,
        };
    }

    private static function failure(Response $response): string
    {
        $detail = $response->json('message') ?? $response->json('error_description') ?? $response->json('error');
        $text = is_scalar($detail) ? (string) $detail : '';

        return match ($response->status()) {
            401 => 'DeepSign: nicht angemeldet oder Token ungültig (HTTP 401). Zugangsdaten prüfen.',
            403 => 'DeepSign: keine Berechtigung (HTTP 403).',
            404 => 'DeepSign: nicht gefunden (HTTP 404).',
            429 => 'DeepSign: zu viele Abfragen (HTTP 429). Abfragen höchstens alle 15 Minuten.',
            default => "DeepSign: HTTP {$response->status()}".($text !== '' ? " – {$text}" : '').'.',
        };
    }
}
