<?php

namespace App\Http\Controllers;

use App\Modules\ModuleArchitecture;
use App\Modules\ModuleRegistry;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Einstellungen → Module: vorhandene Module, Zustand ein/aus, Rechte, Sammlungen, Fehler im Manifest.
 */
class ModuleManagementController extends Controller
{
    public function __construct(private readonly ModuleRegistry $modules, private readonly AuditLog $audit) {}

    public function index(): Response
    {
        $items = [];
        foreach ($this->modules->all() as $manifest) {
            $items[] = $manifest->summary() + [
                'enabled' => $this->modules->enabled($manifest->key),
                'store' => $this->modules->storeType($manifest->key),
                'sql' => $this->modules->sql($manifest->key),
            ];
        }

        return Inertia::render('module-management', [
            'modules' => $items,
            'errors' => $this->modules->errors(),
            'violations' => array_merge(...array_map(fn (string $p): array => ModuleArchitecture::violations($p), $this->modules->paths())),
            'path' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $this->modules->path()),
        ]);
    }

    public function toggle(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $manifest = $this->modules->find($key);
        abort_if($manifest === null, 404);
        $before = ['id' => $key, 'enabled' => $this->modules->enabled($key)];
        $user = $request->user()?->getAuthIdentifier();
        $this->modules->setEnabled($key, (bool) $data['enabled'], is_string($user) ? $user : null);
        $this->audit->record(ModuleRegistry::STATE_COLLECTION, $before, ['id' => $key, 'enabled' => (bool) $data['enabled']], 'Module', $request->user());
        $this->toast($data['enabled'] ? "Modul {$manifest->name} eingeschaltet." : "Modul {$manifest->name} ausgeschaltet – Menü und Seiten sind ausgeblendet, die Daten bleiben erhalten.");

        return back();
    }
}
