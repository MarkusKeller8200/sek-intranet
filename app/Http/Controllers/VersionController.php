<?php

namespace App\Http\Controllers;

use App\Support\AppVersion;
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
}
