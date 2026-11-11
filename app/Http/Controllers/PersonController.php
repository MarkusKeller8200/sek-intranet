<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use App\Support\AuditLog;
use App\Support\GridViews;
use App\Support\PersonDocuments;
use App\Support\PersonFields;
use App\Support\PersonLinks;
use App\Support\PersonTypes;
use App\Support\PersonValidator;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PersonController extends Controller
{
    /** Gesammelt änderbare Felder (Auswahl in der Tabelle). */
    private const BULK_FIELDS = ['role', 'school', 'active', 'function', 'department'];

    private const NAME_FIELDS = ['first_name', 'last_name', 'name'];

    private const MAX_CELLS = 5000;

    public const GRID = 'people';

    public const GRID_STUDENTS = 'students';

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuditLog $audit,
        private readonly PersonLinks $links,
    ) {}

    public function index(Request $request, GridViews $views): Response
    {
        return $this->list($request, $views, null);
    }

    /** Schüler:innen: gleiche Tabelle, nur Personenart «Schüler:in». */
    public function students(Request $request, GridViews $views): Response
    {
        return $this->list($request, $views, PersonTypes::STUDENT);
    }

    private function list(Request $request, GridViews $views, ?string $type): Response
    {
        $admin = Gate::allows('manage-users');
        $people = PersonTypes::visibleFor($admin ? $this->users->all() : $this->users->active(), $request->user());
        if ($type !== null) {
            $people = array_values(array_filter($people, fn (array $p): bool => PersonTypes::of($p) === $type));
        }

        usort($people, fn (array $a, array $b): int => strnatcasecmp(
            ($a['last_name'] ?? '').' '.($a['first_name'] ?? ''),
            ($b['last_name'] ?? '').' '.($b['first_name'] ?? ''),
        ));

        $pending = $this->links->pendingCounts();

        return Inertia::render('people/index', [
            'people' => array_map(fn (array $p): array => PersonFields::visible($p, $admin) + [
                'sync_pending' => $pending[(string) $p['id']] ?? 0,
            ], $people),
            'groups' => PersonFields::groups($admin),
            'views' => $views->for($type === null ? self::GRID : self::GRID_STUDENTS, $request->user()),
            'scope' => $type === null ? null : [
                'type' => $type,
                'title' => 'Schüler:innen',
                'description' => 'Schüler:innen nach Klasse – gleiche Tabelle wie «Personen», nur Personenart Schüler:in.',
                'href' => '/schueler',
            ],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $admin = Gate::allows('manage-users');
        $person = $this->users->findOrFail($id);
        abort_if(! $admin && ($person['active'] ?? false) !== true, 404);
        abort_if(PersonTypes::visibleFor([$person], $request->user()) === [], 404);
        $viewer = $request->user();

        return Inertia::render('people/show', [
            'person' => PersonFields::visible($person, $admin),
            'groups' => PersonFields::groups($admin),
            // Verlauf enthält auch Admin-Felder: nur für die Administration
            'history' => $admin ? $this->audit->for('users', $id) : [],
            'links' => $this->links->forPerson($id, $person),
            'systems' => PersonLinks::systems(),
            'canManageLinks' => $admin,
            // Dokumente aus Modulen (#208), Sichtbarkeit entscheidet das Modul
            'documents' => app(PersonDocuments::class)->forPerson($id, $viewer instanceof IntranetUser ? $viewer : null),
        ]);
    }

    public function create(Request $request): Response
    {
        $type = $request->query('art');

        return Inertia::render('people/form', [
            'person' => null,
            'groups' => PersonFields::groups(true),
            // Vorbelegung, z. B. aus «Schüler:innen» → Neue Person
            'defaults' => is_string($type) && isset(PersonTypes::all()[$type])
                ? ['person_type' => $type] + ($type === PersonTypes::STUDENT || $type === 'eltern' ? ['role' => 'kein-zugang'] : [])
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $person = $this->users->create($this->validateData($request->all()));
        $this->audit->record('users', null, $person, 'Formular', $request->user());
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
        $before = $this->users->findOrFail($id);
        $data = $this->validateData($request->all(), $id);

        if ($this->removesOwnAdmin($request, $id, $data)) {
            return back()->withErrors(['role' => 'Die eigene Administrationsrolle kann nicht entfernt werden.']);
        }

        $person = $this->users->update($id, $data);
        $this->audit->record('users', $before, $person, 'Formular', $request->user());
        $this->pluginEvent('person.updated', $person);
        $this->toast('Person aktualisiert.');

        return redirect()->route('people.index');
    }

    /**
     * Ein Feld direkt in der Tabelle ändern (JSON). Kurzform von cells().
     */
    public function updateField(Request $request, string $id): JsonResponse
    {
        $result = $this->applyCells($request, [['id' => $id, 'field' => (string) $request->input('field'), 'value' => $request->input('value')]], 'Tabelle');

        return isset($result['errors'])
            ? response()->json(['message' => $result['errors'][0]['message'], 'errors' => $result['errors']], 422)
            : response()->json(['person' => $result['people'][0] ?? null]);
    }

    /**
     * Mehrere Zellen auf einmal ändern (Einfügen aus Excel, Rückgängig, Löschen eines Bereichs).
     * Alles oder nichts: Ist ein Wert ungültig, wird nichts gespeichert.
     */
    public function cells(Request $request): JsonResponse
    {
        $changes = array_values(array_filter((array) $request->input('changes', []), 'is_array'));
        abort_if($changes === [] || count($changes) > self::MAX_CELLS, 422, 'Keine oder zu viele Änderungen.');

        $source = in_array($request->input('source'), ['Tabelle', 'Einfügen', 'Rückgängig', 'Wiederholen', 'Löschen'], true)
            ? (string) $request->input('source')
            : 'Tabelle';
        $result = $this->applyCells($request, $changes, $source);

        return isset($result['errors'])
            ? response()->json(['message' => count($result['errors']).' ungültige Werte – nichts gespeichert. '.trim(($result['errors'][0]['name'] ?? '').': '.$result['errors'][0]['message'], ': '), 'errors' => $result['errors']], 422)
            : response()->json(['people' => $result['people']]);
    }

    /**
     * @param  list<array<string, mixed>>  $changes  je {id, field, value}
     * @return array{people: list<array<string, mixed>>}|array{errors: non-empty-list<array{id: string, field: string, message: string, name?: string}>}
     */
    private function applyCells(Request $request, array $changes, string $source): array
    {
        $editable = array_values(array_filter(array_keys(PersonFields::rules()), fn (string $key): bool => $key !== 'name'));
        $byPerson = [];
        $errors = [];

        foreach ($changes as $change) {
            $id = (string) ($change['id'] ?? '');
            $field = (string) ($change['field'] ?? '');
            if (! in_array($field, $editable, true)) {
                $errors[] = ['id' => $id, 'field' => $field, 'message' => "Feld «{$field}» kann nicht geändert werden."];

                continue;
            }
            $byPerson[$id][$field] = $change['value'] ?? null;
        }

        $planned = [];
        foreach ($byPerson as $id => $fields) {
            $existing = $this->users->find((string) $id);
            if ($existing === null) {
                $errors[] = ['id' => (string) $id, 'field' => '', 'message' => 'Person nicht gefunden.'];

                continue;
            }

            $input = $fields + $existing;
            if (array_intersect(array_keys($fields), self::NAME_FIELDS) !== []) {
                unset($input['name']);
            }

            $check = $this->check($input, (string) $id);
            if (isset($check['errors'])) {
                foreach ($check['errors'] as $field => $message) {
                    $errors[] = ['id' => (string) $id, 'field' => $field, 'message' => $message, 'name' => (string) ($existing['name'] ?? '')];
                }

                continue;
            }
            if ($this->removesOwnAdmin($request, (string) $id, $check['data'])) {
                $errors[] = ['id' => (string) $id, 'field' => 'role', 'message' => 'Die eigene Administrationsrolle kann nicht entfernt werden.'];

                continue;
            }

            $keys = array_keys($fields);
            if (array_intersect($keys, self::NAME_FIELDS) !== []) {
                $keys = array_unique([...$keys, ...self::NAME_FIELDS]);
            }
            $planned[(string) $id] = [$existing, array_intersect_key($check['data'], array_flip($keys))];
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $people = [];
        foreach ($planned as $id => [$before, $update]) {
            $person = $this->users->update($id, $update);
            $this->audit->record('users', $before, $person, $source, $request->user());
            $this->pluginEvent('person.updated', $person);
            $people[] = $person;
        }

        return ['people' => $people];
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
            $this->audit->record('users', $existing, $person, 'Sammeländerung', $request->user());
            $this->pluginEvent('person.updated', $person);
            $people[] = $person;
        }

        return response()->json([
            'message' => count($people).' Personen geändert'.($skipped > 0 ? ", {$skipped} übersprungen (eigenes Konto oder nicht gefunden)" : '').'.',
            'people' => $people,
        ]);
    }

    /**
     * Einen früheren Wert aus dem Verlauf wiederherstellen.
     */
    public function restore(Request $request, string $id, string $entry): RedirectResponse
    {
        $log = $this->audit->find($entry);
        abort_if($log === null || ($log['record_id'] ?? null) !== $id || ($log['collection'] ?? null) !== 'users', 404);

        $field = (string) $request->input('field');
        $changes = (array) ($log['changes'] ?? []);
        abort_unless(isset($changes[$field]) && is_array($changes[$field]), 422, 'Dieser Wert ist im Verlauf nicht vorhanden.');

        $result = $this->applyCells($request, [['id' => $id, 'field' => $field, 'value' => $changes[$field]['old'] ?? null]], 'Wiederherstellung');
        if (isset($result['errors'])) {
            return back()->withErrors(['history' => $result['errors'][0]['message']]);
        }

        $this->toast('Früherer Wert wiederhergestellt.');

        return back();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        abort_if($id === (string) $request->user()?->getAuthIdentifier(), 422, 'Das eigene Konto kann nicht gelöscht werden.');

        $person = $this->users->findOrFail($id);
        $this->users->delete($id);
        $this->links->deleteForPerson($id);
        $this->audit->record('users', $person, null, 'Formular', $request->user());
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
            $this->links->deleteForPerson($id);
            $this->audit->record('users', $person, null, 'Sammellöschung', $request->user());
            $this->pluginEvent('person.deleted', $person);
            $deleted++;
        }

        return response()->json([
            'message' => "{$deleted} Personen gelöscht".($skipped > 0 ? ", {$skipped} übersprungen (eigenes Konto oder nicht gefunden)" : '').'.',
        ]);
    }

    /**
     * Export als CSV oder Excel. Nur Felder, die die Person sehen darf; nur sichtbare Personen.
     */
    public function export(Request $request): HttpResponse
    {
        $admin = Gate::allows('manage-users');
        $fields = array_column(PersonFields::fields($admin), null, 'key');
        $columns = array_values(array_filter(array_map('strval', (array) $request->input('columns', [])), fn (string $c): bool => isset($fields[$c])));
        abort_if($columns === [], 422, 'Keine Spalten gewählt.');

        $ids = array_flip(array_map('strval', (array) $request->input('ids', [])));
        $people = array_values(array_filter(
            PersonTypes::visibleFor($admin ? $this->users->all() : $this->users->active(), $request->user()),
            fn (array $p): bool => $ids === [] || isset($ids[(string) $p['id']]),
        ));

        $header = array_map(fn (string $c): string => $fields[$c]['label'], $columns);
        $rows = array_map(fn (array $p): array => array_map(fn (string $c): string => self::exportValue($fields[$c], $p[$c] ?? null), $columns), $people);

        $xlsx = $request->input('format') === 'xlsx';
        $name = 'personen-'.now()->format('Y-m-d');

        return response($xlsx ? SpreadsheetWriter::xlsx($header, $rows, 'Personen') : SpreadsheetWriter::csv($header, $rows), 200, [
            'Content-Type' => $xlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.($xlsx ? '.xlsx' : '.csv').'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @param  array{type: string, options: array<string, string>|null}  $field
     */
    private static function exportValue(array $field, mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $field['type'] === 'bool' => $value === true ? 'Ja' : 'Nein',
            $field['type'] === 'enum' => (string) ($field['options'][(string) $value] ?? $value),
            $field['type'] === 'date' && is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1 => "{$m[3]}.{$m[2]}.{$m[1]}",
            is_scalar($value) => (string) $value,
            default => '',
        };
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
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateData(array $input, ?string $ignoreId = null): array
    {
        $check = $this->check($input, $ignoreId);
        if (isset($check['errors'])) {
            throw ValidationException::withMessages($check['errors']);
        }

        return $check['data'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{data: array<string, mixed>}|array{errors: array<string, string>}
     */
    private function check(array $input, ?string $ignoreId = null): array
    {
        return app(PersonValidator::class)->check($input, $ignoreId);
    }
}
