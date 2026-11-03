<?php

namespace App\Http\Middleware;

use App\Support\RolePins;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optionale Zugangssperre für den Prototyp: gemeinsames Passwort (HTTP Basic Auth).
 *
 * Aktiv, sobald INTRANET_ACCESS_PASSWORD gesetzt ist. Benutzername beliebig
 * (bzw. INTRANET_ACCESS_USER, falls gesetzt). Ersetzt später durch OIDC.
 *
 * Entfällt, sobald die PIN-Anmeldung aktiv ist (PIN der Administration gesetzt, #69).
 */
class RequireSitePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = (string) config('intranet.access.password', '');

        if ($password === '' || $request->is('up') || app(RolePins::class)->active()) {
            return $next($request);
        }

        $expectedUser = (string) config('intranet.access.user', '');
        $userOk = $expectedUser === '' || hash_equals($expectedUser, (string) $request->getUser());
        $passwordOk = hash_equals($password, (string) $request->getPassword());

        if ($userOk && $passwordOk) {
            return $next($request);
        }

        return response('Zugang nur für Mitarbeitende der Sekundarschule Bülach.', 401, [
            'WWW-Authenticate' => 'Basic realm="SekBuelach Intranet", charset="UTF-8"',
        ]);
    }
}
