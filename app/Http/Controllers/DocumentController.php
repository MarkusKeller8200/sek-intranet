<?php

namespace App\Http\Controllers;

use App\Data\Contracts\DocumentRepository;
use App\Data\Contracts\UserRepository;
use App\Support\DocumentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dokumente (Dateien auf Disk "local") und Links.
 * Die Metadaten liegen im DocumentRepository, die Dateien unter storage/app/private.
 */
class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly UserRepository $users,
    ) {}

    public function index(Request $request): Response
    {
        $names = $this->users->names();

        return Inertia::render('documents/index', [
            'documents' => array_map(fn (array $d): array => $this->public($d) + [
                'created_by_name' => $names[$d['created_by'] ?? ''] ?? null,
                'restricted' => DocumentAccess::ABILITIES[(string) ($d['ability'] ?? '')] ?? null,
            ], DocumentAccess::filter($this->documents->sorted(), $request->user())),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('documents/form', ['document' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, requireFile: true);
        $data['created_by'] = (string) $request->user()?->getAuthIdentifier();

        $document = $this->documents->create($data);
        unset($document['file_path']);
        $this->pluginEvent('document.created', $document);
        $this->toast('Eintrag gespeichert.');

        return redirect()->route('documents.index');
    }

    public function edit(Request $request, string $id): Response
    {
        return Inertia::render('documents/form', [
            'document' => $this->public($this->visibleOrFail($request, $id)),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $existing = $this->visibleOrFail($request, $id);
        $hasFile = ($existing['kind'] ?? null) === 'file' && is_string($existing['file_path'] ?? null);

        $data = $this->validated($request, requireFile: ! $hasFile);

        // Datei ersetzt oder Art auf Link gewechselt: alte Datei entfernen.
        $replaced = array_key_exists('file_path', $data) && ($data['file_path'] ?? null) !== ($existing['file_path'] ?? null);

        if ($hasFile && $replaced) {
            Storage::disk('local')->delete((string) $existing['file_path']);
        }

        $this->documents->update($id, $data);
        $this->toast('Eintrag aktualisiert.');

        return redirect()->route('documents.index');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $document = $this->visibleOrFail($request, $id);

        if (($document['kind'] ?? null) === 'file' && is_string($document['file_path'] ?? null)) {
            Storage::disk('local')->delete($document['file_path']);
        }

        $this->documents->delete($id);
        $this->toast('Eintrag gelöscht.');

        return redirect()->route('documents.index');
    }

    public function download(Request $request, string $id): StreamedResponse
    {
        $document = $this->visibleOrFail($request, $id);
        $path = $document['file_path'] ?? null;

        abort_unless(
            ($document['kind'] ?? null) === 'file' && is_string($path) && Storage::disk('local')->exists($path),
            404,
        );

        $name = is_string($document['file_name'] ?? null) ? $document['file_name'] : basename($path);

        return Storage::disk('local')->download($path, $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireFile): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in($this->keys('document_categories'))],
            'kind' => ['required', Rule::in(['file', 'link'])],
            'url' => ['nullable', 'required_if:kind,link', 'url:http,https', 'max:2048'],
            'file' => [
                Rule::requiredIf(fn (): bool => $request->input('kind') === 'file' && $requireFile),
                'nullable',
                'file',
                'max:'.(int) config('intranet.upload_max_kb', 20480),
            ],
        ]);

        $result = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'kind' => $data['kind'],
        ];

        if ($data['kind'] === 'link') {
            return $result + ['url' => $data['url'], 'file_path' => null, 'file_name' => null, 'file_size' => null];
        }

        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            $path = $file->store((string) config('intranet.upload_directory', 'intranet/documents'), 'local');

            return $result + [
                'url' => null,
                'file_path' => $path === false ? null : $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ];
        }

        return $result + ['url' => null];
    }

    /**
     * Eingeschränkte Dokumente (#94) gibt es für andere nicht.
     *
     * @return array<string, mixed>
     */
    private function visibleOrFail(Request $request, string $id): array
    {
        $document = $this->documents->findOrFail($id);
        abort_unless(DocumentAccess::visible($document, $request->user()), 404);

        return $document;
    }

    /**
     * Interner Dateipfad bleibt auf dem Server.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function public(array $document): array
    {
        unset($document['file_path']);

        return $document;
    }
}
