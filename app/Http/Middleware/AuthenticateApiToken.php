<?php

namespace App\Http\Middleware;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use App\Support\ApiTokens;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * REST-API: Anmeldung mit «Authorization: Bearer sbi_…». Das Token handelt im Namen
 * seiner Besitzerin/seines Besitzers (gleiche Rechte wie im Intranet), eingeschränkt
 * auf «read» (GET) und «write» (POST, PATCH, DELETE).
 */
class AuthenticateApiToken
{
    public function __construct(
        private readonly ApiTokens $tokens,
        private readonly UserRepository $users,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $record = $token !== '' ? $this->tokens->resolve($token) : null;
        if ($record === null) {
            return response()->json(['message' => 'Kein oder ungültiges API-Token (Authorization: Bearer sbi_…).'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        $owner = $this->users->find((string) ($record['owner_id'] ?? ''));
        if ($owner === null || ($owner['active'] ?? false) !== true) {
            return response()->json(['message' => 'Die Person hinter diesem Token ist nicht (mehr) aktiv.'], 401);
        }

        $needed = $request->isMethodSafe() ? 'read' : 'write';
        if (! in_array($needed, (array) ($record['abilities'] ?? []), true)) {
            return response()->json(['message' => "Dieses Token hat kein Recht «{$needed}»."], 403);
        }

        Auth::setUser(new IntranetUser($owner));
        $request->attributes->set('api_token', $record);

        return $next($request);
    }
}
