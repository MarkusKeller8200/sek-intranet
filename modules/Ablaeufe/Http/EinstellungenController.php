<?php

namespace Modules\Ablaeufe\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ablaeufe\Data\Contracts\ListenRepository;
use Modules\Ablaeufe\Data\Contracts\ListeRepository;
use Modules\Ablaeufe\Listen;

/**
 * Einstellungen der Abläufe (#52): Orte, Fachbereiche, Rollen, Zeitlich, Mitwirkung – erweiterbare Tabellen.
 */
class EinstellungenController extends Controller
{
    public function __construct(private readonly ListenRepository $listen) {}

    public function index(Request $request): Response
    {
        $aktiv = $request->string('liste')->toString();
        if (! Listen::gibt($aktiv)) {
            $aktiv = (string) array_key_first(Listen::alle());
        }
        $anzahl = [];
        foreach (array_keys(Listen::alle()) as $key) {
            $anzahl[$key] = count($this->listen->liste($key)->all());
        }

        return Inertia::render('Ablaeufe::einstellungen', [
            'listen' => array_map(fn (array $l): array => ['titel' => $l['titel'], 'einzahl' => $l['einzahl'], 'beschreibung' => $l['beschreibung']], Listen::alle()),
            'anzahl' => $anzahl,
            'aktiv' => $aktiv,
            'eintraege' => $this->listen->liste($aktiv)->sortiert(),
            'fachbereiche' => $this->optionen('fachbereiche'),
            'orte' => $this->optionen('orte'),
            'ortTypen' => Listen::ORT_TYPEN,
        ]);
    }

    public function store(Request $request, string $liste): RedirectResponse
    {
        $repo = $this->repo($liste);
        $data = $this->validated($request, $liste, null);
        $repo->create($data + ['position' => count($repo->all()) + 1]);
        $this->toast(Listen::alle()[$liste]['einzahl'].' gespeichert.');

        return redirect('/ablaeufe/einstellungen?liste='.$liste);
    }

    public function update(Request $request, string $liste, string $id): RedirectResponse
    {
        $repo = $this->repo($liste);
        $repo->findOrFail($id);
        $repo->update($id, $this->validated($request, $liste, $id));
        $this->toast(Listen::alle()[$liste]['einzahl'].' gespeichert.');

        return redirect('/ablaeufe/einstellungen?liste='.$liste);
    }

    public function sort(Request $request, string $liste): RedirectResponse
    {
        $repo = $this->repo($liste);
        $ids = (array) $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['string']])['ids'];
        foreach (array_values($ids) as $i => $id) {
            if ($repo->find((string) $id) !== null) {
                $repo->update((string) $id, ['position' => $i + 1]);
            }
        }

        return redirect('/ablaeufe/einstellungen?liste='.$liste);
    }

    public function destroy(string $liste, string $id): RedirectResponse
    {
        $repo = $this->repo($liste);
        $item = $repo->findOrFail($id);
        $verwendet = match ($liste) {
            'fachbereiche' => count(array_filter($this->listen->liste('rollen')->all(), fn (array $r): bool => ($r['fachbereich_id'] ?? null) === $id)),
            'orte' => count(array_filter($repo->all(), fn (array $o): bool => ($o['parent_id'] ?? null) === $id)),
            default => 0,
        };
        if ($verwendet > 0) {
            $this->toast("«{$item['bezeichnung']}» wird noch verwendet ({$verwendet}) und kann nicht gelöscht werden.", 'error');

            return redirect('/ablaeufe/einstellungen?liste='.$liste);
        }
        $repo->delete($id);
        $this->toast("«{$item['bezeichnung']}» gelöscht.");

        return redirect('/ablaeufe/einstellungen?liste='.$liste);
    }

    private function repo(string $liste): ListeRepository
    {
        abort_unless(Listen::gibt($liste), 404);

        return $this->listen->liste($liste);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $liste, ?string $id): array
    {
        $rules = [
            'kuerzel' => ['required', 'string', 'max:20'],
            'bezeichnung' => ['required', 'string', 'max:200'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url:http,https', 'max:500'],
            'notiz' => ['nullable', 'string', 'max:5000'],
        ];
        if ($liste === 'rollen') {
            $rules['fachbereich_id'] = ['nullable', Rule::in(array_column($this->listen->liste('fachbereiche')->all(), 'id'))];
        }
        if ($liste === 'orte') {
            $rules['typ'] = ['required', Rule::in(array_keys(Listen::ORT_TYPEN))];
            $rules['parent_id'] = ['nullable', Rule::in(array_column($this->listen->liste('orte')->all(), 'id'))];
        }
        $data = $request->validate($rules, [
            'kuerzel.required' => 'Bitte eine Abkürzung eingeben.',
            'bezeichnung.required' => 'Bitte eine Bezeichnung eingeben.',
            'url.url' => 'Die URL muss mit http:// oder https:// beginnen.',
        ]);

        $kuerzel = trim((string) $data['kuerzel']);
        foreach ($this->listen->liste($liste)->all() as $row) {
            if ($row['id'] !== $id && mb_strtolower((string) ($row['kuerzel'] ?? '')) === mb_strtolower($kuerzel)) {
                throw ValidationException::withMessages(['kuerzel' => "Die Abkürzung «{$kuerzel}» gibt es schon."]);
            }
        }
        $result = [
            'kuerzel' => $kuerzel,
            'bezeichnung' => trim((string) $data['bezeichnung']),
            'beschreibung' => (string) ($data['beschreibung'] ?? ''),
            'url' => (string) ($data['url'] ?? ''),
            'notiz' => (string) ($data['notiz'] ?? ''),
        ];
        if ($liste === 'rollen') {
            $result['fachbereich_id'] = $data['fachbereich_id'] ?? null;
        }
        if ($liste === 'orte') {
            $parent = $data['parent_id'] ?? null;
            // kein Ort unter sich selbst oder unter einem eigenen Unterort
            if ($parent !== null && $id !== null && $this->istUnterort((string) $parent, $id)) {
                throw ValidationException::withMessages(['parent_id' => 'Ein Ort kann nicht unter sich selbst oder einem seiner Unterorte stehen.']);
            }
            $result['typ'] = $data['typ'];
            $result['parent_id'] = $parent;
        }

        return $result;
    }

    private function istUnterort(string $kandidat, string $id): bool
    {
        $orte = array_column($this->listen->liste('orte')->all(), null, 'id');
        for ($n = $kandidat, $i = 0; $n !== null && $i < 50; $i++) {
            if ($n === $id) {
                return true;
            }
            $n = isset($orte[$n]['parent_id']) && is_string($orte[$n]['parent_id']) ? $orte[$n]['parent_id'] : null;
        }

        return false;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function optionen(string $liste): array
    {
        return array_map(fn (array $r): array => [
            'id' => (string) $r['id'],
            'label' => trim((string) ($r['kuerzel'] ?? '').' – '.(string) ($r['bezeichnung'] ?? '')),
        ], $this->listen->liste($liste)->sortiert());
    }
}
