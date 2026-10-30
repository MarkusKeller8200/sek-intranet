<?php

namespace App\Http\Controllers;

use App\Data\Contracts\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function __construct(private readonly UserRepository $users) {}

    public function index(): Response
    {
        $people = Gate::allows('manage-users') ? $this->users->all() : $this->users->active();

        usort($people, fn (array $a, array $b): int => strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return Inertia::render('people/index', ['people' => $people]);
    }

    public function create(): Response
    {
        return Inertia::render('people/form', ['person' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $person = $this->users->create($this->validated($request));
        $this->pluginEvent('person.created', $person);
        $this->toast('Person gespeichert.');

        return redirect()->route('people.index');
    }

    public function edit(string $id): Response
    {
        return Inertia::render('people/form', ['person' => $this->users->findOrFail($id)]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->users->findOrFail($id);
        $data = $this->validated($request, $id);

        if ($this->removesOwnAdmin($request, $id, $data)) {
            return back()->withErrors(['role' => 'Die eigene Administrationsrolle kann nicht entfernt werden.']);
        }

        $person = $this->users->update($id, $data);
        $this->pluginEvent('person.updated', $person);
        $this->toast('Person aktualisiert.');

        return redirect()->route('people.index');
    }

    /** Bearbeitbare Felder in der Tabelle (direkt und als Sammeländerung). */
    private const GRID_FIELDS = ['name', 'email', 'role', 'function', 'school', 'phone', 'active'];

    private const BULK_FIELDS = ['role', 'school', 'active', 'function'];

    /**
     * Ein Feld direkt in der Tabelle ändern (JSON).
     */
    public function updateField(Request $request, string $id): JsonResponse
    {
        $field = (string) $request->input('field');
        abort_unless(in_array($field, self::GRID_FIELDS, true), 422, 'Dieses Feld kann nicht geändert werden.');

        $existing = $this->users->findOrFail($id);
        $data = $this->validateData([$field => $request->input('value')] + $existing, $id);

        if ($this->removesOwnAdmin($request, $id, $data)) {
            return response()->json(['message' => 'Die eigene Administrationsrolle kann nicht entfernt werden.'], 422);
        }

        $person = $this->users->update($id, [$field => $data[$field]]);
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

        $ids = array_values(array_unique(array_map('strval', (array) $request->input('ids', []))));
        abort_if($ids === [] || count($ids) > 1000, 422, 'Keine oder zu viele Personen ausgewählt.');

        $people = [];
        $skipped = 0;
        foreach ($ids as $id) {
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

            $person = $this->users->update($id, [$field => $data[$field]]);
            $this->pluginEvent('person.updated', $person);
            $people[] = $person;
        }

        return response()->json([
            'message' => count($people).' Personen geändert'.($skipped > 0 ? ", {$skipped} übersprungen (eigenes Konto oder nicht gefunden)" : '').'.',
            'people' => $people,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function removesOwnAdmin(Request $request, string $id, array $data): bool
    {
        return $id === (string) $request->user()?->getAuthIdentifier()
            && (($data['role'] ?? null) !== 'admin' || ($data['active'] ?? null) !== true);
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
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?string $ignoreId = null): array
    {
        return $this->validateData($request->all(), $ignoreId);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateData(array $input, ?string $ignoreId = null): array
    {
        $validator = validator($input, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200'],
            'role' => ['required', Rule::in($this->keys('roles'))],
            'function' => ['nullable', 'string', 'max:200'],
            'school' => ['required', Rule::in($this->keys('schools'))],
            'phone' => ['nullable', 'string', 'max:50'],
            'active' => ['boolean'],
        ]);

        // Eindeutige E-Mail (in der Datenbank später ein UNIQUE-Index).
        $validator->after(function (Validator $validator) use ($input, $ignoreId): void {
            $existing = $this->users->findByEmail(mb_strtolower(trim((string) ($input['email'] ?? ''))));

            if ($existing !== null && ($existing['id'] ?? null) !== $ignoreId) {
                $validator->errors()->add('email', 'Diese E-Mail-Adresse ist bereits erfasst.');
            }
        });

        $data = $validator->validate();
        $data['email'] = mb_strtolower(trim((string) $data['email']));
        $data['active'] = filter_var($input['active'] ?? false, FILTER_VALIDATE_BOOL);
        $data['function'] = $data['function'] ?? null;
        $data['phone'] = $data['phone'] ?? null;

        return $data;
    }
}
