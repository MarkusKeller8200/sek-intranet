<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Plugins\PersonalDataUnlock;
use App\Plugins\PluginContext;
use App\Plugins\PluginManager;
use App\Support\ApiDocs;
use App\Support\RolePins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    public function show(Request $request, string $id, ApiDocs $docs): Response
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
            // Protokollstufe (#218)
            'logLevel' => $this->plugins->logLevel($id),
            'logLevels' => PluginContext::LOG_LEVELS,
            // Mit dem API-Recorder aufgezeichnete APIs, die diesem Plugin zugewiesen sind
            'recordings' => array_map(fn (array $doc): array => [
                'id' => $doc['id'],
                'title' => $doc['title'] ?? '',
                'operations' => $docs->compare($doc),
            ], $docs->forPlugin($id)),
            'serverInSwitzerland' => (bool) config('intranet.server_in_switzerland'),
            // Personendaten-Endpunkte ausserhalb der Schweiz: befristete Freischaltung mit dem Admin-PIN
            'personalData' => [
                'endpoints' => count(array_filter($manifest->endpoints(), fn (array $e): bool => ($e['personal_data'] ?? false) === true)),
                'until' => app(PersonalDataUnlock::class)->until($id),
                'minutes' => PersonalDataUnlock::MINUTES,
                'canUnlock' => $this->user($request)?->hasRole(RolePins::ADMIN) === true,
            ],
        ]);
    }

    /** Personendaten für einen Test freischalten (Admin-PIN, befristet). */
    public function unlockPersonal(Request $request, string $id): RedirectResponse
    {
        $this->plugins->manifest($id);
        $user = $this->user($request);
        abort_if($user === null, 403);
        $pin = (string) $request->input('pin', '');
        try {
            $until = app(PersonalDataUnlock::class)->unlock($id, $pin, $user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }
        $this->toast('Personendaten für den Test freigeschaltet bis '.date('H:i', (int) strtotime($until)).'.');

        return back();
    }

    /** Protokollstufe setzen (#218): Details und Debug für 60 Minuten. */
    public function logLevel(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['level' => ['required', Rule::in(array_keys(PluginContext::LOG_LEVELS))]]);
        $r = $this->plugins->setLogLevel($id, (string) $data['level']);
        $this->toast($r['level'] === 'normal' ? 'Protokollstufe: Normal.' : 'Protokollstufe: '.PluginContext::LOG_LEVELS[$r['level']].' bis '.date('H:i', (int) strtotime((string) $r['until'])).'.');

        return back();
    }

    public function lockPersonal(Request $request, string $id): RedirectResponse
    {
        app(PersonalDataUnlock::class)->lock($id, $this->user($request));
        $this->toast('Personendaten wieder gesperrt.');

        return back();
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

    /**
     * «Alle Verbindungen testen»: jedes eingeschaltete und eingerichtete Plugin nacheinander prüfen.
     * Ausgeschaltete oder unvollständig eingerichtete Plugins werden übersprungen (mit Grund).
     */
    public function testAll(Request $request): JsonResponse
    {
        $results = [];
        foreach ($this->plugins->all() as $id => ['manifest' => $manifest]) {
            $state = $this->plugins->state($id);
            $skip = match (true) {
                ! $state['enabled'] => 'ausgeschaltet',
                ! $this->plugins->isConfigured($id) => 'nicht fertig eingerichtet',
                default => null,
            };
            if ($skip !== null) {
                $results[] = ['id' => $id, 'name' => $manifest->name(), 'ok' => null, 'message' => "Übersprungen: {$skip}.", 'ms' => null];

                continue;
            }
            $start = microtime(true);
            try {
                $result = $this->plugins->test($id, $this->user($request));
            } catch (\Throwable $e) {
                $result = ['ok' => false, 'message' => $e->getMessage()];
            }
            $results[] = [
                'id' => $id,
                'name' => $manifest->name(),
                'ok' => ($result['ok'] ?? false) === true,
                'message' => (string) ($result['message'] ?? ''),
                'ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        }

        $tested = array_filter($results, fn (array $r): bool => $r['ok'] !== null);
        $failed = array_filter($tested, fn (array $r): bool => $r['ok'] === false);

        return response()->json([
            'status' => $failed === [] ? 'success' : 'warning',
            'message' => count($tested).' Verbindungen geprüft, '.count($failed).' mit Fehler.',
            'results' => $results,
        ]);
    }

    public function action(Request $request, string $id, string $action): JsonResponse
    {
        // Mit Datei: multipart, übrige Angaben als JSON in «payload», Dateien als «file_<schlüssel>».
        $payload = $request->has('payload')
            ? (array) json_decode((string) $request->input('payload'), true)
            : $request->all();

        $options = (array) ($payload['options'] ?? []);
        foreach ($request->allFiles() as $name => $file) {
            if (str_starts_with((string) $name, 'file_') && $file instanceof UploadedFile) {
                $options[substr((string) $name, 5)] = $file;
            }
        }
        $options['_apply'] = filter_var($payload['apply'] ?? false, FILTER_VALIDATE_BOOL);
        $options['_selected'] = array_values(array_map('strval', (array) ($payload['selected'] ?? [])));

        return $this->json(fn (): array => $this->plugins->runAction($id, $action, $options, $this->user($request)));
    }

    public function api(Request $request, string $id): JsonResponse
    {
        // Mit Datei (Endpunkte mit «file», z. B. DeepSign-Upload): multipart, Angaben als JSON in «payload»
        $input = $request->has('payload') ? (array) json_decode((string) $request->input('payload'), true) : $request->all();
        $data = Validator::make($input, [
            'endpoint' => ['required', 'string'],
            'params' => ['array'],
            'body' => ['nullable', 'string', 'max:100000'],
        ])->validate();

        $file = null;
        $upload = $request->file('file_upload');
        if ($upload instanceof UploadedFile) {
            if (! $upload->isValid() || $upload->getSize() > 20 * 1024 * 1024) {
                return response()->json(['status' => 'error', 'message' => 'Datei ungültig oder grösser als 20 MB.'], 422);
            }
            $file = ['name' => $upload->getClientOriginalName(), 'contents' => (string) $upload->get(), 'mime' => (string) ($upload->getMimeType() ?? 'application/octet-stream')];
        }

        $body = null;
        if (is_string($data['body'] ?? null) && $data['body'] !== '') {
            $decoded = json_decode((string) $data['body'], true);
            if (! is_array($decoded)) {
                return response()->json(['status' => 'error', 'message' => 'Body ist kein gültiges JSON-Objekt.'], 422);
            }
            $body = $decoded;
        }

        return $this->json(fn (): array => $this->plugins->apiCall($id, (string) $data['endpoint'], (array) ($data['params'] ?? []), $body, $this->user($request), $file));
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
