<?php

namespace Modules\Ablaeufe\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ablaeufe\Data\Contracts\AblaufRepository;
use Modules\Ablaeufe\Data\Contracts\GruppeRepository;

/**
 * Personengruppen für «gilt für» (#47): anlegen, umbenennen, Bemerkung, Reihenfolge, entfernen.
 */
class GruppenController extends Controller
{
    public function __construct(
        private readonly GruppeRepository $gruppen,
        private readonly AblaufRepository $ablaeufe,
    ) {}

    public function index(): Response
    {
        $verwendet = [];
        foreach ($this->ablaeufe->all() as $ablauf) {
            foreach ((array) ($ablauf['schritte'] ?? []) as $schritt) {
                foreach ((array) ($schritt['gilt_fuer'] ?? []) as $id) {
                    $verwendet[(string) $id] = ($verwendet[(string) $id] ?? 0) + 1;
                }
            }
        }

        return Inertia::render('Ablaeufe::gruppen', [
            'gruppen' => array_map(fn (array $g): array => [
                'id' => (string) $g['id'],
                'name' => (string) ($g['name'] ?? ''),
                'bemerkung' => (string) ($g['bemerkung'] ?? ''),
                'verwendet' => $verwendet[(string) $g['id']] ?? 0,
            ], $this->gruppen->sortiert()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->gruppen->create($data + ['position' => count($this->gruppen->all()) + 1]);
        $this->toast('Gruppe gespeichert.');

        return back();
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->gruppen->findOrFail($id);
        $this->gruppen->update($id, $this->validated($request));
        $this->toast('Gruppe gespeichert.');

        return back();
    }

    public function sort(Request $request): RedirectResponse
    {
        $ids = (array) $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['string']])['ids'];
        foreach (array_values($ids) as $i => $id) {
            if ($this->gruppen->find((string) $id) !== null) {
                $this->gruppen->update((string) $id, ['position' => $i + 1]);
            }
        }

        return back();
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->gruppen->findOrFail($id);
        $this->gruppen->delete($id);
        // Verweise in Schritten entfernen, damit keine unbekannten Gruppen bleiben
        foreach ($this->ablaeufe->all() as $ablauf) {
            $schritte = (array) ($ablauf['schritte'] ?? []);
            $neu = array_map(fn (array $s): array => ['gilt_fuer' => array_values(array_diff((array) ($s['gilt_fuer'] ?? []), [$id]))] + $s, $schritte);
            if ($neu !== $schritte) {
                $this->ablaeufe->update((string) $ablauf['id'], ['schritte' => $neu]);
            }
        }
        $this->toast('Gruppe entfernt.');

        return back();
    }

    /**
     * @return array{name: string, bemerkung: string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'bemerkung' => ['nullable', 'string', 'max:2000'],
        ], ['name.required' => 'Bitte einen Namen eingeben.']);

        return ['name' => (string) $data['name'], 'bemerkung' => (string) ($data['bemerkung'] ?? '')];
    }
}
