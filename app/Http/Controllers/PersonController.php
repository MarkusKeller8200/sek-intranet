<?php

namespace App\Http\Controllers;

use App\Data\Contracts\UserRepository;
use App\Support\PersonFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    /** Gesammelt änderbare Felder (Auswahl in der Tabelle). */
    private const BULK_FIELDS = ['role', 'school', 'active', 'function', 'department'];

    private const NAME_FIELDS = ['first_name', 'last_name', 'name'];

    public function __construct(private readonly UserRepository $users) {}

    public function index(): Response
    {
        $admin = Gate::allows('manage-users');
        $people = $admin ? $this->users->all() : $this->users->active();

        usort($people, fn (array $a, array $b): int => strnatcasecmp(
            ($a['last_name'] ?? '').' '.($a['first_name'] ?? ''),
            ($b['last_name'] ?? '').' '.($b['first_name'] ?? ''),
        ));

        return Inertia::render('people/index', [
            'people' => array_map(fn (array $p): array => PersonFields::visible($p, $admin), $people),
            'groups' => PersonFields::groups($admin),
        ]);
    }

    public function show(string $id): Response
    {
        $admin = Gate::allows('manage-users');
        $person = $this->users->findOrFail($id);
        abort_if(! $admin && ($person['active'] ?? false) !== true, 404);

        return Inertia::render('people/show', [
            'person' => PersonFields::visible($person, $admin),
            'groups' => PersonFields::groups($admin),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('people/form', ['person' => null, 'groups' => PersonFields::groups(true)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $person = $this->users->create($this->validateData($request->all()));
        $this->pluginEvent('person.created', $person);
        $this->toast('Person gespeichert.');

        return redirect()->route('people.index');
    }

    public function edit(string $id): Response
    {
        return Inertia::render('people/form', ['person' => $this->users->findOrFail($id), 'groups' => PersonFields::groups(true)]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->users->findOrFail($id);
        $data = $this->validateData($request->all(), $id);

        if ($this->removesOwnAdmin($request, $id, $data)) {
            return back()->withErrors(['role' => 'Die eigene Administrationsrolle kann nicht entfernt werden.']);
        }

        $person = $this->users->update($id, $data);
        $this->pluginEvent('person.updated', $person);
        $this->toast('Person aktualisiert.');

        return redirect()->route('people.index');
    }

    /**
     * Ein Feld direkt in der Tabelle ändern (JSON).
     */
    public function updateField(Request $request, string $id): JsonResponse
    {
        $field = (string) $request->input('field');
        $editable = array_values(array_filter(
            array_keys(PersonFields::rules()),
            fn (string $key): bool => $key !== 'name',
        ));
        abort_unless(in_array($field, $editable, true), 422, 'Dieses Feld kann nicht geändert werden.');

        $existing = $this->users->findOrFail($id);
        $input = [$field => $request->input('value')] + $existing;
        if (in_array($field, self::NAME_FIELDS, true)) {
            unset($input['name']);
        }
        $data = $this->validateData($input, $id);

        if ($this->removesOwnAdmin($request, $id, $data)) {
            return response()->json(['message' => 'Die eigene Administrationsrolle kann nicht entfernt werden.'], 422);
        }

        $changes = in_array($field, self::NAME_FIELDS, true)
            ? array_intersect_key($data, array_flip(self::NAME_FIELDS))
            : [$field => $data[$field] ?? null];

        $person = $this->users->update($id, $changes);
        $this->pluginEvent('person.updated', $person);

        return response()->json(['person' => $person]);
    }

    /**
     * Ein Feld für mehrere Personen setzen (JSON). Das eigene Konto wird bei Rolle/Aktiv übersprungen.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $field = (string) $request->input('field');
        abort_unless(in_array($field, self::BULK_FIELDS, true), 422, 'Dieses Feld kann nicht gesammelt geändert werden.');

        $people = [];
        $skipped = 0;
        foreach ($this->selectedIds($request) as $id) {
            $existing = $this->users->find($id);
            if ($existing === null) {
                $skipped++;

                continue;
            }

            $data = $this->validateData([$field => $request->input('value')] + $existing, $id);
            if ($this->removesOwnAdmin($request, $id, $data)) {
                $skipped++;

                continue;
            }

            $person = $this->users->update($id, [$field => $data[$field] ?? null]);
            $this->pluginEvent('person.updated', $person);
            $people[] = $person;
        }

        return response()->json([
            'message' => count($people).' Personen geändert'.($skipped > 0 ? ", {$skipped} übersprungen (eigenes Konto oder nicht gefunden)" : '').'.',
            'people' => $people,
        ]);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        abort_if($id === (string) $request->user()?->getAuthIdentifier(), 422, 'Das eigene Konto kann nicht gelöscht werden.');

        $person = $this->users->findOrFail($id);
        $this->users->delete($id);
        $this->pluginEvent('person.deleted', $person);
        $this->toast('Person gelöscht.');

        return redirect()->route('people.index');
    }

    /**
     * Mehrere Personen löschen (JSON). Das eigene Konto wird nie gelöscht.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $own = (string) $request->user()?->getAuthIdentifier();
        $deleted = 0;
        $skipped = 0;

        foreach ($this->selectedIds($request) as $id) {
            $person = $id === $own ? null : $this->users->find($id);
            if ($person === null) {
                $skipped++;

                continue;
            }

            $this->users->delete($id);
            $this->pluginEvent('person.deleted', $person);
            $deleted++;
        }

        return response()->json([
            'message' => "{$deleted} Personen gelöscht".($skipped > 0 ? ", {$skipped} übersprungen (eigenes Konto oder nicht gefunden)" : '').'.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function selectedIds(Request $request): array
    {
        $ids = array_values(array_unique(array_map('strval', (array) $request->input('ids', []))));
        abort_if($ids === [] || count($ids) > 1000, 422, 'Keine oder zu viele Personen ausgewählt.');

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function removesOwnAdmin(Request $request, string $id, array $data): bool
    {
        return $id === (string) $request->user()?->getAuthIdentifier()
            && (($data['role'] ?? null) !== 'admin' || ($data['active'] ?? null) !== true);
    }

    /**
     * Prüft alle Felder nach Schema und liefert einen vollständigen Datensatz (fehlende Felder = null).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateData(array $input, ?string $ignoreId = null): array
    {
        $input = PersonFields::withNames($input);
        foreach (['email', 'private_email'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = mb_strtolower(trim($input[$key]));
            }
        }

        $rules = PersonFields::rules();
        $validator = validator($input, $rules, [], array_map(
            fn (array $f): string => $f['label'],
            array_column(PersonFields::fields(true), null, 'key'),
        ));

        // Eindeutige E-Mail (in der Datenbank später ein UNIQUE-Index).
        $validator->after(function (Validator $validator) use ($input, $ignoreId): void {
            $existing = $this->users->findByEmail((string) ($input['email'] ?? ''));

            if ($existing !== null && ($existing['id'] ?? null) !== $ignoreId) {
                $validator->errors()->add('email', 'Diese E-Mail-Adresse ist bereits erfasst.');
            }
        });

        $data = $validator->validate();

        $complete = [];
        foreach (array_keys($rules) as $key) {
            $complete[$key] = $data[$key] ?? null;
        }
        $complete['active'] = filter_var($input['active'] ?? false, FILTER_VALIDATE_BOOL);

        return $complete;
    }
}
