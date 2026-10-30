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

        $frame = $module['url'] !== null && $module['open'] !== 'tab' ? FrameCheck::check($module['url']) : null;

        return Inertia::render('modules/embed', [
            'module' => $module,
            'frame' => $frame,
        ]);
    }
}
