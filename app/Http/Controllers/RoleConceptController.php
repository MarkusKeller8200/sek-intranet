<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use App\Support\ModuleNavigation;
use App\Support\Roles;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prototyp → Rollenkonzept: Rollen, Rechte, sichtbare Menüs je Rolle und Anzahl Personen.
 */
class RoleConceptController extends Controller
{
    public function __invoke(ModuleNavigation $navigation, UserRepository $users): Response
    {
        $descriptions = (array) config('intranet.role_descriptions', []);
        $counts = array_count_values(array_map(fn (array $u): string => (string) ($u['role'] ?? ''), $users->active()));

        $roles = [];
        foreach (Roles::all() as $key => $label) {
            // Menü so, wie es eine Person mit dieser Rolle sieht
            $probe = new IntranetUser(['id' => 'probe', 'role' => $key, 'active' => true]);
            $roles[] = [
                'key' => $key,
                'label' => $label,
                'description' => (string) ($descriptions[$key] ?? ''),
                'people' => $counts[$key] ?? 0,
                'abilities' => array_keys(array_filter(Roles::abilities(), fn (array $a): bool => in_array($key, $a['roles'], true))),
                'menus' => array_map(fn (array $group): array => [
                    'key' => $group['key'],
                    'title' => $group['title'],
                    'items' => count($group['items']),
                ], $navigation->groups($probe)),
            ];
        }

        $abilities = [];
        foreach (Roles::abilities() as $key => $ability) {
            $abilities[] = ['key' => $key, 'label' => $ability['label']];
        }

        return Inertia::render('role-concept', [
            'roles' => $roles,
            'abilities' => $abilities,
            'allMenus' => array_map(fn (array $g): array => ['key' => $g['key'], 'title' => $g['title'], 'color' => $g['color']], $navigation->groups(new IntranetUser(['id' => 'probe', 'role' => 'admin', 'active' => true]))),
        ]);
    }
}
