<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Support\Signatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

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
            'filter' => [
                'modul' => (string) $request->query('modul', ''),
                'monat' => (string) $request->query('monat', ''),
                'status' => (string) $request->query('status', ''),
            ],
        ]);
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
