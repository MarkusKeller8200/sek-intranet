<?php

namespace Plugins\Entra;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use App\Plugins\Support\PeopleImport;
use RuntimeException;

/**
 * Microsoft Entra ID über Microsoft Graph (App-Zugriff, Client Credentials).
 * Endpunkte und Berechtigungen siehe plugin.json; Quelle: learn.microsoft.com/graph.
 */
class Plugin extends BasePlugin
{
    private const USER_FIELDS = 'id,displayName,mail,userPrincipalName,jobTitle,department,businessPhones,mobilePhone,accountEnabled';

    public function test(PluginContext $ctx): array
    {
        $response = $ctx->call('organization');
        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Graph antwortet mit HTTP '.$response->status().'. Berechtigung Organization.Read.All erteilt?'];
        }

        $org = (array) ($response->json('value.0') ?? []);

        return ['ok' => true, 'message' => 'Verbunden mit «'.($org['displayName'] ?? '?').'».'];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        return match ($action) {
            'import-people' => $this->importPeople($ctx, $options),
            'lookup' => $this->lookup($ctx, (string) ($options['email'] ?? '')),
            'add-to-group' => $this->addToGroup($ctx, (string) ($options['email'] ?? ''), (string) ($options['group_id'] ?? '')),
            default => parent::action($action, $ctx, $options),
        };
    }

    public function widget(string $widget, PluginContext $ctx): array
    {
        $org = (array) ($ctx->call('organization')->json('value.0') ?? []);
        $users = $this->count($ctx, '/users/$count');
        $groups = $this->count($ctx, '/groups/$count');

        return [
            'items' => array_values(array_filter([
                ['label' => 'Tenant', 'value' => (string) ($org['displayName'] ?? '–')],
                $users !== null ? ['label' => 'Benutzer', 'value' => $users] : null,
                $groups !== null ? ['label' => 'Gruppen', 'value' => $groups] : null,
            ])),
        ];
    }

    public function onEvent(string $event, array $data, PluginContext $ctx): void
    {
        $email = (string) ($data['email'] ?? '');
        if ($email === '') {
            return;
        }

        $response = $ctx->call('user', ['id' => $email, '$select' => 'id,displayName,accountEnabled']);

        $ctx->log(match (true) {
            $response->successful() && $response->json('accountEnabled') === false => "{$email}: Konto in Entra ist deaktiviert.",
            $response->successful() => "{$email}: in Entra vorhanden.",
            $response->status() === 404 => "{$email}: nicht in Entra – Konto anlegen?",
            default => "{$email}: Abfrage nicht möglich (HTTP {$response->status()}).",
        });
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function importPeople(PluginContext $ctx, array $options): ActionResult
    {
        $groupId = trim((string) ($options['group_id'] ?? '')) ?: (string) $ctx->setting('group_id', '');
        $max = (int) $ctx->setting('max_users', 2000);

        $records = $groupId !== ''
            ? $this->collect($ctx, '/groups/'.rawurlencode($groupId).'/members/microsoft.graph.user', ['$select' => self::USER_FIELDS, '$top' => 999], $max, eventual: true)
            : $this->collect($ctx, '/users', ['$select' => self::USER_FIELDS, '$top' => 999], $max);

        if (($options['only_enabled'] ?? true) === true) {
            $records = array_values(array_filter($records, fn (array $u): bool => ($u['accountEnabled'] ?? true) === true));
        }

        $school = (string) $ctx->setting('default_school', 'gesamt');
        $people = array_map(fn (array $u): array => [
            'name' => $u['displayName'] ?? null,
            'email' => $u['mail'] ?? $u['userPrincipalName'] ?? null,
            'function' => $u['jobTitle'] ?? null,
            'phone' => ($u['businessPhones'][0] ?? null) ?: ($u['mobilePhone'] ?? null),
            'active' => $u['accountEnabled'] ?? true,
            'school' => $school,
        ], $records);

        $import = new PeopleImport($ctx->users());
        $rows = $import->plan($people, 'Entra');
        $ctx->log(count($records).' Konten aus Entra gelesen'.($groupId !== '' ? " (Gruppe {$groupId})" : '').'.');

        if (($options['_apply'] ?? false) === true) {
            $done = $import->apply($rows, array_values(array_map('strval', (array) ($options['_selected'] ?? []))));

            return ActionResult::ok("{$done['created']} Personen angelegt, {$done['updated']} geändert.");
        }

        $counts = array_count_values(array_column($rows, '_change'));

        return ActionResult::preview(
            sprintf('%d neu, %d mit Änderungen, %d gleich, %d nur im Intranet.', $counts['neu'] ?? 0, $counts['ändern'] ?? 0, $counts['gleich'] ?? 0, $counts['fehlt dort'] ?? 0),
            PeopleImport::columns(),
            $rows,
        );
    }

    private function lookup(PluginContext $ctx, string $email): ActionResult
    {
        if ($email === '') {
            return ActionResult::warning('Bitte E-Mail oder UPN angeben.');
        }

        $user = $ctx->call('user', ['id' => $email]);
        if ($user->status() === 404) {
            return ActionResult::warning("{$email} ist nicht in Entra.");
        }
        if (! $user->successful()) {
            throw new RuntimeException("Graph antwortet mit HTTP {$user->status()}.");
        }

        $data = (array) $user->json();
        $groups = (array) ($ctx->call('user-groups', ['id' => $email])->json('value') ?? []);

        $rows = [
            ['feld' => 'Name', 'wert' => $data['displayName'] ?? null],
            ['feld' => 'E-Mail', 'wert' => $data['mail'] ?? null],
            ['feld' => 'UPN', 'wert' => $data['userPrincipalName'] ?? null],
            ['feld' => 'Funktion', 'wert' => $data['jobTitle'] ?? null],
            ['feld' => 'Abteilung', 'wert' => $data['department'] ?? null],
            ['feld' => 'Konto aktiv', 'wert' => $data['accountEnabled'] ?? null],
            ['feld' => 'Objekt-ID', 'wert' => $data['id'] ?? null],
            ['feld' => 'Gruppen', 'wert' => implode(', ', array_filter(array_map(fn ($g): ?string => is_array($g) ? ($g['displayName'] ?? null) : null, $groups)))],
        ];

        return ActionResult::table('Gefunden.', [['key' => 'feld', 'label' => 'Feld'], ['key' => 'wert', 'label' => 'Wert']], $rows);
    }

    private function addToGroup(PluginContext $ctx, string $email, string $groupId): ActionResult
    {
        $ctx->requireWrite();

        if ($email === '' || $groupId === '') {
            return ActionResult::warning('E-Mail und Gruppen-ID angeben.');
        }

        $userId = $ctx->call('user', ['id' => $email, '$select' => 'id'])->json('id');
        if (! is_string($userId)) {
            return ActionResult::warning("{$email} ist nicht in Entra.");
        }

        $response = $ctx->call('group-add-member', ['id' => $groupId], [
            '@odata.id' => 'https://graph.microsoft.com/v1.0/directoryObjects/'.$userId,
        ]);

        return match (true) {
            $response->status() === 204 => ActionResult::ok("{$email} zur Gruppe hinzugefügt."),
            $response->status() === 400 && str_contains((string) $response->body(), 'already exist') => ActionResult::warning("{$email} ist bereits Mitglied."),
            $response->status() === 403 => throw new RuntimeException('Keine Berechtigung (GroupMember.ReadWrite.All erteilt?).'),
            default => throw new RuntimeException("Graph antwortet mit HTTP {$response->status()}."),
        };
    }

    /**
     * Alle Seiten eines Graph-Listenaufrufs (über @odata.nextLink).
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function collect(PluginContext $ctx, string $path, array $query, int $max, bool $eventual = false): array
    {
        $records = [];
        $request = $ctx->http();
        if ($eventual) {
            // OData-Cast (…/microsoft.graph.user) braucht ConsistencyLevel und $count.
            $request = $request->withHeaders(['ConsistencyLevel' => 'eventual']);
            $query['$count'] = 'true';
        }

        $ctx->log("GET {$path}");
        $response = $request->get($path, $query);

        while (true) {
            if (! $response->successful()) {
                throw new RuntimeException("Graph antwortet mit HTTP {$response->status()} ({$path}).");
            }

            foreach ((array) ($response->json('value') ?? []) as $record) {
                if (is_array($record)) {
                    $records[] = $record;
                }
            }

            // Feldname enthält einen Punkt: nicht über json('…') lesen (würde als Pfad gedeutet).
            $json = $response->json();
            $next = is_array($json) ? ($json['@odata.nextLink'] ?? null) : null;
            if (! is_string($next) || count($records) >= $max) {
                break;
            }

            $ctx->log('weitere Seite …');
            $response = $request->get($next);
        }

        return array_slice($records, 0, $max);
    }

    private function count(PluginContext $ctx, string $path): ?int
    {
        $response = $ctx->http()->withHeaders(['ConsistencyLevel' => 'eventual'])->get($path);

        return $response->successful() && is_numeric(trim($response->body())) ? (int) trim($response->body()) : null;
    }
}
