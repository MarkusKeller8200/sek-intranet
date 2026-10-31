<?php

namespace App\Plugins;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Findet, verwaltet und startet Plugins.
 *
 * Plugins liegen nur im Repository (plugins/<Ordner>/plugin.json + Plugin.php) und
 * werden per CI geprüft. Im Intranet werden sie nur ein-/ausgeschaltet und eingerichtet.
 * Zustand: Sammlung «plugin_states» (Zugangsdaten mit APP_KEY verschlüsselt),
 * Läufe: Sammlung «plugin_runs» (Protokoll, Zugangsdaten geschwärzt).
 */
final class PluginManager
{
    private const STATES = 'plugin_states';

    private const RUNS = 'plugin_runs';

    private const MAX_RUNS = 300;

    /** @var array<string, array{manifest: PluginManifest, plugin: BasePlugin}>|null */
    private ?array $plugins = null;

    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly JsonStore $store,
        private readonly UserRepository $users,
        private readonly string $path,
    ) {}

    /**
     * @return array<string, array{manifest: PluginManifest, plugin: BasePlugin}>
     */
    public function all(): array
    {
        if ($this->plugins !== null) {
            return $this->plugins;
        }

        $this->plugins = [];
        foreach (glob($this->path.'/*/plugin.json') ?: [] as $file) {
            try {
                $manifest = PluginManifest::fromFile($file);
                $class = $manifest->className();

                if (! class_exists($class) || ! is_subclass_of($class, BasePlugin::class)) {
                    throw new InvalidArgumentException("{$class} fehlt oder erbt nicht von BasePlugin.");
                }

                $this->plugins[$manifest->id()] = ['manifest' => $manifest, 'plugin' => new $class];
            } catch (Throwable $e) {
                $this->errors[] = $e->getMessage();
                Log::warning('Plugin nicht geladen: '.$e->getMessage());
            }
        }

        ksort($this->plugins);

        return $this->plugins;
    }

    /**
     * @return list<string>
     */
    public function loadErrors(): array
    {
        $this->all();

        return $this->errors;
    }

    /** Ordner mit den Plugins (plugins/). */
    public function path(): string
    {
        return $this->path;
    }

    public function manifest(string $id): PluginManifest
    {
        return ($this->all()[$id] ?? throw new InvalidArgumentException("Plugin «{$id}» nicht gefunden."))['manifest'];
    }

    /**
     * @return array{enabled: bool, allow_write: bool, settings: array<string, mixed>, secrets: array<string, string>}
     */
    public function state(string $id): array
    {
        $record = $this->store->find(self::STATES, $id) ?? [];

        return [
            'enabled' => ($record['enabled'] ?? false) === true,
            'allow_write' => ($record['allow_write'] ?? false) === true,
            'settings' => is_array($record['settings'] ?? null) ? $record['settings'] : [],
            'secrets' => is_array($record['secrets'] ?? null) ? array_map('strval', $record['secrets']) : [],
        ];
    }

    public function isConfigured(string $id): bool
    {
        $state = $this->state($id);

        foreach ($this->manifest($id)->settings() as $setting) {
            if (($setting['required'] ?? false) !== true) {
                continue;
            }
            $key = (string) $setting['key'];
            $set = in_array($key, $this->manifest($id)->secretKeys(), true)
                ? ($state['secrets'][$key] ?? '') !== ''
                : ($state['settings'][$key] ?? '') !== '' && ($state['settings'][$key] ?? null) !== null;
            if (! $set) {
                return false;
            }
        }

        return true;
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $this->manifest($id);
        $this->saveState($id, ['enabled' => $enabled]);
    }

    /**
     * Einstellungen speichern. Leere Passwortfelder = unverändert; «_clear_secrets» löscht alle.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveSettings(string $id, array $input): void
    {
        $manifest = $this->manifest($id);
        $state = $this->state($id);
        $secretKeys = $manifest->secretKeys();
        $settings = $state['settings'];
        $secrets = ($input['_clear_secrets'] ?? false) === true ? [] : $state['secrets'];

        foreach ($manifest->settings() as $setting) {
            $key = (string) $setting['key'];
            $value = $input[$key] ?? null;

            if (in_array($key, $secretKeys, true)) {
                if (is_string($value) && trim($value) !== '') {
                    $secrets[$key] = Crypt::encryptString(trim($value));
                }

                continue;
            }

            $settings[$key] = match ($setting['type']) {
                'check' => (bool) $value,
                'number' => $value === null || $value === '' ? null : (float) $value,
                default => is_scalar($value) ? trim((string) $value) : null,
            };

            if (($setting['type'] ?? '') === 'url' && is_string($settings[$key]) && $settings[$key] !== ''
                && ! str_starts_with($settings[$key], 'https://')) {
                throw new InvalidArgumentException("«{$setting['label']}» muss mit https:// beginnen.");
            }
        }

        $this->saveState($id, [
            'settings' => $settings,
            'secrets' => $secrets,
            'allow_write' => (bool) ($input['_allow_write'] ?? $state['allow_write']),
        ]);

        Cache::forget("plugin-widgets:{$id}");
    }

    public function context(string $id, ?IntranetUser $user): PluginContext
    {
        $state = $this->state($id);
        $secrets = [];

        foreach ($state['secrets'] as $key => $encrypted) {
            try {
                $secrets[$key] = Crypt::decryptString($encrypted);
            } catch (DecryptException) {
                // z. B. nach neuem APP_KEY: Zugangsdaten müssen neu eingegeben werden
            }
        }

        return new PluginContext($this->manifest($id), $state['settings'], $secrets, $state['allow_write'], $user, $this->users);
    }

    /**
     * @return array<string, mixed>
     */
    public function test(string $id, ?IntranetUser $user): array
    {
        return $this->guarded($id, $user, 'test', function (PluginContext $ctx) use ($id): array {
            $result = $this->all()[$id]['plugin']->test($ctx);

            return ['ok' => $result['ok'], 'message' => $result['message'], 'status' => $result['ok'] ? 'success' : 'error'];
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function runAction(string $id, string $actionId, array $options, ?IntranetUser $user): array
    {
        $action = $this->manifest($id)->action($actionId)
            ?? throw new InvalidArgumentException("Aktion «{$actionId}» nicht gefunden.");

        $this->requireReady($id);

        $applying = ($options['_apply'] ?? false) === true;
        if (($action['kind'] ?? 'read') === 'write' || $applying) {
            if (! $this->state($id)['allow_write']) {
                throw new RuntimeException('Schreibende Aktionen sind für dieses Plugin ausgeschaltet (Einstellungen).');
            }
        }

        return $this->guarded($id, $user, $actionId.($applying ? ' (übernehmen)' : ''), function (PluginContext $ctx) use ($id, $actionId, $options): array {
            @set_time_limit(180);

            return $this->all()[$id]['plugin']->action($actionId, $ctx, $options)->toArray();
        });
    }

    /**
     * API-Tester: Endpunkt mit Parametern aufrufen. Nicht-GET nur mit Schreibfreigabe.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    public function apiCall(string $id, string $endpointId, array $params, ?array $body, ?IntranetUser $user): array
    {
        $this->requireReady($id);

        return $this->guarded($id, $user, "API {$endpointId}", function (PluginContext $ctx) use ($endpointId, $params, $body): array {
            $start = microtime(true);
            $response = $ctx->call($endpointId, $params, $body);
            $raw = $response->body();
            $json = json_decode($raw, true);

            return [
                'status' => $response->successful() ? 'success' : 'error',
                'message' => "HTTP {$response->status()}",
                'http_status' => $response->status(),
                'ms' => (int) round((microtime(true) - $start) * 1000),
                'headers' => array_map(fn (array $v): string => $ctx->redact(implode(', ', $v)), array_intersect_key(
                    $response->headers(),
                    array_flip(['Content-Type', 'content-type', 'Retry-After', 'retry-after', 'X-RateLimit-Remaining', 'x-ratelimit-remaining']),
                )),
                'body' => $ctx->redact(is_array($json)
                    ? (string) json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : mb_substr($raw, 0, 20000)),
            ];
        });
    }

    /**
     * Ruft jeden lesenden, prüfbaren Endpunkt einmal auf (ohne Pfadparameter).
     *
     * @return array<string, mixed>
     */
    public function accessCheck(string $id, ?IntranetUser $user): array
    {
        $this->requireReady($id);

        return $this->guarded($id, $user, 'Zugriff prüfen', function (PluginContext $ctx): array {
            $rows = [];

            foreach ($ctx->manifest->endpoints() as $endpoint) {
                $method = strtoupper((string) ($endpoint['method'] ?? 'GET'));
                $skip = match (true) {
                    $method !== 'GET' => 'schreibend – nicht geprüft',
                    ($endpoint['status'] ?? 'full') === 'planned' => 'geplant',
                    ($endpoint['test'] ?? true) === false => 'vom Plugin ausgenommen',
                    str_contains((string) $endpoint['path'], '{') && ! $this->hasPathDefaults($endpoint) => 'braucht eine ID',
                    default => null,
                };

                if ($skip !== null) {
                    $rows[] = ['endpoint' => $endpoint['label'] ?? $endpoint['id'], 'path' => "{$method} {$endpoint['path']}", 'result' => $skip, 'http' => '–'];

                    continue;
                }

                try {
                    $status = $ctx->call((string) $endpoint['id'])->status();
                    $result = match (true) {
                        $status >= 200 && $status < 300 => 'Zugriff',
                        $status === 401, $status === 403 => 'kein Zugriff (Berechtigung fehlt)',
                        $status === 404 => 'nicht gefunden',
                        $status === 429 => 'gedrosselt',
                        default => 'erreichbar, Fehler',
                    };
                } catch (Throwable $e) {
                    $status = 0;
                    $result = 'nicht erreichbar: '.$ctx->redact($e->getMessage());
                }

                $rows[] = ['endpoint' => $endpoint['label'] ?? $endpoint['id'], 'path' => "{$method} {$endpoint['path']}", 'result' => $result, 'http' => (string) $status];
            }

            return [
                'status' => 'success',
                'message' => count($rows).' Endpunkte geprüft.',
                'columns' => [
                    ['key' => 'endpoint', 'label' => 'Endpunkt'],
                    ['key' => 'path', 'label' => 'Aufruf'],
                    ['key' => 'result', 'label' => 'Ergebnis'],
                    ['key' => 'http', 'label' => 'HTTP'],
                ],
                'rows' => $rows,
                'preview' => false,
            ];
        });
    }

    /**
     * Kacheln aller eingeschalteten, eingerichteten Plugins (kurz zwischengespeichert).
     *
     * @return list<array<string, mixed>>
     */
    public function widgets(?IntranetUser $user): array
    {
        $result = [];

        foreach ($this->all() as $id => ['manifest' => $manifest, 'plugin' => $plugin]) {
            if ($manifest->widgets() === [] || ! $this->state($id)['enabled'] || ! $this->isConfigured($id)) {
                continue;
            }

            foreach ($manifest->widgets() as $widget) {
                if (($widget['audience'] ?? 'admin') === 'admin' && ! $user?->hasRole('admin')) {
                    continue;
                }

                $widgetId = (string) $widget['id'];
                $data = Cache::remember("plugin-widgets:{$id}:{$widgetId}", (int) ($widget['ttl'] ?? 300), function () use ($id, $plugin, $widgetId, $user): array {
                    try {
                        return $plugin->widget($widgetId, $this->context($id, $user)) + ['error' => null];
                    } catch (Throwable $e) {
                        return ['items' => [], 'error' => $this->context($id, $user)->redact($e->getMessage())];
                    }
                });

                $result[] = [
                    'plugin' => $id,
                    'plugin_name' => $manifest->name(),
                    'id' => $widgetId,
                    'label' => (string) ($widget['label'] ?? $manifest->name()),
                    ...$data,
                ];
            }
        }

        return $result;
    }

    /**
     * Ereignis an alle eingeschalteten Plugins melden, die es abonniert haben.
     * Fehler eines Plugins brechen nie die eigentliche Aktion ab.
     *
     * @param  array<string, mixed>  $data
     */
    public function dispatch(string $event, array $data, ?IntranetUser $user = null): void
    {
        foreach ($this->all() as $id => ['manifest' => $manifest, 'plugin' => $plugin]) {
            if (! in_array($event, $manifest->events(), true) || ! $this->state($id)['enabled'] || ! $this->isConfigured($id)) {
                continue;
            }

            $this->guarded($id, $user, "Ereignis {$event}", function (PluginContext $ctx) use ($plugin, $event, $data): array {
                $plugin->onEvent($event, $data, $ctx);

                return ['status' => 'success', 'message' => 'verarbeitet'];
            }, rethrow: false);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function runs(?string $id = null, int $limit = 50): array
    {
        $runs = array_values(array_filter(
            $this->store->all(self::RUNS),
            fn (array $run): bool => $id === null || ($run['plugin'] ?? null) === $id,
        ));

        return array_slice(array_reverse($runs), 0, $limit);
    }

    private function requireReady(string $id): void
    {
        if (! $this->state($id)['enabled']) {
            throw new RuntimeException('Plugin ist ausgeschaltet.');
        }
        if (! $this->isConfigured($id)) {
            throw new RuntimeException('Plugin ist noch nicht eingerichtet (Pflichtfelder in den Einstellungen).');
        }
    }

    /**
     * @param  array<string, mixed>  $endpoint
     */
    private function hasPathDefaults(array $endpoint): bool
    {
        preg_match_all('/\{([a-z0-9_]+)\}/i', (string) $endpoint['path'], $m);
        $defaults = [];
        foreach ((array) ($endpoint['params'] ?? []) as $p) {
            if (is_array($p) && ($p['default'] ?? '') !== '') {
                $defaults[] = $p['name'];
            }
        }

        return array_diff($m[1], $defaults) === [];
    }

    /**
     * Führt einen Plugin-Aufruf aus, protokolliert ihn und wandelt Fehler in Meldungen um.
     *
     * @param  callable(PluginContext): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function guarded(string $id, ?IntranetUser $user, string $label, callable $callback, bool $rethrow = false): array
    {
        $ctx = $this->context($id, $user);
        $start = microtime(true);

        try {
            $result = $callback($ctx);
        } catch (Throwable $e) {
            $result = ['ok' => false, 'status' => 'error', 'message' => $ctx->redact($e->getMessage())];
            Log::warning("Plugin {$id} ({$label}): ".$result['message']);

            if ($rethrow) {
                throw $e;
            }
        }

        $result['log'] = $ctx->lines();
        $this->recordRun($id, $user, $label, $result, (int) round((microtime(true) - $start) * 1000));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function recordRun(string $id, ?IntranetUser $user, string $label, array $result, int $ms): void
    {
        $this->store->insert(self::RUNS, [
            'plugin' => $id,
            'label' => $label,
            'status' => $result['status'] ?? 'success',
            'message' => mb_substr((string) ($result['message'] ?? ''), 0, 500),
            'log' => array_slice((array) ($result['log'] ?? []), 0, 100),
            'user_id' => $user?->id(),
            'user_name' => $user?->name(),
            'ms' => $ms,
        ]);

        $runs = $this->store->all(self::RUNS);
        if (count($runs) > self::MAX_RUNS) {
            $this->store->replace(self::RUNS, array_slice($runs, -self::MAX_RUNS));
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function saveState(string $id, array $changes): void
    {
        if ($this->store->find(self::STATES, $id) === null) {
            $this->store->insert(self::STATES, array_merge(
                ['id' => $id, 'enabled' => false, 'allow_write' => false, 'settings' => [], 'secrets' => []],
                $changes,
            ));

            return;
        }

        $this->store->update(self::STATES, $id, $changes);
    }
}
