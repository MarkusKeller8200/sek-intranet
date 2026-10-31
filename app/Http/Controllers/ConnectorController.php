<?php

namespace App\Http\Controllers;

use App\Data\Contracts\UserRepository;
use App\Plugins\PluginManager;
use App\Plugins\PluginManifest;
use App\Support\ModuleNavigation;
use App\Support\PersonLinks;
use App\Support\PersonTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Verbindung Intranet ⇄ Tab einer Fachanwendung (Browser-Erweiterung, docs/KONZEPT-BROWSER-CONNECTOR.md).
 *
 * - Kontext: Die Adresse, die im Tab offen ist, enthält oft IDs (Person, Klasse). Sie werden mit der
 *   Datenherkunft (PersonLinks, System der Fachanwendung) abgeglichen → passende Personen im Intranet.
 *   Die Adresse wird nicht gespeichert und nicht protokolliert.
 * - Abrufe: lesende Endpunkte der Plugins für dieselbe Adresse, die die Erweiterung im Tab mit der
 *   Anmeldung der Person ausführt (der Server ruft dabei nichts auf).
 */
class ConnectorController extends Controller
{
    public function context(Request $request, string $key, ModuleNavigation $modules, PersonLinks $links, UserRepository $users): JsonResponse
    {
        $module = $modules->embedded($key, $request->user());
        abort_if($module === null, 404);
        $data = $request->validate(['url' => ['required', 'string', 'max:2000', 'regex:#^https://#']]);

        $system = $module['system'];
        $candidates = self::ids((string) $data['url']);
        if ($system === null || $candidates === []) {
            return response()->json(['system' => $system, 'ids' => $candidates, 'people' => []]);
        }

        $people = [];
        foreach ($candidates as $id) {
            foreach (['source', 'target'] as $direction) {
                $personId = $links->findPerson($system, $id, $direction);
                if ($personId !== null && ! isset($people[$personId])) {
                    $person = $users->find($personId);
                    if ($person !== null) {
                        $people[$personId] = $person + ['_matched' => $id];
                    }
                }
            }
        }

        $visible = PersonTypes::visibleFor(array_values($people), $request->user());

        return response()->json([
            'system' => $system,
            'ids' => $candidates,
            'people' => array_map(fn (array $p): array => [
                'id' => $p['id'],
                'name' => (string) ($p['name'] ?? ''),
                'type' => PersonTypes::of($p),
                'class' => $p['class_name'] ?? null,
                'device' => $p['device_inventory'] ?? null,
                'matched' => $p['_matched'],
                'href' => '/personen/'.$p['id'],
            ], $visible),
        ]);
    }

    /**
     * Mögliche IDs aus einer Adresse: Zahlen, UUIDs und längere Kennungen in Pfad und Abfrage.
     *
     * @return list<string>
     */
    public static function ids(string $url): array
    {
        $parts = parse_url($url);
        $values = array_merge(explode('/', (string) ($parts['path'] ?? '')), explode('/', (string) ($parts['fragment'] ?? '')));
        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach ($query as $value) {
            if (is_string($value)) {
                $values[] = $value;
            }
        }

        $ids = [];
        foreach ($values as $value) {
            $value = rawurldecode(trim($value));
            if (preg_match('/^\d{2,}$/', $value) === 1
                || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1
                || preg_match('/^(?=.*\d)[A-Za-z0-9_-]{4,64}$/', $value) === 1) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique(array_slice($ids, 0, 20)));
    }

    /**
     * Lesende Plugin-Endpunkte für die Adresse der Fachanwendung (für «Abruf über die Anmeldung im Tab»).
     * Nur für die Fachstelle ICT und die Administration.
     *
     * @return list<array<string, mixed>>
     */
    public static function endpointsFor(?string $url, ?object $user): array
    {
        if ($url === null || $user === null || ! Gate::forUser($user)->allows('use-ict')) {
            return [];
        }
        $origin = self::origin($url);
        $plugins = app(PluginManager::class);
        $list = [];
        foreach ($plugins->all() as $id => ['manifest' => $manifest]) {
            $settings = $plugins->state($id)['settings'];
            $hosts = ['default' => (string) ($manifest->service()['base'] ?? $settings['base_url'] ?? '')] + $manifest->hosts();
            foreach ($manifest->endpoints() as $endpoint) {
                $host = (string) ($endpoint['host'] ?? 'default');
                if (self::origin($hosts[$host] ?? '') !== $origin || PluginManifest::endpointWrites($endpoint) || ($endpoint['status'] ?? 'full') === 'planned') {
                    continue;
                }
                $params = [];
                foreach ((array) ($endpoint['params'] ?? []) as $param) {
                    if (! is_array($param) || ! isset($param['name'])) {
                        continue;
                    }
                    // Vorgabe aus den Plugin-Einstellungen (nur nicht geheime Werte)
                    $setting = (string) ($param['setting'] ?? strtolower((string) preg_replace('/[A-Z]/', '_$0', (string) $param['name'])));
                    $value = in_array($setting, $manifest->secretKeys(), true) ? null : ($settings[$setting] ?? $param['default'] ?? null);
                    $params[] = ['name' => (string) $param['name'], 'value' => is_scalar($value) ? (string) $value : '', 'help' => $param['help'] ?? null];
                }
                $list[] = [
                    'plugin' => $manifest->name(),
                    'id' => (string) $endpoint['id'],
                    'label' => (string) ($endpoint['label'] ?? $endpoint['id']),
                    'method' => strtoupper((string) ($endpoint['method'] ?? 'GET')),
                    'path' => (string) $endpoint['path'],
                    'params' => $params,
                    'body' => $endpoint['body'] ?? null,
                ];
            }
        }

        return $list;
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        return isset($parts['host']) ? strtolower(($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')) : '';
    }
}
