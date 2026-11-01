<?php

namespace App\Http\Controllers;

use App\Support\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
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
            // «Fehler melden» / «Idee vorschlagen» auf GitHub (vorausgefüllt), nur für die Administration
            'github' => Gate::allows('manage-users') ? self::githubRepo() : null,
        ]);
    }

    /** GitHub-Repository für vorausgefüllte Issues (INTRANET_GITHUB_REPO), null = ausgeblendet. */
    public static function githubRepo(): ?string
    {
        $repo = trim((string) config('intranet.github_repo'));

        return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo) === 1 ? $repo : null;
    }

    /**
     * Für die Aktualisierungsprüfung im Browser: Version, Build und Änderungen der aktuellen Version.
     */
    public function current(): JsonResponse
    {
        $info = AppVersion::info();
        // Developer Release: die noch unveröffentlichten Änderungen, sonst die der aktuellen Version
        $release = collect(AppVersion::changelog())->firstWhere('version', $info['dev'] !== null ? 'Unveröffentlicht' : $info['version']);

        return response()->json($info + ['changes' => $release['sections'] ?? []])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
