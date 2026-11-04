<?php

namespace Plugins\Deepsign;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\DemoResponder;
use App\Plugins\PluginContext;
use App\Plugins\SignatureProvider;
use App\Support\AuditLog;
use App\Support\DocumentAccess;
use App\Support\Signatures;
use App\Support\SimplePdf;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
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

    public const DEMO_FILE = '5e6f7a8b-9c0d-4e1f-8a2b-3c4d5e6f7a8b';

    public const DEMO_ATTACHMENT = '9a0b1c2d-3e4f-4a5b-8c6d-7e8f9a0b1c2d';

    public const DEMO_KEY = '6d7e8f9a-0b1c-4d2e-8f3a-4b5c6d7e8f9a';

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
            'send' => $this->send($ctx, $options),
            'documents' => $this->documents($ctx, (string) ($options['status'] ?? '')),
            'modes' => $this->modes($ctx),
            'seals' => $this->seals($ctx),
            'authority' => $this->authority($ctx, (string) ($options['signatureMode'] ?? 'advanced')),
            'webhook' => $this->webhookCheck($ctx),
            'archive' => $this->archive($ctx, $options),
            default => parent::action($action, $ctx, $options),
        };
    }

    /**
     * Dokument zur Unterschrift senden: über die Kern-Schnittstelle «Unterschriften», damit der Auftrag unter
     * Verwaltung → Unterschriften erscheint (Status, Erinnern, unterschriebenes PDF).
     *
     * @param  array<string, mixed>  $options
     */
    private function send(PluginContext $ctx, array $options): ActionResult
    {
        $file = $options['file'] ?? null;
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return ActionResult::warning('Bitte ein PDF wählen.');
        }
        if ($file->getSize() > 20 * 1024 * 1024) {
            return ActionResult::warning('Das PDF ist grösser als 20 MB.');
        }
        $signees = Signatures::parseSignees((string) ($options['signees'] ?? ''));
        if ($signees === []) {
            return ActionResult::warning('Mindestens eine unterzeichnende Person angeben (eine pro Zeile: Name; E-Mail).');
        }
        $observers = array_values(array_filter(array_map('trim', explode(',', (string) ($options['observers'] ?? '')))));

        $request = app(Signatures::class)->sendManual(
            (string) ($options['title'] ?? ''),
            $file->getClientOriginalName(),
            (string) $file->get(),
            $signees,
            ($options['order'] ?? '') === 'gleichzeitig',
            $observers,
            is_string($options['comment'] ?? null) ? $options['comment'] : null,
            $ctx->user,
        );

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '').'«'.$request['title'].'» ist zur Unterschrift versendet. Status unter Verwaltung → Unterschriften.', [
            ['key' => 'order', 'label' => 'Reihenfolge'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'E-Mail'],
            ['key' => 'status', 'label' => 'Status'],
        ], array_map(fn (array $s): array => [
            'order' => (int) $s['order'] + 1, 'name' => (string) $s['name'], 'email' => (string) $s['email'], 'status' => (string) $s['status'],
        ], array_values(array_filter((array) $request['signees'], 'is_array'))));
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

    private function seals(PluginContext $ctx): ActionResult
    {
        $response = $ctx->call('seals');
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }
        $rows = [];
        foreach ((array) $response->json() as $seal) {
            if (is_array($seal)) {
                $rows[] = [
                    'name' => (string) ($seal['displayName'] ?? $seal['name'] ?? ''),
                    'key' => (string) ($seal['sealKey'] ?? ''),
                    'id' => (string) ($seal['sealId'] ?? ''),
                ];
            }
        }

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '').count($rows).' Siegel.', [
            ['key' => 'name', 'label' => 'Siegel'],
            ['key' => 'key', 'label' => 'Schlüssel'],
            ['key' => 'id', 'label' => 'ID'],
        ], $rows);
    }

    private function authority(PluginContext $ctx, string $mode): ActionResult
    {
        $mode = in_array($mode, ['advanced', 'qualified'], true) ? $mode : 'advanced';
        $response = $ctx->call('authority-services', ['signatureMode' => $mode, 'jurisdiction' => (string) $ctx->setting('jurisdiction', 'zertes')]);
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }
        $rows = [];
        foreach ((array) $response->json() as $service => $info) {
            $info = is_array($info) ? $info : [];
            $rows[] = [
                'service' => match ($service) {
                    'ras' => 'MobileID', 'did' => 'DeepID', default => (string) $service,
                },
                'available' => ($info['available'] ?? null) === false ? 'nein' : 'ja',
                'detail' => implode(', ', array_map(fn (mixed $v, int|string $k): string => $k.': '.(is_scalar($v) ? (string) $v : (string) json_encode($v)),
                    array_values(array_diff_key($info, ['available' => true])), array_keys(array_diff_key($info, ['available' => true])))),
            ];
        }

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '').'Identifikation für '.($mode === 'qualified' ? 'qualifizierte' : 'fortgeschrittene').' Unterschriften.', [
            ['key' => 'service', 'label' => 'Identifikation'],
            ['key' => 'available', 'label' => 'Verfügbar'],
            ['key' => 'detail', 'label' => 'Angaben'],
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
            'signee-add' => match (true) {
                ! filter_var((string) ($body['email'] ?? ''), FILTER_VALIDATE_EMAIL) => self::demoError(400, 'email is invalid.'),
                ! in_array($body['signeeType'] ?? null, ['sign', 'approve'], true) => self::demoError(400, 'signeeType is required.'),
                default => ['status' => 201, 'json' => array_merge(self::demoSignees()[0], [
                    'signeeId' => 'demo-'.substr(hash('sha256', (string) ($body['email'] ?? '')), 0, 12),
                    'email' => (string) ($body['email'] ?? ''), 'signOrder' => (int) ($body['signOrder'] ?? 0), 'signStatus' => 'on-hold',
                    'initiatorComment' => (string) ($body['comment'] ?? ''), 'signedTime' => null,
                ])],
            },
            'signee-update' => ['status' => 200, 'json' => array_merge(self::demoSignees()[0], array_intersect_key((array) $body, array_flip(['email', 'signOrder', 'autographPosition'])))],
            'signee-delete' => ['status' => 200, 'json' => self::demoSignees()[0]],
            'observer-add' => ['status' => 201, 'json' => ['observerId' => self::DEMO_OBSERVER, 'documentId' => $doc, 'email' => (string) ($body['email'] ?? ''), 'isAdmin' => (bool) ($body['isAdmin'] ?? false), 'language' => 'de']],
            'document-access-url' => ['status' => 200, 'json' => 'https://app.deepsign.swiss/demo/'.self::DEMO_DOCUMENT],
            'document-update', 'document-start', 'document-withdraw', 'document-delete', 'signee-resend', 'observer-delete' => ['status' => 204],
            default => self::demoGeneric((string) $endpoint['id'], $pathParams, $body, $ctx),
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
                // wie DeepSign: signeeType und fileSignatureFields sind Pflicht
                if (! in_array($body['signeeType'] ?? null, ['sign', 'approve'], true) || ! array_key_exists('fileSignatureFields', (array) $body)) {
                    return self::demoError(400, 'signeeType and fileSignatureFields are required.');
                }
                $signee = [
                    'signeeId' => 'demo-'.Str::lower(Str::random(12)), 'documentId' => $doc, 'email' => (string) $body['email'],
                    'signOrder' => (int) ($body['signOrder'] ?? 0), 'signStatus' => 'on-hold', 'signedTime' => null, 'language' => 'de',
                    'initiatorComment' => (string) ($body['comment'] ?? ''), 'signeeType' => (string) $body['signeeType'],
                    'fileSignatureFields' => $body['fileSignatureFields'],
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
                if (! in_array($body['sendMail'] ?? null, ['all', 'others', 'none'], true)) {
                    return self::demoError(400, 'sendMail is required.');
                }
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
                return ['status' => 200, 'json' => 'https://app.deepsign.swiss/demo/'.$doc];
            case 'signee-resend':
            case 'document-update':
            case 'signee-delete':
            case 'observer-delete':
                return ['status' => 204];
            default:
                return self::demoGeneric($endpoint, $pathParams, $body, $ctx);
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
            'files' => [self::demoFile((string) ($state['documentName'] ?? 'Dokument.pdf'), $status === 'signed' ? 'https://api.sign.deepbox.swiss/demo/download/'.$state['documentId'] : null)],
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

    // ---------- Webhook (#93) ----------

    /**
     * Webhook für ein neues Dokument: nur, wenn der Server ihn erlaubt oder die Einstellung «Webhook verwenden» an ist.
     *
     * @param  array{url: string, secret: string, enabled?: bool}|null  $callback
     * @return array{url: string, secret: string}|null
     */
    private static function webhook(PluginContext $ctx, ?array $callback): ?array
    {
        if ($callback === null || (($callback['enabled'] ?? false) !== true && $ctx->setting('webhook', false) !== true)) {
            return null;
        }

        return ['url' => self::webhookUrl($ctx, $callback['url']), 'secret' => $callback['secret']];
    }

    /** Adresse des Webhooks: öffentliche Adresse aus den Einstellungen (falls gesetzt) + Pfad des Intranets. */
    public static function webhookUrl(PluginContext $ctx, string $default): string
    {
        $base = trim((string) $ctx->setting('webhook_url', ''));
        if ($base === '') {
            return $default;
        }
        $path = parse_url($default, PHP_URL_PATH);

        return rtrim($base, '/').(is_string($path) ? $path : '');
    }

    /**
     * Webhook prüfen: Zustand und Adresse zeigen, dann eine signierte Probe-Meldung an die eigene Adresse schicken
     * (gleiches Format und gleiche Signatur wie DeepSign). Das Intranet nimmt sie nur mit dem richtigen Geheimnis an.
     */
    private function webhookCheck(PluginContext $ctx): ActionResult
    {
        $id = $ctx->manifest->id();
        $url = self::webhookUrl($ctx, route('signatures.callback', ['provider' => $id]));
        $on = $ctx->setting('webhook', false) === true || (bool) config('intranet.signatures.webhook');

        $secret = Str::random(40);
        Cache::put(Signatures::WEBHOOK_TEST.':'.$id, $secret, now()->addMinutes(2));
        $body = (string) json_encode(['eventType' => 'document-completed', 'documentId' => Signatures::WEBHOOK_TEST, 'signeeId' => null, 'timestamp' => now()->toIso8601String()]);
        $ctx->log('POST '.$url.' (Probe-Meldung)');
        try {
            $response = Http::connectTimeout(5)->timeout(15)
                ->withHeaders(['X-DeepSign-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $secret)])
                ->withBody($body, 'application/json')
                ->post($url);
            $ctx->log("→ HTTP {$response->status()}");
            $result = match ($response->status()) {
                204 => 'angenommen – der Webhook funktioniert',
                401 => 'abgelehnt (Signatur) – HTTP 401',
                404 => 'nicht gefunden – Adresse prüfen (HTTP 404)',
                default => 'unerwartete Antwort (HTTP '.$response->status().')',
            };
            $ok = $response->status() === 204;
        } catch (Throwable $e) {
            $result = 'nicht erreichbar: '.mb_substr($e->getMessage(), 0, 160);
            $ok = false;
        } finally {
            Cache::forget(Signatures::WEBHOOK_TEST.':'.$id);
        }

        $rows = [
            ['what' => 'Webhook beim Versenden', 'value' => $on ? 'eingeschaltet' : 'ausgeschaltet – Status wird alle 15 Minuten abgeglichen'],
            ['what' => 'Adresse', 'value' => $url],
            ['what' => 'Ereignisse', 'value' => 'signee-completed, document-completed'],
            ['what' => 'Probe-Meldung', 'value' => $result],
        ];
        if (! str_starts_with($url, 'https://')) {
            $rows[] = ['what' => 'Hinweis', 'value' => 'DeepSign erreicht nur eine öffentliche https-Adresse. In den Einstellungen die Webhook-Adresse eintragen.'];
        }
        $message = $ok
            ? ($on ? 'Webhook bereit.' : 'Probe angenommen. Zum Verwenden in den Einstellungen «Webhook verwenden» einschalten.')
            : 'Probe-Meldung nicht angenommen.';

        return ActionResult::table($message, [
            ['key' => 'what', 'label' => 'Angabe'],
            ['key' => 'value', 'label' => 'Wert'],
        ], $rows);
    }

    // ---------- Dokumente ablegen (#94) ----------

    /**
     * Unterschriebene Dokumente herunterladen und in «Dokumente & Links» ablegen. Ohne Dokument-ID alle
     * unterschriebenen, die noch fehlen. Herkunft «deepsign:<ID>» verhindert Doppeltes.
     *
     * @param  array<string, mixed>  $options
     */
    private function archive(PluginContext $ctx, array $options): ActionResult
    {
        if ($ctx->user !== null && Gate::forUser($ctx->user)->denies('manage-content')) {
            return ActionResult::warning('Ablegen braucht das Recht «Inhalte verwalten».');
        }
        $categories = (array) config('intranet.document_categories', []);
        $category = (string) ($options['category'] ?? 'unterschriften');
        if (! array_key_exists($category, $categories)) {
            $category = array_key_exists('unterschriften', $categories) ? 'unterschriften' : (string) array_key_first($categories);
        }
        $ability = ($options['visibility'] ?? 'verwaltung') === 'alle' ? null : 'manage-signatures';

        $documentId = trim((string) ($options['documentId'] ?? ''));
        if ($documentId !== '') {
            $details = $ctx->call('document', ['documentId' => $documentId]);
            if (! $details->successful()) {
                throw new RuntimeException(self::failure($details));
            }
            $docs = [['documentId' => $documentId, 'documentName' => (string) $details->json('documentName'), 'documentStatus' => (string) $details->json('documentStatus')]];
        } else {
            $overview = $ctx->call('overview', ['limit' => 100, 'order' => 'creationTime desc', 'filterDocumentStatus' => 'signed']);
            if (! $overview->successful()) {
                throw new RuntimeException(self::failure($overview));
            }
            $docs = array_values(array_filter((array) $overview->json('documents'), 'is_array'));
        }

        $store = app(DocumentAccess::class);
        $rows = [];
        $stored = 0;
        foreach ($docs as $doc) {
            $id = (string) ($doc['documentId'] ?? '');
            $name = trim((string) ($doc['documentName'] ?? '')) ?: $id;
            $status = (string) ($doc['documentStatus'] ?? '');
            $existing = $id !== '' ? $store->bySource('deepsign:'.$id) : null;
            if ($id === '') {
                continue;
            } elseif ($existing !== null) {
                $result = 'bereits abgelegt («'.(string) $existing['title'].'»)';
            } elseif ($status !== 'signed') {
                $result = 'nicht abgelegt – Status '.self::statusLabel($status);
            } else {
                $pdf = $this->signatureDownload($ctx, $id);
                if ($pdf === null) {
                    $result = 'PDF nicht verfügbar';
                } else {
                    $fileName = str_ends_with(strtolower($name), '.pdf') ? $name : $name.'.pdf';
                    $title = preg_replace('/\.pdf$/i', '', $name) ?: $name;
                    $document = $store->store($title, $fileName, $pdf, $category, $ability, 'deepsign:'.$id, 'Unterschrieben mit DeepSign.', $ctx->user);
                    app(AuditLog::class)->record('documents', null, array_diff_key($document, ['file_path' => true]), 'DeepSign: Dokument abgelegt', $ctx->user);
                    $result = 'abgelegt';
                    $stored++;
                }
            }
            $rows[] = ['name' => $name, 'status' => self::statusLabel($status), 'result' => $result];
        }

        $message = ($ctx->demo() ? 'Demo-Modus: ' : '').$stored.' von '.count($rows).' Dokumenten abgelegt'
            .' (Kategorie «'.(string) ($categories[$category] ?? $category).'», '.($ability === null ? 'für alle sichtbar' : 'nur Verwaltung').').';

        return ActionResult::table($message, [
            ['key' => 'name', 'label' => 'Dokument'],
            ['key' => 'status', 'label' => 'Status bei DeepSign'],
            ['key' => 'result', 'label' => 'Ergebnis'],
        ], $rows)->withLink('Dokumente & Links öffnen', url('/dokumente'));
    }

    // ---------- Schnittstelle «Unterschriften» (App\Support\Signatures) ----------

    public function signatureSend(PluginContext $ctx, array $request, string $pdf, ?array $callback): array
    {
        $data = array_filter([
            'documentName' => $request['title'],
            'initiatorAliasName' => (string) $ctx->setting('initiator_alias', 'Sekundarschule Bülach'),
            'sendMail' => 'others',
            'visualSignatureRequirement' => 'optional',
            'signatureMode' => (string) $ctx->setting('signature_mode', 'timestamp'),
            'jurisdiction' => (string) $ctx->setting('jurisdiction', 'zertes'),
            'comment' => $request['comment'] ?? null,
            'companyId' => $ctx->setting('company_id'),
        ], fn (mixed $v): bool => $v !== null && $v !== '');
        $callback = self::webhook($ctx, $callback);
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
        // Unterschriftsfelder gelten je Datei des Dokuments (fileSignatureFields: fileId → Positionen)
        $fileId = $upload->json('files.0.fileId');

        try {
            $ids = [];
            foreach ($request['signees'] as $signee) {
                $position = $signee['position'] ?? null;
                $added = $ctx->call('signee-add', ['documentId' => $documentId], [
                    'signeeType' => 'sign',
                    'email' => $signee['email'],
                    'signOrder' => $signee['order'],
                    'language' => 'de',
                    'fileSignatureFields' => is_string($fileId) && $position !== null
                        ? [$fileId => ['autographPositions' => [$position]]]
                        : (object) [],
                ]);
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
        self::expect($ctx->call('document-start', ['documentId' => $providerId]));
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
                'signed', 'approved' => 'unterschrieben',
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
            if (! is_string($file) && self::demoStatusOf($providerId) === 'signed') {
                // Demo-Dokument aus der Übersicht (nicht selbst versendet): erfundenes PDF
                $pdf = new SimplePdf('Demo – unterschriebenes Dokument');
                $pdf->addPage();
                $pdf->text(56, 780, 'Demo-Modus: unterschriebenes Dokument aus DeepSign', 14, true);
                $pdf->text(56, 756, 'Dokument-ID: '.$providerId);
                $pdf->text(56, 740, 'Erfundener Inhalt – nichts wurde an DeepSign gesendet.');

                return $pdf->output();
            }

            return is_string($file) ? $file : null;
        }

        $details = $ctx->call('document', ['documentId' => $providerId]);
        // files[0].fileUrl (documentUrl ist veraltet); gilt ca. 1 Minute, Abruf ohne Authorization-Header
        $url = $details->json('files.0.fileUrl') ?? $details->json('documentUrl');
        if (! $details->successful() || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            return null;
        }
        $ctx->log('GET fileUrl (ohne Anmeldung)');
        $file = Http::connectTimeout(5)->timeout(60)->get($url);
        $ctx->log("→ HTTP {$file->status()}");

        return $file->successful() ? $file->body() : null;
    }

    public function signatureRemind(PluginContext $ctx, string $providerId, string $signeeId): void
    {
        self::expect($ctx->call('signee-resend', ['documentId' => $providerId, 'signeeId' => $signeeId]));
    }

    public function signatureWithdraw(PluginContext $ctx, string $providerId): void
    {
        self::expect($ctx->call('document-withdraw', ['documentId' => $providerId], ['sendMail' => 'others']));
    }

    public function signatureDelete(PluginContext $ctx, string $providerId): void
    {
        self::expect($ctx->call('document-delete', ['documentId' => $providerId]));
    }

    public function signatureAccessUrl(PluginContext $ctx, string $providerId): ?string
    {
        $response = $ctx->call('document-access-url', ['documentId' => $providerId]);
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }
        // Antwort ist die Adresse als Text (bzw. als JSON-Zeichenkette)
        $json = $response->json();
        $url = is_string($json) ? $json : trim($response->body(), " \n\r\t\"");

        return str_starts_with($url, 'https://') ? $url : null;
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
     * @return array<string, mixed>
     */
    private static function demoFile(string $name, ?string $url): array
    {
        return [
            'fileId' => self::DEMO_FILE, 'fileName' => $name, 'mimeType' => 'application/pdf',
            'fileUrl' => $url ?? 'https://api.sign.deepbox.swiss/demo/file/'.self::DEMO_FILE, 'thumbnailUrl' => null,
            'creationTime' => '2026-11-01T08:00:00Z', 'filePolicy' => ['canDelete' => false, 'canUpdate' => true],
        ];
    }

    /**
     * Simulierte Antworten der übrigen Endpunkte (Benutzer, Siegel, Reihenfolge, Gruppen, Dateien, Anhänge, Hash- und
     * Datei-Service). Schreibende ohne Inhalt antworten wie DeepSign mit 204.
     *
     * @param  array<string, string>  $pathParams
     * @param  array<string, mixed>|null  $body
     * @return array{status: int, json?: mixed}
     */
    private static function demoGeneric(string $endpoint, array $pathParams, ?array $body, PluginContext $ctx): array
    {
        $attachment = ['attachmentId' => self::DEMO_ATTACHMENT, 'name' => 'Beilage Spesen.pdf', 'size' => 48213, 'mimeType' => 'application/pdf'];

        return match ($endpoint) {
            'user' => ['status' => 200, 'json' => ['email' => '0f1e2d3c-demo@in.deepcloud.swiss', 'userId' => '0f1e2d3c-4b5a-4c6d-8e7f-9a0b1c2d3e4f', 'location' => 'Bülach', 'optionalText' => 'Schulverwaltung', 'autographUrl' => null, 'deepSignStorage' => true, 'companiesWithDisabledStorage' => []]],
            'seals' => ['status' => 200, 'json' => [['sealId' => '2c3d4e5f-6a7b-4c8d-9e0f-1a2b3c4d5e6f', 'sealKey' => 'siegel-schulverwaltung', 'displayName' => 'Sekundarschule Bülach (Demo)', 'companyId' => '0c1d2e3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f']]],
            'authority-services' => ['status' => 200, 'json' => [
                'ras' => ['available' => true, 'phoneNumber' => '+4179•••0000'],
                'did' => ['available' => false],
            ]],
            'sign-order' => ['status' => 200, 'json' => ['context' => 'Reihenfolge: 1. Muster Anna, 2. Beispiel Beat (Demo)']],
            'file' => ($pathParams['fileId'] ?? '') === self::DEMO_FILE
                ? ['status' => 200, 'json' => self::demoFile('Monatsrapport Oktober 2026 – Muster Anna.pdf', null)]
                : self::demoError(404, 'File not found.'),
            'attachments' => ['status' => 200, 'json' => [$attachment]],
            'attachment-add' => ['status' => 201, 'json' => ['attachmentId' => 'demo-'.Str::lower(Str::random(12)), 'name' => (string) (($body['_file'] ?? [])['name'] ?? 'Anhang.pdf'), 'size' => strlen((string) (($body['_file'] ?? [])['contents'] ?? '')), 'mimeType' => 'application/pdf']],
            'attachment-download-url' => ($pathParams['attachmentId'] ?? '') === self::DEMO_ATTACHMENT
                ? ['status' => 200, 'json' => 'https://api.sign.deepbox.swiss/demo/attachment/'.self::DEMO_ATTACHMENT]
                : self::demoError(404, 'Attachment not found.'),
            'file-add' => ['status' => 201, 'json' => self::demoFile((string) (($body['_file'] ?? [])['name'] ?? 'Datei.pdf'), null)],
            'document-deepbox' => trim((string) ($body['deepboxDocumentId'] ?? '')) === ''
                ? self::demoError(400, 'deepboxDocumentId is required.')
                : ['status' => 201, 'json' => self::demoDocument('draft', $ctx, $body, 'demo-deepbox-1')],
            'hash-sign' => ($body['hashes'] ?? []) === []
                ? self::demoError(400, 'hashes must not be empty.')
                : ['status' => 202, 'json' => ['hashKey' => self::DEMO_KEY, 'signStatus' => 'pending', 'otpCallbackUrl' => null, 'oauth2LoginUrl' => null, 'correlationCode' => 'DEMO-42']],
            'hash-seal' => ['status' => 201, 'json' => ['signStatus' => 'signed', 'signatureData' => ['signatures' => [['hashId' => 'rapport-2026-10', 'signature' => 'ZGVtby1zaWduYXR1cmU=']]], 'lastError' => null]],
            'file-sign' => ['status' => 202, 'json' => ['fileKey' => self::DEMO_KEY, 'signStatus' => 'in-progress', 'otpCallbackUrl' => null, 'oauth2LoginUrl' => null, 'correlationCode' => 'DEMO-42']],
            'file-seal', 'file-seal-xml' => ['status' => 201, 'json' => 'Demo: versiegelte Datei (im echten Betrieb Binärdaten)'],
            'hash-status' => ['status' => 200, 'json' => ['signStatus' => 'signed', 'signatureData' => ['signatures' => [['hashId' => 'rapport-2026-10', 'signature' => 'ZGVtby1zaWduYXR1cmU=']]], 'lastError' => null]],
            'file-status' => ['status' => 200, 'json' => ['fileKey' => self::DEMO_KEY, 'signStatus' => 'signed']],
            'file-download' => ['status' => 200, 'json' => 'Demo: unterschriebene Datei (im echten Betrieb Binärdaten)'],
            'signature-preview' => ['status' => 200, 'json' => 'Demo: Vorschau der Unterschrift (im echten Betrieb ein Bild)'],
            'user-update', 'signee-group-update', 'signee-group-signature-field', 'signee-group-order', 'sign-order-update', 'sign-order-delete',
            'sign-order-initiator', 'observer-update', 'file-update', 'file-delete', 'file-order', 'attachment-update', 'attachment-delete',
            'attachment-order', 'autograph-update', 'autograph-delete' => ['status' => 204],
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
            'files' => [self::demoFile($name, $status === 'signed' ? 'https://api.sign.deepbox.swiss/demo/download/'.$id : null)],
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
