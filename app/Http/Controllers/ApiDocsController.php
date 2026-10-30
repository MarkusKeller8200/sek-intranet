<?php

namespace App\Http\Controllers;

use App\Data\Contracts\UserRepository;
use App\Support\ApiTokens;
use App\Support\OpenApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seite «API»: Beschreibung (OpenAPI) mit Tester und Verwaltung der API-Tokens (Administration).
 */
class ApiDocsController extends Controller
{
    public function __construct(private readonly ApiTokens $tokens) {}

    public function show(UserRepository $users): Response
    {
        $names = $users->names();

        return Inertia::render('api-docs', [
            'spec' => OpenApi::spec(),
            'tokens' => array_map(fn (array $t): array => $t + ['owner_name' => $names[$t['owner_id'] ?? ''] ?? null], $this->tokens->all()),
            'people' => array_map(fn (array $p): array => ['id' => $p['id'], 'name' => $p['name'], 'role' => $p['role']], $users->active()),
        ]);
    }

    /**
     * Neues Token: wird genau einmal im Klartext zurückgegeben.
     */
    public function store(Request $request, UserRepository $users): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['in:'.implode(',', ApiTokens::ABILITIES)],
            'owner_id' => ['nullable', 'string'],
            'days' => ['nullable', 'integer', 'min:1', 'max:730'],
        ]);

        $owner = (string) ($data['owner_id'] ?? $request->user()?->getAuthIdentifier());
        $person = $users->find($owner);
        abort_if($person === null || ($person['active'] ?? false) !== true, 422, 'Die Person muss aktiv sein.');

        $created = $this->tokens->create(
            trim($data['name']),
            array_values(array_map('strval', $data['abilities'])),
            $owner,
            isset($data['days']) ? Carbon::now()->addDays((int) $data['days']) : null,
        );

        return response()->json($created + ['owner_name' => $person['name'] ?? null], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        abort_unless($this->tokens->delete($id), 404);

        return response()->json(['ok' => true]);
    }
}
