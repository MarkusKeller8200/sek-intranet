<?php

namespace App\Http\Middleware;

use App\Support\ModuleNavigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eingeschränkte Rollen (config intranet.restricted_roles, z. B. Externe) öffnen nur die
 * Menüpunkte ihrer freigegebenen Gruppen (#69). Menü und Seiten gleich behandelt.
 */
class RestrictRoleAreas
{
    /** Immer erlaubt: Abmelden, eigene Darstellung, Sprache. */
    private const ALWAYS = ['abmelden', 'anmelden', 'einstellungen/darstellung', 'sprache', 'up'];

    public function __construct(private readonly ModuleNavigation $navigation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $groups = ModuleNavigation::restrictedGroups($request->user());
        if ($groups === null) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        foreach ([...self::ALWAYS, ...$this->navigation->allowedPaths($groups)] as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed.'/')) {
                return $next($request);
            }
        }
        if ($path === '') {
            return redirect('/start');
        }

        abort(403, 'Dieser Bereich ist für deine Rolle nicht freigegeben.');
    }
}
