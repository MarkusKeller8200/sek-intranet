<?php

namespace App\Http\Controllers;

use App\Plugins\PluginManager;
use App\Support\ModuleNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platzhalter für geplante Module (config/intranet.php → modules, ohne «href»).
 */
class ModuleController extends Controller
{
    public function show(Request $request, string $key, ModuleNavigation $modules, PluginManager $plugins): Response
    {
        $module = $modules->planned($key, $request->user());
        abort_if($module === null, 404);

        $plugin = null;
        $id = $module['plugin'];
        if ($id !== null && isset($plugins->all()[$id])) {
            $plugin = [
                'id' => $id,
                'name' => $plugins->manifest($id)->name(),
                'enabled' => $plugins->state($id)['enabled'],
                // Einstellungen nur für die Administration verlinken
                'canManage' => $request->user() !== null && Gate::forUser($request->user())->allows('manage-users'),
            ];
        }

        return Inertia::render('modules/show', [
            'module' => $module,
            'plugin' => $plugin,
        ]);
    }
}
