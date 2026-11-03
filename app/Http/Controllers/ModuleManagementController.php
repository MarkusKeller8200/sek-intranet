<?php

namespace App\Http\Controllers;

use App\Modules\ModuleArchitecture;
use App\Modules\ModuleRegistry;
use App\Support\AuditLog;
use App\Support\Roles;
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
            $rights = [];
            foreach ($manifest->rights as $ability => $right) {
                $rights[$ability] = ['roles' => $this->modules->rightRoles($ability, $right['roles']), 'default_roles' => $right['roles']] + $right;
            }
            $items[] = ['rights' => $rights] + $manifest->summary() + [
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
            'roles' => Roles::all(),
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

    /** Welche Rollen ein Recht des Moduls haben (z. B. «Abläufe ansehen» je Rolle ein/aus). */
    public function rights(Request $request, string $key, string $ability): RedirectResponse
    {
        $data = $request->validate(['roles' => ['present', 'array'], 'roles.*' => ['string']]);
        $manifest = $this->modules->find($key);
        abort_if($manifest === null || ! isset($manifest->rights[$ability]), 404);
        $before = ['id' => $ability, 'roles' => $this->modules->rightRoles($ability, $manifest->rights[$ability]['roles'])];
        $user = $request->user()?->getAuthIdentifier();
        /** @var list<string> $roles */
        $roles = array_values(array_map('strval', (array) $data['roles']));
        $this->modules->setRightRoles($key, $ability, $roles, is_string($user) ? $user : null);
        $after = ['id' => $ability, 'roles' => $this->modules->rightRoles($ability, $manifest->rights[$ability]['roles'])];
        $this->audit->record(ModuleRegistry::RIGHTS_COLLECTION, $before, $after, 'Module', $request->user());
        $this->toast("Recht «{$manifest->rights[$ability]['label']}» gespeichert.");

        return back();
    }
}
