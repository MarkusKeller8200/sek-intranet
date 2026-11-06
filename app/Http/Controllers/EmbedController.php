<?php

namespace App\Http\Controllers;

use App\Support\FrameCheck;
use App\Support\ModuleNavigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Eingebundene Anwendungen und Seiten (config/intranet.php → modules, Schlüssel «embed»):
 * im Intranet-Rahmen anzeigen oder in neuem Tab öffnen.
 */
class EmbedController extends Controller
{
    public function show(Request $request, string $key, ModuleNavigation $modules): Response
    {
        $module = $modules->embedded($key, $request->user());
        abort_if($module === null, 404);

        $frame = match (true) {
            $module['url'] === null || in_array($module['open'], ['tab', 'window', 'extension', 'split'], true) => null,
            $module['allowed'] => ['embeddable' => true, 'reason' => 'Einbetten vom Anbieter erlaubt'],
            default => FrameCheck::check($module['url']),
        };

        return Inertia::render('modules/embed', [
            'module' => $module,
            'frame' => $frame,
            // Über die Browser-Erweiterung: lesende Abrufe der Plugins für diese Anwendung
            'endpoints' => in_array($module['open'], ['extension', 'split'], true) ? ConnectorController::endpointsFor($module['url'], $request->user()) : [],
        ]);
    }
}
