<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Support\Signatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Seite «Unterschriften» (#42, Recht manage-signatures): Aufträge mit Status je Person, erinnern, zurückziehen,
 * löschen, starten, Status aktualisieren; «In DeepSign öffnen» nur für die Administration.
 */
class SignatureController extends Controller
{
    public function __construct(private readonly Signatures $signatures) {}

    public function index(Request $request): Response
    {
        return Inertia::render('signatures/index', [
            'auftraege' => $this->signatures->all(),
            'status' => Signatures::STATUS,
            'personStatus' => Signatures::SIGNEE_STATUS,
            'anbieter' => $this->signatures->provider(),
            'darfOeffnen' => Gate::allows('manage-users'),
            'mitPdf' => array_values(array_filter(array_map(fn (array $a): ?string => $this->signatures->manualPdf((string) $a['id']) !== null ? (string) $a['id'] : null, $this->signatures->all()))),
            'filter' => [
                'modul' => (string) $request->query('modul', ''),
                'monat' => (string) $request->query('monat', ''),
                'status' => (string) $request->query('status', ''),
            ],
        ]);
    }

    /**
     * Dokument von Hand zur Unterschrift senden (Dialog «Dokument senden»): PDF, Unterzeichnende, Reihenfolge.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'datei' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'titel' => ['nullable', 'string', 'max:200'],
            'unterzeichnende' => ['required', 'array', 'min:1', 'max:20'],
            'unterzeichnende.*.name' => ['required', 'string', 'max:120'],
            'unterzeichnende.*.email' => ['required', 'email', 'max:200'],
            'gleichzeitig' => ['boolean'],
            'beobachtende' => ['nullable', 'string', 'max:500'],
            'nachricht' => ['nullable', 'string', 'max:1000'],
        ], [
            'datei.required' => 'Bitte ein PDF wählen.',
            'datei.mimes' => 'Nur PDF-Dateien.',
            'unterzeichnende.*.email.email' => 'Ungültige E-Mail.',
        ]);
        /** @var UploadedFile $file */
        $file = $data['datei'];
        $observers = array_values(array_filter(array_map('trim', explode(',', (string) ($data['beobachtende'] ?? '')))));

        return $this->run(fn () => $this->signatures->sendManual(
            (string) ($data['titel'] ?? ''),
            $file->getClientOriginalName(),
            (string) $file->get(),
            array_values(array_map(fn (array $s): array => ['name' => trim((string) $s['name']), 'email' => trim((string) $s['email'])], $data['unterzeichnende'])),
            (bool) ($data['gleichzeitig'] ?? false),
            $observers,
            $data['nachricht'] ?? null,
            $this->user($request),
        ), 'Zur Unterschrift gesendet. Die Unterzeichnenden erhalten eine E-Mail von DeepSign.');
    }

    /** Unterschriebenes PDF eines von Hand gesendeten Auftrags. */
    public function pdf(string $id): StreamedResponse
    {
        $path = $this->signatures->manualPdf($id);
        abort_if($path === null, 404);
        $title = (string) ($this->signatures->find($id)['title'] ?? 'Dokument');

        return Storage::disk('local')->download($path, preg_replace('/[^\w\- ]+/u', '_', $title).'-unterschrieben.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function refresh(Request $request, string $id): RedirectResponse
    {
        return $this->run(fn () => $this->signatures->refresh($id, $this->user($request)), 'Status aktualisiert.');
    }

    public function refreshAll(): RedirectResponse
    {
        return $this->run(function (): void {
            $result = $this->signatures->refreshDue(0);
            $this->toast("{$result['checked']} Aufträge abgefragt, {$result['fetched']} Dokumente abgeholt".($result['errors'] > 0 ? ", {$result['errors']} Fehler" : '').'.');
        }, null);
    }

    public function start(Request $request, string $id): RedirectResponse
    {
        return $this->run(fn () => $this->signatures->start($id, $this->user($request)), 'Zur Unterschrift versendet.');
    }

    public function remind(Request $request, string $id): RedirectResponse
    {
        $index = (int) $request->validate(['person' => ['required', 'integer', 'min:0']])['person'];

        return $this->run(fn () => $this->signatures->remind($id, $index, $this->user($request)), 'Erinnerung gesendet.');
    }

    public function withdraw(Request $request, string $id): RedirectResponse
    {
        return $this->run(fn () => $this->signatures->withdraw($id, $this->user($request)), 'Zurückgezogen.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        return $this->run(fn () => $this->signatures->delete($id, $this->user($request)), 'Auftrag gelöscht.');
    }

    public function simulate(Request $request, string $id): RedirectResponse
    {
        return $this->run(fn () => $this->signatures->simulate($id, $this->user($request)), 'Demo: nächste Person hat unterschrieben.');
    }

    /** Kurzlebiger Link zum Bearbeiten beim Anbieter (nur Administration, Recht manage-users). */
    public function access(Request $request, string $id): JsonResponse
    {
        abort_unless(Gate::allows('manage-users'), 403);

        try {
            $url = $this->signatures->accessUrl($id, $this->user($request));
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $url === null
            ? response()->json(['message' => 'Der Anbieter hat keinen Link geliefert.'], 422)
            : response()->json(['url' => $url]);
    }

    /**
     * @param  callable(): mixed  $action
     */
    private function run(callable $action, ?string $success): RedirectResponse
    {
        try {
            $action();
            if ($success !== null) {
                $this->toast($success);
            }
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');
        }

        return back();
    }

    private function user(Request $request): ?IntranetUser
    {
        $user = $request->user();

        return $user instanceof IntranetUser ? $user : null;
    }
}
