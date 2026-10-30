<?php

namespace App\Http\Controllers;

use App\Support\AppVersion;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seite «Versionen»: aktuelle Version, Build und Änderungen (CHANGELOG.md).
 */
class VersionController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('versions', [
            'info' => AppVersion::info(),
            'changelog' => AppVersion::changelog(),
        ]);
    }

    /**
     * Für die Aktualisierungsprüfung im Browser: Version, Build und Änderungen der aktuellen Version.
     */
    public function current(): JsonResponse
    {
        $info = AppVersion::info();
        $release = collect(AppVersion::changelog())->firstWhere('version', $info['version']);

        return response()->json($info + ['changes' => $release['sections'] ?? []])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
