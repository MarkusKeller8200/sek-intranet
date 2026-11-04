<?php

namespace Plugins\Deepsign;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\DemoResponder;
use App\Plugins\PluginContext;
use App\Plugins\SignatureProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
class Plugin extends BasePlugin implements DemoResponder, SignatureProvider
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
        if ($ctx->setting('demo', true) !== false) {
            return true;
        }

        // Ausserhalb der Schweiz nur mit ausdrücklichem Endtest (erfundene Dokumente, eigene E-Mail)
        return ! self::swissServer() && $ctx->setting('endtest', false) !== true;
    }

    public function status(PluginContext $ctx): ?string
    {
        if ($ctx->setting('demo', true) !== false) {
            return 'Demo-Modus (simulierte Antworten).';
        }
        if (! self::swissServer()) {
            return $ctx->setting('endtest', false) === true
                ? 'ENDTEST: echte Aufrufe an DeepSign auf einem Server ausserhalb der Schweiz – nur erfundene Dokumente, danach ausschalten.'
                : 'Demo-Modus (Server ausserhalb der Schweiz: nichts geht an DeepSign; für den Endtest «Endtest» einschalten).';
        }

        return null;
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
        // Aufträge der Schnittstelle «Unterschriften»: Zustand im Cache (siehe demoStateResponse)
        if ($endpoint['id'] === 'document-upload' || ($doc !== null && self::demoState($doc) !== null)) {
            return self::demoStateResponse((string) $endpoint['id'], $doc, $pathParams, $body, $ctx);
        }
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

    // ---------- Demo-Zustand (Aufträge der Schnittstelle «Unterschriften») ----------

    private const DEMO_CACHE = 'deepsign-demo:';

    /**
     * @return array<string, mixed>|null
     */
    private static function demoState(string $id): ?array
    {
        $state = Cache::get(self::DEMO_CACHE.$id);

        return is_array($state) ? $state : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function saveDemoState(array $state): void
    {
        Cache::put(self::DEMO_CACHE.$state['documentId'], $state, now()->addDays(14));
    }

    /**
     * Simulierter Ablauf wie bei DeepSign: Entwurf → Unterzeichnende → Start → je Reihenfolge unterschreiben.
     *
     * @param  array<string, string>  $pathParams
     * @param  array<string, mixed>|null  $body
     * @return array{status: int, json?: mixed}
     */
    private static function demoStateResponse(string $endpoint, ?string $doc, array $pathParams, ?array $body, PluginContext $ctx): array
    {
        if ($endpoint === 'document-upload') {
            if (trim((string) ($body['initiatorAliasName'] ?? '')) === '') {
                return self::demoError(400, 'initiatorAliasName is required.');
            }
            $file = is_array($body['_file'] ?? null) ? $body['_file'] : [];
            $id = 'demo-'.Str::lower((string) Str::uuid());
            $state = [
                'documentId' => $id,
                'documentName' => (string) ($file['name'] ?? 'Dokument.pdf'),
                'documentMimeType' => 'application/pdf',
                'documentStatus' => 'draft',
                'signatureMode' => (string) ($body['signatureMode'] ?? $ctx->setting('signature_mode', 'timestamp')),
                'jurisdiction' => (string) ($body['jurisdiction'] ?? $ctx->setting('jurisdiction', 'zertes')),
                'comment' => (string) ($body['comment'] ?? ''),
                'initiatorDisplayName' => (string) $body['initiatorAliasName'],
                'creationTime' => now()->toIso8601String(),
                'startTime' => null,
                'completionTime' => null,
                'signees' => [],
                'observers' => [],
            ];
            self::saveDemoState($state);
            if (is_string($file['contents'] ?? null)) {
                Cache::put(self::DEMO_CACHE.'file:'.$id, $file['contents'], now()->addDays(14));
            }

            return ['status' => 201, 'json' => self::demoStateJson($state)];
        }

        $state = self::demoState((string) $doc) ?? [];
        $signees = (array) ($state['signees'] ?? []);
        $draft = ($state['documentStatus'] ?? '') === 'draft';
        $signeeId = $pathParams['signeeId'] ?? null;
        $signeeIndex = null;
        foreach ($signees as $i => $s) {
            if (is_array($s) && $s['signeeId'] === $signeeId) {
                $signeeIndex = $i;
            }
        }
        if ($signeeId !== null && $signeeIndex === null) {
            return self::demoError(404, 'Signee not found.');
        }

        switch ($endpoint) {
            case 'document':
                return ['status' => 200, 'json' => self::demoStateJson($state)];
            case 'signees':
                return ['status' => 200, 'json' => $signees];
            case 'signee':
                return ['status' => 200, 'json' => $signeeIndex === null ? null : $signees[$signeeIndex]];
            case 'signee-add':
                if (! $draft) {
                    return self::demoError(409, 'Document is not a draft.');
                }
                if (! filter_var((string) ($body['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                    return self::demoError(400, 'email is invalid.');
                }
                $signee = [
                    'signeeId' => 'demo-'.Str::lower(Str::random(12)), 'documentId' => $doc, 'email' => (string) $body['email'],
                    'signOrder' => (int) ($body['signOrder'] ?? 0), 'signStatus' => 'on-hold', 'signedTime' => null, 'language' => 'de',
                    'initiatorComment' => (string) ($body['comment'] ?? ''), 'autographPosition' => $body['autographPosition'] ?? null,
                ];
                $state['signees'] = [...$signees, $signee];
                self::saveDemoState($state);

                return ['status' => 201, 'json' => $signee];
            case 'observer-add':
                $observer = ['observerId' => 'demo-'.Str::lower(Str::random(12)), 'documentId' => $doc, 'email' => (string) ($body['email'] ?? ''), 'isAdmin' => (bool) ($body['isAdmin'] ?? false)];
                $state['observers'] = [...(array) ($state['observers'] ?? []), $observer];
                self::saveDemoState($state);

                return ['status' => 201, 'json' => $observer];
            case 'document-start':
                if (! $draft || $signees === []) {
                    return self::demoError(409, 'Document must be a draft with signees.');
                }
                $state['documentStatus'] = 'in-progress';
                $state['startTime'] = now()->toIso8601String();
                self::saveDemoState(self::demoAdvanceOrder($state));

                return ['status' => 204];
            case 'document-withdraw':
                $state['documentStatus'] = 'withdrawn';
                self::saveDemoState($state);

                return ['status' => 204];
            case 'document-delete':
                if (! in_array($state['documentStatus'] ?? '', ['draft', 'withdrawn'], true)) {
                    return self::demoError(409, 'Only drafts or withdrawn documents can be deleted.');
                }
                Cache::forget(self::DEMO_CACHE.$doc);
                Cache::forget(self::DEMO_CACHE.'file:'.$doc);

                return ['status' => 204];
            case 'document-access-url':
                return ['status' => 200, 'json' => ['url' => 'https://app.deepsign.swiss/demo/'.$doc, 'validUntil' => now()->addMinutes(5)->toIso8601String()]];
            case 'signee-resend':
            case 'document-update':
            case 'signee-delete':
            case 'observer-delete':
                return ['status' => 204];
            default:
                return self::demoError(501, 'Im Demo-Modus nicht simuliert.');
        }
    }

    /**
     * Nach Start bzw. einer Unterschrift: die nächste Reihenfolge-Gruppe ist an der Reihe, sonst fertig.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function demoAdvanceOrder(array $state): array
    {
        $signees = array_values(array_filter((array) $state['signees'], 'is_array'));
        $open = array_filter($signees, fn (array $s): bool => $s['signStatus'] !== 'signed');
        if ($open === []) {
            $state['documentStatus'] = 'signed';
            $state['completionTime'] = now()->toIso8601String();

            return $state;
        }
        $next = min(array_map(fn (array $s): int => (int) $s['signOrder'], $open));
        foreach ($signees as $i => $s) {
            if ($s['signStatus'] !== 'signed') {
                $signees[$i]['signStatus'] = (int) $s['signOrder'] === $next ? 'pending' : 'on-hold';
            }
        }
        $state['signees'] = $signees;

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function demoStateJson(array $state): array
    {
        $signees = array_values(array_filter((array) ($state['signees'] ?? []), 'is_array'));
        $status = (string) ($state['documentStatus'] ?? 'draft');

        return [
            'documentId' => $state['documentId'] ?? null,
            'documentName' => $state['documentName'] ?? null,
            'documentMimeType' => 'application/pdf',
            // Im echten Betrieb ca. 1 Minute gültig, ohne Anmeldung abrufbar
            'documentUrl' => $status === 'signed' ? 'https://api.sign.deepbox.swiss/demo/download/'.$state['documentId'] : null,
            'documentStatus' => $status,
            'signStatus' => match (true) {
                $status === 'signed' => 'signed',
                $status === 'draft' => 'pending',
                count(array_filter($signees, fn (array $s): bool => $s['signStatus'] === 'signed')) > 0 => 'in-progress',
                default => 'pending',
            },
            'signatureMode' => $state['signatureMode'] ?? 'timestamp',
            'jurisdiction' => $state['jurisdiction'] ?? 'zertes',
            'comment' => $state['comment'] ?? '',
            'initiatorDisplayName' => $state['initiatorDisplayName'] ?? '',
            'creationTime' => $state['creationTime'] ?? null,
            'startTime' => $state['startTime'] ?? null,
            'completionTime' => $state['completionTime'] ?? null,
            'signees' => $signees,
            'observers' => array_values((array) ($state['observers'] ?? [])),
        ];
    }

    // ---------- Schnittstelle «Unterschriften» (App\Support\Signatures) ----------

    public function signatureSend(PluginContext $ctx, array $request, string $pdf, ?array $callback): array
    {
        $data = array_filter([
            'initiatorAliasName' => (string) $ctx->setting('initiator_alias', 'Sekundarschule Bülach'),
            'sendMail' => 'others',
            'signatureMode' => (string) $ctx->setting('signature_mode', 'timestamp'),
            'jurisdiction' => (string) $ctx->setting('jurisdiction', 'zertes'),
            'comment' => $request['comment'] ?? null,
            'companyId' => $ctx->setting('company_id'),
        ], fn (mixed $v): bool => $v !== null && $v !== '');
        if ($callback !== null) {
            $data += [
                'callbackUrl' => $callback['url'],
                'callbackSecret' => $callback['secret'],
                'callbackEventTypes' => ['signee-completed', 'document-completed'],
            ];
        }

        $upload = $ctx->call('document-upload', [], $data, ['name' => $request['file_name'], 'contents' => $pdf]);
        $documentId = $upload->json('documentId');
        if (! $upload->successful() || ! is_string($documentId) || $documentId === '') {
            throw new RuntimeException(self::failure($upload));
        }

        try {
            $ids = [];
            foreach ($request['signees'] as $signee) {
                $added = $ctx->call('signee-add', ['documentId' => $documentId], array_filter([
                    'email' => $signee['email'],
                    'signOrder' => $signee['order'],
                    'language' => 'de',
                    'autographPosition' => $signee['position'] ?? null,
                ], fn (mixed $v): bool => $v !== null));
                $signeeId = $added->json('signeeId');
                if (! $added->successful() || ! is_string($signeeId)) {
                    throw new RuntimeException('Unterzeichnende «'.$signee['email'].'»: '.self::failure($added));
                }
                $ids[] = $signeeId;
            }
            foreach ($request['observers'] as $email) {
                $observer = $ctx->call('observer-add', ['documentId' => $documentId], ['email' => $email, 'isAdmin' => false, 'language' => 'de']);
                if (! $observer->successful()) {
                    throw new RuntimeException('Beobachtende «'.$email.'»: '.self::failure($observer));
                }
            }
        } catch (RuntimeException $e) {
            // Entwurf wieder entfernen, damit bei DeepSign nichts liegen bleibt
            $ctx->call('document-delete', ['documentId' => $documentId]);

            throw $e;
        }

        return ['provider_id' => $documentId, 'signee_ids' => $ids];
    }

    public function signatureStart(PluginContext $ctx, string $providerId): void
    {
        self::expect($ctx->call('document-start', ['documentId' => $providerId], []));
    }

    public function signatureStatus(PluginContext $ctx, string $providerId): array
    {
        $response = $ctx->call('document', ['documentId' => $providerId]);
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }

        $signees = [];
        $anySigned = false;
        foreach ((array) $response->json('signees') as $s) {
            if (! is_array($s)) {
                continue;
            }
            $status = match ((string) ($s['signStatus'] ?? '')) {
                'signed' => 'unterschrieben',
                'rejected' => 'abgelehnt',
                'on-hold' => 'wartet',
                default => 'offen',
            };
            $anySigned = $anySigned || $status === 'unterschrieben';
            $signees[] = [
                'email' => (string) ($s['email'] ?? ''),
                'status' => $status,
                'signed_at' => is_string($s['signedTime'] ?? null) ? $s['signedTime'] : null,
                'provider_id' => (string) ($s['signeeId'] ?? ''),
            ];
        }

        $status = match ((string) $response->json('documentStatus')) {
            'draft' => 'entwurf',
            'signed' => 'unterschrieben',
            'withdrawn' => 'zurueckgezogen',
            'rejected' => 'abgelehnt',
            default => $anySigned ? 'teilweise' : 'versendet',
        };

        return ['status' => $status, 'signees' => $signees];
    }

    public function signatureDownload(PluginContext $ctx, string $providerId): ?string
    {
        if ($ctx->demo()) {
            $file = Cache::get(self::DEMO_CACHE.'file:'.$providerId);

            return is_string($file) ? $file : null;
        }

        $details = $ctx->call('document', ['documentId' => $providerId]);
        $url = $details->json('documentUrl');
        if (! $details->successful() || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            return null;
        }
        // documentUrl gilt ca. 1 Minute und wird ohne Anmeldung abgerufen (kein Authorization-Header)
        $ctx->log('GET documentUrl (ohne Anmeldung)');
        $file = Http::connectTimeout(5)->timeout(60)->get($url);
        $ctx->log("→ HTTP {$file->status()}");

        return $file->successful() ? $file->body() : null;
    }

    public function signatureRemind(PluginContext $ctx, string $providerId, string $signeeId): void
    {
        self::expect($ctx->call('signee-resend', ['documentId' => $providerId, 'signeeId' => $signeeId], []));
    }

    public function signatureWithdraw(PluginContext $ctx, string $providerId): void
    {
        self::expect($ctx->call('document-withdraw', ['documentId' => $providerId], []));
    }

    public function signatureDelete(PluginContext $ctx, string $providerId): void
    {
        self::expect($ctx->call('document-delete', ['documentId' => $providerId]));
    }

    public function signatureAccessUrl(PluginContext $ctx, string $providerId): ?string
    {
        $response = $ctx->call('document-access-url', ['documentId' => $providerId], []);
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }
        foreach (['url', 'documentAccessUrl', 'accessUrl'] as $key) {
            $url = $response->json($key);
            if (is_string($url) && str_starts_with($url, 'https://')) {
                return $url;
            }
        }

        return null;
    }

    public function signatureVerifyCallback(array $headers, string $raw, string $secret): bool
    {
        $value = null;
        foreach ($headers as $name => $v) {
            if (strtolower((string) $name) === 'x-deepsign-signature-256') {
                $value = is_array($v) ? ($v[0] ?? null) : $v;
            }
        }
        if (! is_string($value) || $value === '' || $secret === '') {
            return false;
        }
        $value = trim($value);
        if (str_starts_with(strtolower($value), 'sha256=')) {
            $value = substr($value, 7);
        }
        $hex = hash_hmac('sha256', $raw, $secret);
        $b64 = base64_encode((string) hex2bin($hex));

        return hash_equals($hex, strtolower($value)) || hash_equals($b64, $value);
    }

    public function signatureCallbackId(string $raw): ?string
    {
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return null;
        }
        foreach ([$json, $json['document'] ?? null, $json['data'] ?? null] as $part) {
            if (is_array($part) && is_string($part['documentId'] ?? null) && $part['documentId'] !== '') {
                return $part['documentId'];
            }
        }

        return null;
    }

    public function signatureSimulate(PluginContext $ctx, string $providerId): bool
    {
        $state = self::demoState($providerId);
        if (! $ctx->demo() || $state === null || ($state['documentStatus'] ?? '') !== 'in-progress') {
            return false;
        }
        $signees = array_values(array_filter((array) $state['signees'], 'is_array'));
        foreach ($signees as $i => $s) {
            if ($s['signStatus'] === 'pending') {
                $signees[$i]['signStatus'] = 'signed';
                $signees[$i]['signedTime'] = now()->toIso8601String();
                break;
            }
        }
        $state['signees'] = $signees;
        self::saveDemoState(self::demoAdvanceOrder($state));

        return true;
    }

    private static function expect(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }
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
