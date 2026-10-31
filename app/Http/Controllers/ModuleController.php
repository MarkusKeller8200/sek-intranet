<?php

namespace App\Http\Controllers;

use App\Plugins\PluginManager;
use App\Support\ModuleNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platzhalter für geplante Module (config/intranet.php → modules, ohne «href»).
 */
class ModuleController extends Controller
{
    public function show(Request $request, string $key, ModuleNavigation $modules, PluginManager $plugins): Response|RedirectResponse
    {
        $module = $modules->planned($key, $request->user());
        if ($module === null) {
            // Inzwischen umgesetzt: alte Links (/module/<schlüssel>) auf die neue Seite umleiten
            $href = collect($modules->groups($request->user()))->flatMap(fn (array $g): array => $g['items'])
                ->flatMap(fn (array $i): array => [$i, ...($i['children'] ?? [])])
                ->firstWhere('key', $key)['href'] ?? null;
            abort_if(! is_string($href) || str_starts_with($href, '/module/'), 404);

            return redirect($href);
        }

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
