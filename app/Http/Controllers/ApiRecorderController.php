<?php

namespace App\Http\Controllers;

use App\Data\Store\JsonStore;
use App\Plugins\PluginManager;
use App\Support\ApiDocs;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Prototyp → API-Recorder: Verwaltung der aufgezeichneten APIs (Liste), Aufzeichnen und Ergänzen
 * (Editor), Zuweisung zu einem Plugin, OpenAPI-Datei und Endpunkt-Entwurf für das Plugin.
 * Gespeichert werden nur Endpunkte, abgeleitete Schemas und – falls gewählt – maskierte Beispiele,
 * nie die aufgezeichneten Rohdaten.
 */
class ApiRecorderController extends Controller
{
    public const COLLECTION = ApiDocs::COLLECTION;

    private const MAX_OPERATIONS = 500;

    private const MAX_BYTES = 2_000_000;

    public function __construct(
        private readonly JsonStore $store,
        private readonly AuditLog $audit,
        private readonly ApiDocs $docs,
    ) {}

    /** Liste der aufgezeichneten APIs. */
    public function index(): Response
    {
        $plugins = array_column($this->docs->pluginCatalog(), 'name', 'id');

        return Inertia::render('api-recorder/index', [
            'docs' => array_map(function (array $doc) use ($plugins): array {
                $compare = $this->docs->compare($doc);

                return $doc + [
                    'plugin_name' => $plugins[$doc['plugin_id'] ?? ''] ?? null,
                    'missing' => count(array_filter($compare, fn (array $r): bool => $r['endpoint'] === null)),
                ];
            }, $this->docs->all()),
        ]);
    }

    /** Neue API aufzeichnen. */
    public function create(): Response
    {
        return $this->editor(null);
    }

    /** Gespeicherte API öffnen und weiter aufzeichnen. */
    public function show(string $id): Response
    {
        $doc = $this->docs->find($id);
        abort_if($doc === null, 404);

        return $this->editor($doc);
    }

    /**
     * @param  array<string, mixed>|null  $doc
     */
    private function editor(?array $doc): Response
    {
        return Inertia::render('api-recorder', [
            'current' => $doc,
            'plugins' => $this->docs->pluginCatalog(),
            'origin' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:200'],
            'start_url' => ['nullable', 'string', 'max:2000', 'regex:#^(https?://\S+)?$#'],
            'base_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://\S+)?$#'],
            'description' => ['nullable', 'string', 'max:5000'],
            'plugin_id' => ['nullable', 'string', 'max:64', Rule::in(array_keys(app(PluginManager::class)->all()))],
            'operations' => ['present', 'array', 'max:'.self::MAX_OPERATIONS],
            'operations.*.key' => ['required', 'string', 'max:2000'],
            'operations.*.method' => ['required', 'string', 'in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'],
            'operations.*.path' => ['required', 'string', 'max:2000'],
            'operations.*.summary' => ['nullable', 'string', 'max:500'],
            'operations.*.tag' => ['nullable', 'string', 'max:100'],
            'operations.*.status' => ['nullable', 'integer', 'between:0,599'],
        ]);

        $operations = array_values((array) $request->input('operations', []));
        if (strlen((string) json_encode($operations)) > self::MAX_BYTES) {
            return response()->json(['message' => 'Dokumentation zu gross (höchstens 2 MB).'], 422);
        }

        $id = $data['id'] ?? null;
        $before = is_string($id) ? $this->store->find(self::COLLECTION, $id) : null;
        $record = [
            'title' => $data['title'],
            'start_url' => (string) ($data['start_url'] ?? ''),
            'base_url' => (string) ($data['base_url'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'plugin_id' => ($data['plugin_id'] ?? '') !== '' ? $data['plugin_id'] : null,
            'operations' => $operations,
            // Zeitpunkt der letzten neuen Endpunkte (für die Liste)
            'recorded_at' => count($operations) !== count((array) ($before['operations'] ?? []))
                ? now()->toAtomString()
                : ($before['recorded_at'] ?? now()->toAtomString()),
            'owner_id' => $before['owner_id'] ?? $request->user()?->getAuthIdentifier(),
        ];

        $doc = $before !== null
            ? $this->store->update(self::COLLECTION, (string) $id, $record)
            : $this->store->insert(self::COLLECTION, $record);

        // Im Verlauf nur Titel, Plugin und Anzahl, nicht die ganze Struktur
        $summary = fn (?array $d): ?array => $d === null ? null : ['id' => $d['id'], 'title' => $d['title'] ?? '', 'plugin_id' => $d['plugin_id'] ?? null, 'operations' => count((array) ($d['operations'] ?? []))];
        $this->audit->record(self::COLLECTION, $summary($before), $summary($doc), 'API-Recorder', $request->user());

        return response()->json(['doc' => $doc]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $doc = $this->store->find(self::COLLECTION, $id);
        abort_if($doc === null, 404);
        $this->store->delete(self::COLLECTION, $id);
        $this->audit->record(self::COLLECTION, ['id' => $id, 'title' => $doc['title'] ?? ''], null, 'API-Recorder', $request->user());

        return response()->json(['status' => 'ok']);
    }

    /** Entwurf für plugin.json → service.endpoints (nur dem Plugin fehlende Endpunkte). */
    public function pluginDraft(string $id): HttpResponse
    {
        $doc = $this->docs->find($id);
        abort_if($doc === null, 404);

        $draft = [
            '_hinweis' => 'Entwurf aus dem API-Recorder. In plugins/<Ordner>/plugin.json unter service.endpoints einfügen, mit der Hersteller-Doku prüfen und erst dann status «full» setzen (docs/KONZEPT-API-ZU-PLUGIN.md).',
            'plugin' => $doc['plugin_id'] ?? null,
            'endpoints' => $this->docs->endpointDraft($doc),
        ];
        $name = (string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($doc['title'] ?? 'api')));

        return response((string) json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.trim($name, '-').'-plugin-endpunkte.json"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
