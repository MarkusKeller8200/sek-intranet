<?php

namespace Modules\Ablaeufe\Http;

use App\Data\Contracts\DocumentRepository;
use App\Data\Contracts\UserRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ablaeufe\Ablauf;
use Modules\Ablaeufe\Data\Contracts\AblaufRepository;
use Modules\Ablaeufe\Data\Contracts\GruppeRepository;

/**
 * Verzeichnis der Abläufe (#45–#48): Liste, Ansicht, Erfassen und Bearbeiten von Checklisten-Vorlagen.
 */
class AblaeufeController extends Controller
{
    public function __construct(
        private readonly AblaufRepository $ablaeufe,
        private readonly GruppeRepository $gruppen,
        private readonly DocumentRepository $documents,
        private readonly UserRepository $users,
    ) {}

    public function index(): Response
    {
        $kannBearbeiten = Gate::allows('edit-ablaeufe');
        $items = array_values(array_filter(
            $this->ablaeufe->sortiert(),
            // Entwürfe und Archiv nur für Bearbeitende
            fn (array $a): bool => $kannBearbeiten || ($a['status'] ?? '') === 'gueltig',
        ));

        return Inertia::render('Ablaeufe::index', [
            'ablaeufe' => array_map(fn (array $a): array => [
                'id' => $a['id'],
                'titel' => $a['titel'] ?? '',
                'bereich' => $a['bereich'] ?? 'anderes',
                'status' => $a['status'] ?? 'entwurf',
                'beschreibung' => $a['beschreibung'] ?? '',
                'verantwortlich' => $a['verantwortlich'] ?? '',
                'phasen' => array_map(fn (array $p): string => (string) $p['titel'], (array) ($a['phasen'] ?? [])),
                'schritte' => count((array) ($a['schritte'] ?? [])),
                'updated_at' => $a['updated_at'] ?? null,
            ], $items),
            'bereiche' => Ablauf::BEREICHE,
            'status' => Ablauf::STATUS,
            'darfBearbeiten' => $kannBearbeiten,
        ]);
    }

    public function show(string $id): Response
    {
        $ablauf = $this->ablaeufe->findOrFail($id);
        abort_if(($ablauf['status'] ?? '') !== 'gueltig' && Gate::denies('edit-ablaeufe'), 404);

        return Inertia::render('Ablaeufe::show', [
            'ablauf' => $ablauf + ['geaendert_von_name' => $this->users->names()[$ablauf['geaendert_von'] ?? ''] ?? null],
            'gruppen' => $this->gruppenOptionen(),
            'dokumente' => $this->dokumentOptionen(),
            'bereiche' => Ablauf::BEREICHE,
            'status' => Ablauf::STATUS,
            'darfBearbeiten' => Gate::allows('edit-ablaeufe'),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(string $id): Response
    {
        return $this->form($this->ablaeufe->findOrFail($id));
    }

    public function store(Request $request): RedirectResponse
    {
        $ablauf = $this->ablaeufe->create($this->validated($request));
        $this->toast('Ablauf gespeichert.');

        return redirect('/ablaeufe/'.$ablauf['id']);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ablaeufe->findOrFail($id);
        $this->ablaeufe->update($id, $this->validated($request));
        $this->toast('Ablauf gespeichert.');

        return redirect('/ablaeufe/'.$id);
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->ablaeufe->findOrFail($id);
        $this->ablaeufe->delete($id);
        $this->toast('Ablauf gelöscht.');

        return redirect('/ablaeufe');
    }

    /**
     * @param  array<string, mixed>|null  $ablauf
     */
    private function form(?array $ablauf): Response
    {
        return Inertia::render('Ablaeufe::form', [
            'ablauf' => $ablauf,
            'gruppen' => $this->gruppenOptionen(),
            'dokumente' => $this->dokumentOptionen(),
            'bereiche' => Ablauf::BEREICHE,
            'status' => Ablauf::STATUS,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'bereich' => ['required', Rule::in(array_keys(Ablauf::BEREICHE))],
            'status' => ['required', Rule::in(array_keys(Ablauf::STATUS))],
            'beschreibung' => ['nullable', 'string', 'max:5000'],
            'verantwortlich' => ['nullable', 'string', 'max:200'],
            'gueltig_ab' => ['nullable', 'date'],
            'phasen' => ['required', 'array', 'min:1', 'max:20'],
            'schritte' => ['present', 'array', 'max:500'],
        ], [
            'titel.required' => 'Bitte einen Titel eingeben.',
            'phasen.required' => 'Mindestens eine Phase ist nötig.',
            'phasen.min' => 'Mindestens eine Phase ist nötig.',
        ]);

        $phasen = Ablauf::phasen((array) $data['phasen']);
        if ($phasen === []) {
            throw ValidationException::withMessages(['phasen' => 'Mindestens eine Phase mit Titel ist nötig.']);
        }
        $user = $request->user()?->getAuthIdentifier();

        return [
            'titel' => $data['titel'],
            'bereich' => $data['bereich'],
            'status' => $data['status'],
            'beschreibung' => (string) ($data['beschreibung'] ?? ''),
            'verantwortlich' => (string) ($data['verantwortlich'] ?? ''),
            'gueltig_ab' => $data['gueltig_ab'] ?? null,
            'phasen' => $phasen,
            'schritte' => Ablauf::schritte(
                (array) $data['schritte'],
                array_column($phasen, 'key'),
                array_map(fn (array $g): string => (string) $g['id'], $this->gruppen->all()),
                array_map(fn (array $d): string => (string) $d['id'], $this->documents->all()),
            ),
            'geaendert_von' => is_string($user) ? $user : null,
        ];
    }

    /**
     * @return list<array{id: string, name: string, bemerkung: string}>
     */
    private function gruppenOptionen(): array
    {
        return array_map(fn (array $g): array => [
            'id' => (string) $g['id'],
            'name' => (string) ($g['name'] ?? ''),
            'bemerkung' => (string) ($g['bemerkung'] ?? ''),
        ], $this->gruppen->sortiert());
    }

    /**
     * @return list<array{id: string, title: string, href: string}>
     */
    private function dokumentOptionen(): array
    {
        return array_map(fn (array $d): array => [
            'id' => (string) $d['id'],
            'title' => (string) ($d['title'] ?? ''),
            'href' => ($d['kind'] ?? '') === 'link' ? (string) ($d['url'] ?? '') : '/dokumente/'.$d['id'].'/download',
        ], $this->documents->all());
    }
}
