<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Plugins\PluginManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

/**
 * Plugin-Verwaltung (nur Administration). Seiten über Inertia, Abläufe
 * (Testen, Aktionen, API-Tester) als JSON, damit Ergebnisse ohne Seitenwechsel erscheinen.
 */
class PluginController extends Controller
{
    public function __construct(private readonly PluginManager $plugins) {}

    public function index(): Response
    {
        $cards = [];
        $lastRuns = [];

        foreach ($this->plugins->runs(null, 300) as $run) {
            $lastRuns[$run['plugin']] ??= $run;
        }

        foreach ($this->plugins->all() as $id => ['manifest' => $manifest]) {
            $state = $this->plugins->state($id);
            $cards[] = $manifest->toArray() + [
                'enabled' => $state['enabled'],
                'configured' => $this->plugins->isConfigured($id),
                'allow_write' => $state['allow_write'],
                'last_run' => $lastRuns[$id] ?? null,
            ];
        }

        return Inertia::render('plugins/index', [
            'plugins' => $cards,
            'errors' => $this->plugins->loadErrors(),
            'serverInSwitzerland' => (bool) config('intranet.server_in_switzerland'),
        ]);
    }

    public function show(string $id): Response
    {
        $manifest = $this->plugins->manifest($id);
        $state = $this->plugins->state($id);
        $secretKeys = $manifest->secretKeys();

        $values = [];
        foreach ($manifest->settings() as $setting) {
            $key = (string) $setting['key'];
            $values[$key] = in_array($key, $secretKeys, true) ? '' : ($state['settings'][$key] ?? $setting['default'] ?? null);
        }

        return Inertia::render('plugins/show', [
            'plugin' => $manifest->toArray() + [
                'enabled' => $state['enabled'],
                'configured' => $this->plugins->isConfigured($id),
                'allow_write' => $state['allow_write'],
            ],
            'values' => $values,
            'secretsSet' => array_values(array_filter($secretKeys, fn (string $k): bool => ($state['secrets'][$k] ?? '') !== '')),
            'runs' => $this->plugins->runs($id, 30),
            'serverInSwitzerland' => (bool) config('intranet.server_in_switzerland'),
        ]);
    }

    public function toggle(Request $request, string $id): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $this->plugins->setEnabled($id, $enabled);
        $this->toast($enabled ? 'Plugin eingeschaltet.' : 'Plugin ausgeschaltet.');

        return back();
    }

    public function settings(Request $request, string $id): RedirectResponse
    {
        try {
            $this->plugins->saveSettings($id, $request->all() + [
                '_allow_write' => $request->boolean('_allow_write'),
                '_clear_secrets' => $request->boolean('_clear_secrets'),
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['settings' => $e->getMessage()]);
        }

        $this->toast('Einstellungen gespeichert.');

        return back();
    }

    public function test(Request $request, string $id): JsonResponse
    {
        return $this->json(fn (): array => $this->plugins->test($id, $this->user($request)));
    }

    public function action(Request $request, string $id, string $action): JsonResponse
    {
        $options = (array) $request->input('options', []);
        $options['_apply'] = $request->boolean('apply');
        $options['_selected'] = array_values(array_map('strval', (array) $request->input('selected', [])));

        return $this->json(fn (): array => $this->plugins->runAction($id, $action, $options, $this->user($request)));
    }

    public function api(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'params' => ['array'],
            'body' => ['nullable', 'string', 'max:100000'],
        ]);

        $body = null;
        if (($data['body'] ?? '') !== '' && $data['body'] !== null) {
            $decoded = json_decode((string) $data['body'], true);
            if (! is_array($decoded)) {
                return response()->json(['status' => 'error', 'message' => 'Body ist kein gültiges JSON-Objekt.'], 422);
            }
            $body = $decoded;
        }

        return $this->json(fn (): array => $this->plugins->apiCall($id, (string) $data['endpoint'], (array) ($data['params'] ?? []), $body, $this->user($request)));
    }

    public function access(Request $request, string $id): JsonResponse
    {
        return $this->json(fn (): array => $this->plugins->accessCheck($id, $this->user($request)));
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    private function json(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    private function user(Request $request): ?IntranetUser
    {
        $user = $request->user();

        return $user instanceof IntranetUser ? $user : null;
    }
}
