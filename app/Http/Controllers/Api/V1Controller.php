<?php

namespace App\Http\Controllers\Api;

use App\Data\Contracts\AnnouncementRepository;
use App\Data\Contracts\DocumentRepository;
use App\Data\Contracts\EventRepository;
use App\Data\Contracts\UserRepository;
use App\Http\Controllers\Controller;
use App\Support\AuditLog;
use App\Support\DocumentAccess;
use App\Support\PersonFields;
use App\Support\PersonTypes;
use App\Support\PersonValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * REST-API v1 (JSON). Beschreibung: /api/openapi.json, Tester: Intranet → Prototyp → API.
 * Rechte wie im Intranet: Lesen für alle Tokens, Personen schreiben nur für die Administration.
 */
class V1Controller extends Controller
{
    private const MAX_LIMIT = 1000;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PersonValidator $validator,
        private readonly AuditLog $audit,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $token = (array) $request->attributes->get('api_token', []);

        return response()->json(['data' => [
            'user' => $request->user()?->jsonSerialize(),
            'admin' => Gate::allows('manage-users'),
            'token' => [
                'name' => $token['name'] ?? null,
                'abilities' => $token['abilities'] ?? [],
                'expires_at' => $token['expires_at'] ?? null,
            ],
        ]]);
    }

    public function people(Request $request): JsonResponse
    {
        $admin = Gate::allows('manage-users');
        $people = PersonTypes::visibleFor($admin ? $this->users->all() : $this->users->active(), $request->user());

        $type = (string) $request->query('person_type', '');
        if ($type !== '') {
            $people = array_values(array_filter($people, fn (array $p): bool => PersonTypes::of($p) === $type));
        }

        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $school = (string) $request->query('school', '');
        $active = $request->query('active');

        $people = array_values(array_filter($people, function (array $p) use ($search, $school, $active): bool {
            if ($school !== '' && ($p['school'] ?? null) !== $school) {
                return false;
            }
            if ($active !== null && ($p['active'] ?? false) !== filter_var($active, FILTER_VALIDATE_BOOL)) {
                return false;
            }

            return $search === '' || str_contains(mb_strtolower(implode(' ', [
                $p['name'] ?? '', $p['email'] ?? '', $p['function'] ?? '', $p['short'] ?? '',
            ])), $search);
        }));
        usort($people, fn (array $a, array $b): int => strnatcasecmp(
            ($a['last_name'] ?? '').' '.($a['first_name'] ?? ''),
            ($b['last_name'] ?? '').' '.($b['first_name'] ?? ''),
        ));

        return $this->paginate($request, array_map(fn (array $p): array => PersonFields::visible($p, $admin), $people));
    }

    public function person(Request $request, string $id): JsonResponse
    {
        $admin = Gate::allows('manage-users');
        $person = $this->users->find($id);
        if ($person === null || (! $admin && ($person['active'] ?? false) !== true) || PersonTypes::visibleFor([$person], $request->user()) === []) {
            return response()->json(['message' => 'Person nicht gefunden.'], 404);
        }

        return response()->json(['data' => PersonFields::visible($person, $admin)]);
    }

    public function createPerson(Request $request): JsonResponse
    {
        if (! Gate::allows('manage-users')) {
            return response()->json(['message' => 'Nur die Administration darf Personen anlegen.'], 403);
        }

        $check = $this->validator->check($this->input($request) + ['role' => 'mitarbeitende', 'school' => 'gesamt', 'active' => true]);
        if (isset($check['errors'])) {
            return $this->invalid($check['errors']);
        }

        $person = $this->users->create($check['data']);
        $this->audit->record('users', null, $person, 'API', $request->user());
        $this->pluginEvent('person.created', $person);

        return response()->json(['data' => $person], 201);
    }

    public function updatePerson(Request $request, string $id): JsonResponse
    {
        if (! Gate::allows('manage-users')) {
            return response()->json(['message' => 'Nur die Administration darf Personen ändern.'], 403);
        }
        $before = $this->users->find($id);
        if ($before === null) {
            return response()->json(['message' => 'Person nicht gefunden.'], 404);
        }

        $input = $this->input($request);
        $merged = $input + $before;
        if (array_intersect(array_keys($input), ['first_name', 'last_name']) !== []) {
            unset($merged['name']);
        }
        $check = $this->validator->check($merged, $id);
        if (isset($check['errors'])) {
            return $this->invalid($check['errors']);
        }
        if ($id === (string) $request->user()?->getAuthIdentifier()
            && (($check['data']['role'] ?? null) !== 'admin' || ($check['data']['active'] ?? null) !== true)) {
            return response()->json(['message' => 'Die eigene Administrationsrolle kann nicht entfernt werden.'], 422);
        }

        $person = $this->users->update($id, $check['data']);
        $this->audit->record('users', $before, $person, 'API', $request->user());
        $this->pluginEvent('person.updated', $person);

        return response()->json(['data' => $person]);
    }

    public function deletePerson(Request $request, string $id): JsonResponse
    {
        if (! Gate::allows('manage-users')) {
            return response()->json(['message' => 'Nur die Administration darf Personen löschen.'], 403);
        }
        if ($id === (string) $request->user()?->getAuthIdentifier()) {
            return response()->json(['message' => 'Das eigene Konto kann nicht gelöscht werden.'], 422);
        }
        $person = $this->users->find($id);
        if ($person === null) {
            return response()->json(['message' => 'Person nicht gefunden.'], 404);
        }

        $this->users->delete($id);
        $this->audit->record('users', $person, null, 'API', $request->user());
        $this->pluginEvent('person.deleted', $person);

        return response()->json(null, 204);
    }

    public function announcements(Request $request, AnnouncementRepository $announcements): JsonResponse
    {
        return $this->paginate($request, $announcements->published());
    }

    public function events(Request $request, EventRepository $events): JsonResponse
    {
        return $this->paginate($request, $events->upcoming());
    }

    public function documents(Request $request, DocumentRepository $documents): JsonResponse
    {
        // Interner Dateipfad bleibt intern; Download über das Intranet
        return $this->paginate($request, array_map(
            fn (array $d): array => array_diff_key($d, ['file_path' => true]) + [
                'download_url' => ($d['kind'] ?? null) === 'file' ? url("/dokumente/{$d['id']}/download") : null,
            ],
            DocumentAccess::filter($documents->sorted(), $request->user()),
        ));
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'roles' => config('intranet.roles'),
            'schools' => config('intranet.schools'),
            'audiences' => config('intranet.audiences'),
            'announcement_categories' => config('intranet.announcement_categories'),
            'event_categories' => config('intranet.event_categories'),
            'document_categories' => config('intranet.document_categories'),
        ]]);
    }

    /**
     * Nur bekannte, beschreibbare Personenfelder aus dem JSON-Körper.
     *
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        $allowed = array_flip(array_diff(array_keys(PersonFields::rules()), ['name']));
        $input = array_intersect_key($request->json()->all(), $allowed);

        // Nur «name» geliefert: Vor- und Nachname daraus bilden
        if ($request->json('name') !== null && ! isset($input['first_name']) && ! isset($input['last_name'])) {
            $input['name'] = $request->json('name');
            $input['first_name'] = null;
            $input['last_name'] = null;
        }

        return $input;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['message' => (string) reset($errors), 'errors' => $errors], 422);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function paginate(Request $request, array $items): JsonResponse
    {
        $limit = max(1, min(self::MAX_LIMIT, (int) $request->query('limit', '100')));
        $offset = max(0, (int) $request->query('offset', '0'));

        return response()->json([
            'data' => array_slice($items, $offset, $limit),
            'meta' => ['total' => count($items), 'limit' => $limit, 'offset' => $offset],
        ]);
    }
}
