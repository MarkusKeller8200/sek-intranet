<?php

namespace Plugins\Jamf;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use App\Plugins\Support\Tabular;
use RuntimeException;

/**
 * Jamf Pro API (API-Client mit Client Credentials).
 * Quelle: developer.jamf.com; nicht vollständig verifizierte Teile sind im Manifest als «partial» markiert.
 */
class Plugin extends BasePlugin
{
    public function test(PluginContext $ctx): array
    {
        $response = $ctx->call('version');
        if (! $response->successful()) {
            return ['ok' => false, 'message' => "Jamf antwortet mit HTTP {$response->status()}."];
        }

        return ['ok' => true, 'message' => 'Verbunden mit Jamf Pro '.((string) ($response->json('version') ?? '?')).'.'];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        return match ($action) {
            'devices-of-person' => $this->devicesOfPerson($ctx, trim((string) ($options['email'] ?? ''))),
            'overview' => $this->overview($ctx, trim((string) ($options['filter'] ?? ''))),
            'refresh-inventory' => $this->refreshInventory($ctx, trim((string) ($options['serial'] ?? ''))),
            'assign-user' => $this->assignUser($ctx, trim((string) ($options['serial'] ?? '')), trim((string) ($options['email'] ?? ''))),
            default => parent::action($action, $ctx, $options),
        };
    }

    public function widget(string $widget, PluginContext $ctx): array
    {
        $ipads = $ctx->call('mobile-devices', ['page-size' => 1])->json('totalCount');
        $managed = $ctx->call('mobile-devices', ['page-size' => 1, 'filter' => 'managed==true'])->json('totalCount');
        $macs = $ctx->call('computers', ['page-size' => 1])->json('totalCount');

        return ['items' => array_values(array_filter([
            is_numeric($ipads) ? ['label' => 'iPads', 'value' => (int) $ipads] : null,
            is_numeric($managed) ? ['label' => 'davon verwaltet', 'value' => (int) $managed] : null,
            is_numeric($macs) ? ['label' => 'Macs', 'value' => (int) $macs] : null,
        ]))];
    }

    /**
     * Person deaktiviert oder gelöscht: zugewiesene Geräte melden (ändert nichts in Jamf).
     */
    public function onEvent(string $event, array $data, PluginContext $ctx): void
    {
        $email = (string) ($data['email'] ?? '');
        if ($email === '' || ($event === 'person.updated' && ($data['active'] ?? true) === true)) {
            return;
        }

        $devices = $this->mobileDevices($ctx, 'emailAddress=='.self::quote($email));
        $ctx->log(count($devices) > 0
            ? "{$email} ist nicht mehr aktiv, hat aber ".count($devices).' iPad(s) in Jamf: '.implode(', ', array_map(fn (array $d): string => (string) ($d['name'] ?? $d['serial']), $devices)).' – Rückgabe prüfen.'
            : "{$email}: keine iPads in Jamf zugewiesen.");
    }

    private function devicesOfPerson(PluginContext $ctx, string $email): ActionResult
    {
        if ($email === '') {
            return ActionResult::warning('Bitte E-Mail oder Benutzername angeben.');
        }

        $field = str_contains($email, '@') ? 'emailAddress' : 'username';
        $mobiles = $this->mobileDevices($ctx, $field.'=='.self::quote($email));

        $computerField = str_contains($email, '@') ? 'userAndLocation.email' : 'userAndLocation.username';
        $response = $ctx->call('computers', ['section' => 'GENERAL', 'filter' => $computerField.'=='.self::quote($email), 'page-size' => 100]);
        $computers = [];
        foreach ((array) ($response->successful() ? $response->json('results') : []) as $c) {
            if (is_array($c)) {
                $computers[] = [
                    'type' => 'Mac',
                    'name' => Tabular::get($c, 'general.name'),
                    'serial' => Tabular::get($c, 'hardware.serialNumber'),
                    'os' => Tabular::get($c, 'operatingSystem.version'),
                    'last' => Tabular::get($c, 'general.lastContactTime'),
                    'managed' => Tabular::get($c, 'general.remoteManagement.managed'),
                ];
            }
        }

        $rows = [...array_map(fn (array $d): array => ['type' => 'iPad'] + $d, $mobiles), ...$computers];

        return ActionResult::table(count($rows).' Gerät(e) für '.$email.'.', self::columns(), $rows);
    }

    private function overview(PluginContext $ctx, string $filter): ActionResult
    {
        $devices = $this->mobileDevices($ctx, $filter === '' ? null : $filter, 500);

        return ActionResult::table(count($devices).' iPads (höchstens 500).', self::columns(), array_map(fn (array $d): array => ['type' => 'iPad'] + $d, $devices));
    }

    private function refreshInventory(PluginContext $ctx, string $serial): ActionResult
    {
        $ctx->requireWrite();
        $device = $this->bySerial($ctx, $serial);

        if (($device['managementId'] ?? null) === null) {
            throw new RuntimeException('managementId des Geräts nicht gefunden – Gerät verwaltet?');
        }

        $response = $ctx->call('mdm-command', [], [
            'clientData' => [['managementId' => $device['managementId']]],
            'commandData' => ['commandType' => 'DEVICE_INFORMATION'],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Jamf antwortet mit HTTP {$response->status()}.");
        }

        return ActionResult::ok("Befehl an «{$device['name']}» gesendet. Neue Daten erscheinen, sobald das Gerät sich meldet.");
    }

    private function assignUser(PluginContext $ctx, string $serial, string $email): ActionResult
    {
        $ctx->requireWrite();

        $person = $ctx->users()->findByEmail($email)
            ?? throw new RuntimeException("{$email} ist nicht im Personenverzeichnis des Intranets.");
        $device = $this->bySerial($ctx, $serial);

        $response = $ctx->call('mobile-device-update', ['id' => (string) $device['id']], [
            'location' => [
                'username' => strstr($email, '@', true) ?: $email,
                'realName' => (string) $person['name'],
                'emailAddress' => $email,
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Jamf antwortet mit HTTP {$response->status()}.");
        }

        return ActionResult::ok("«{$device['name']}» ist jetzt {$person['name']} zugewiesen.");
    }

    /**
     * @return array<string, mixed>
     */
    private function bySerial(PluginContext $ctx, string $serial): array
    {
        if ($serial === '') {
            throw new RuntimeException('Bitte Seriennummer angeben.');
        }

        return $this->mobileDevices($ctx, 'serialNumber=='.self::quote($serial))[0]
            ?? throw new RuntimeException("Kein iPad mit Seriennummer {$serial} in Jamf.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mobileDevices(PluginContext $ctx, ?string $filter, int $max = 200): array
    {
        $devices = [];
        $page = 0;

        do {
            // «section» mehrfach: Abfrage selbst zusammensetzen (Guzzle würde sie sonst ersetzen).
            $query = 'section=GENERAL&section=HARDWARE&section=USER_AND_LOCATION&'
                .http_build_query(array_filter(['page' => $page, 'page-size' => 100, 'sort' => 'displayName:asc', 'filter' => $filter], fn ($v): bool => $v !== null), '', '&', PHP_QUERY_RFC3986);
            $response = $ctx->http()->get('/api/v2/mobile-devices/detail?'.$query);
            $ctx->log("GET /api/v2/mobile-devices/detail Seite {$page} → HTTP {$response->status()}");

            if (! $response->successful()) {
                throw new RuntimeException("Jamf antwortet mit HTTP {$response->status()}.");
            }

            $results = (array) ($response->json('results') ?? []);
            foreach ($results as $d) {
                if (! is_array($d)) {
                    continue;
                }
                $devices[] = [
                    'id' => $d['mobileDeviceId'] ?? $d['id'] ?? null,
                    'managementId' => $d['managementId'] ?? Tabular::get($d, 'general.managementId'),
                    'name' => Tabular::get($d, 'general.displayName') ?? $d['name'] ?? null,
                    'serial' => Tabular::get($d, 'hardware.serialNumber') ?? $d['serialNumber'] ?? null,
                    'user' => Tabular::get($d, 'userAndLocation.realName') ?? Tabular::get($d, 'userAndLocation.username'),
                    'os' => Tabular::get($d, 'general.osVersion'),
                    'last' => Tabular::get($d, 'general.lastInventoryUpdateDate'),
                    'managed' => Tabular::get($d, 'general.managed'),
                ];
            }

            $total = (int) ($response->json('totalCount') ?? 0);
            $page++;
        } while (count($devices) < min($total, $max) && $results !== []);

        return array_slice($devices, 0, $max);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private static function columns(): array
    {
        return [
            ['key' => 'type', 'label' => 'Art'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'serial', 'label' => 'Seriennummer'],
            ['key' => 'user', 'label' => 'Person'],
            ['key' => 'os', 'label' => 'System'],
            ['key' => 'last', 'label' => 'Letzte Meldung'],
            ['key' => 'managed', 'label' => 'Verwaltet'],
        ];
    }

    /** RSQL-Wert in Anführungszeichen (Leerzeichen, Sonderzeichen). */
    private static function quote(string $value): string
    {
        return '"'.str_replace('"', '\"', $value).'"';
    }
}
