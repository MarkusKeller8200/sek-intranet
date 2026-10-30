<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Module aus config/intranet.php → modules: Navigation (Seitenleiste) und Platzhalter.
 *
 * @phpstan-type Module array{key: string, title: string, icon: string, href: string, planned: bool}
 * @phpstan-type Group array{key: string, title: string, color: string, items: list<Module>}
 */
class ModuleNavigation
{
    /**
     * Sichtbare Gruppen mit ihren Modulen für diese Person.
     *
     * @return list<Group>
     */
    public function groups(?Authenticatable $user): array
    {
        $groups = [];

        foreach ($this->config() as $groupKey => $group) {
            if (! $this->allowed($group, $user)) {
                continue;
            }

            $items = [];
            foreach ((array) ($group['items'] ?? []) as $key => $module) {
                if (! is_array($module) || ! $this->allowed($module, $user)) {
                    continue;
                }

                $href = $module['href'] ?? null;
                $items[] = [
                    'key' => (string) $key,
                    'title' => (string) ($module['title'] ?? $key),
                    'icon' => (string) ($module['icon'] ?? 'Circle'),
                    'href' => is_string($href) ? $href : '/module/'.$key,
                    'planned' => ! is_string($href),
                ];
            }

            if ($items !== []) {
                $groups[] = [
                    'key' => (string) $groupKey,
                    'title' => (string) ($group['title'] ?? $groupKey),
                    'color' => (string) ($group['color'] ?? 'slate'),
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    /**
     * Geplantes Modul für die Platzhalterseite, null wenn unbekannt, umgesetzt oder nicht erlaubt.
     *
     * @return array{key: string, title: string, icon: string, group: string, description: string, features: list<string>, plugin: string|null}|null
     */
    public function planned(string $key, ?Authenticatable $user): ?array
    {
        foreach ($this->config() as $group) {
            $items = (array) ($group['items'] ?? []);
            $module = $items[$key] ?? null;

            if (! is_array($module) || isset($module['href']) || ! $this->allowed($group, $user) || ! $this->allowed($module, $user)) {
                continue;
            }

            return [
                'key' => $key,
                'title' => (string) ($module['title'] ?? $key),
                'icon' => (string) ($module['icon'] ?? 'Circle'),
                'group' => (string) ($group['title'] ?? ''),
                'description' => (string) ($module['description'] ?? ''),
                'features' => array_values(array_map('strval', (array) ($module['features'] ?? []))),
                'plugin' => isset($module['plugin']) ? (string) $module['plugin'] : null,
            ];
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function config(): array
    {
        /** @var array<string, array<string, mixed>> $modules */
        $modules = (array) config('intranet.modules', []);

        return $modules;
    }

    /**
     * @param  array<mixed>  $entry
     */
    private function allowed(array $entry, ?Authenticatable $user): bool
    {
        $ability = $entry['can'] ?? null;

        if (! is_string($ability)) {
            return true;
        }

        return $user !== null && Gate::forUser($user)->allows($ability);
    }
}
