<?php

namespace App\Http\Controllers;

use App\Data\Store\JsonStore;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prototyp → API-Recorder: Anfragen einer Website aufzeichnen (im Browser), ausgewählte Endpunkte
 * als API-Dokumentation speichern und als OpenAPI-Datei (Swagger) herunterladen.
 * Gespeichert werden nur Endpunkte, abgeleitete Schemas und – falls gewählt – maskierte Beispiele,
 * nie die aufgezeichneten Rohdaten.
 */
class ApiRecorderController extends Controller
{
    public const COLLECTION = 'api_docs';

    private const MAX_OPERATIONS = 500;

    private const MAX_BYTES = 2_000_000;

    public function __construct(private readonly JsonStore $store, private readonly AuditLog $audit) {}

    public function show(): Response
    {
        $docs = $this->store->all(self::COLLECTION);
        usort($docs, fn (array $a, array $b): int => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));

        return Inertia::render('api-recorder', [
            'docs' => $docs,
            'origin' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:200'],
            'base_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://\S+)?$#'],
            'description' => ['nullable', 'string', 'max:5000'],
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

        $record = [
            'title' => $data['title'],
            'base_url' => (string) ($data['base_url'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'operations' => $operations,
            'owner_id' => $request->user()?->getAuthIdentifier(),
        ];

        $id = $data['id'] ?? null;
        $before = is_string($id) ? $this->store->find(self::COLLECTION, $id) : null;
        $doc = $before !== null
            ? $this->store->update(self::COLLECTION, (string) $id, $record)
            : $this->store->insert(self::COLLECTION, $record);

        // Im Verlauf nur Titel und Anzahl, nicht die ganze Struktur
        $summary = fn (?array $d): ?array => $d === null ? null : ['id' => $d['id'], 'title' => $d['title'] ?? '', 'operations' => count((array) ($d['operations'] ?? []))];
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
}
