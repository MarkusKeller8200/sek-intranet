<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Module aus config/intranet.php → modules: Navigation (Seitenleiste) und Platzhalter.
 *
 * @phpstan-type Module array{key: string, title: string, icon: string, href: string, planned: bool, external: string|null, open: string|null, collapse?: bool, children?: list<array<string, mixed>>}
 * @phpstan-type Embedded array{key: string, title: string, icon: string, group: string, color: string, description: string, collapse: bool, url: string|null, open: string, repo: string|null, stack: string|null, allowed: bool}
 * @phpstan-type Group array{key: string, title: string, color: string, icon: string, items: list<Module>}
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

                // Untermenü (z. B. Kommunikation → Fachanwendungen): Einträge unter «items»
                if (is_array($module['items'] ?? null)) {
                    $children = [];
                    foreach ($module['items'] as $childKey => $child) {
                        if (is_array($child) && $this->allowed($child, $user)) {
                            $children[] = $this->item((string) $childKey, $child) + ['collapse' => ($module['collapse_sidebar'] ?? false) === true];
                        }
                    }
                    if ($children !== []) {
                        $items[] = [
                            'key' => (string) $key,
                            'title' => (string) ($module['title'] ?? $key),
                            'icon' => (string) ($module['icon'] ?? 'Folder'),
                            'href' => $children[0]['href'],
                            'planned' => false,
                            'external' => null,
                            'open' => null,
                            'children' => $children,
                        ];
                    }

                    continue;
                }

                $items[] = $this->item((string) $key, $module);
            }

            if ($items !== []) {
                $groups[] = [
                    'key' => (string) $groupKey,
                    'title' => (string) ($group['title'] ?? $groupKey),
                    'color' => (string) ($group['color'] ?? 'slate'),
                    'icon' => (string) ($group['icon'] ?? 'Folder'),
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    /**
     * @param  array<mixed>  $module
     * @return Module
     */
    private function item(string $key, array $module): array
    {
        $href = $module['href'] ?? null;
        $embed = $this->embed($module);
        if ($embed !== null) {
            // Eingebunden: eigene Seite mit Rahmen, «tab» führt direkt auf die Adresse
            $href = '/einbindung/'.$key;
        }

        return [
            'key' => $key,
            'title' => (string) ($module['title'] ?? $key),
            'icon' => (string) ($module['icon'] ?? 'Circle'),
            'href' => is_string($href) ? $href : '/module/'.$key,
            'planned' => ! is_string($href),
            'external' => $embed['url'] ?? null,
            'open' => $embed['open'] ?? null,
        ];
    }

    /**
     * Alle Module flach (Untermenüs aufgelöst), mit Gruppe und – falls vorhanden – Obermenü.
     *
     * @return list<array{key: string, module: array<mixed>, group: array<string, mixed>, parent: array<mixed>|null}>
     */
    private function flat(): array
    {
        $all = [];
        foreach ($this->config() as $group) {
            foreach ((array) ($group['items'] ?? []) as $key => $module) {
                if (! is_array($module)) {
                    continue;
                }
                if (is_array($module['items'] ?? null)) {
                    foreach ($module['items'] as $childKey => $child) {
                        if (is_array($child)) {
                            $all[] = ['key' => (string) $childKey, 'module' => $child, 'group' => $group, 'parent' => $module];
                        }
                    }

                    continue;
                }
                $all[] = ['key' => (string) $key, 'module' => $module, 'group' => $group, 'parent' => null];
            }
        }

        return $all;
    }

    /**
     * Geplantes Modul für die Platzhalterseite, null wenn unbekannt, umgesetzt oder nicht erlaubt.
     *
     * @return array{key: string, title: string, icon: string, group: string, description: string, features: list<string>, plugin: string|null}|null
     */
    public function planned(string $key, ?Authenticatable $user): ?array
    {
        foreach ($this->flat() as ['key' => $k, 'module' => $module, 'group' => $group, 'parent' => $parent]) {
            if ($k !== $key || isset($module['href']) || isset($module['embed']) || ! $this->allowed($group, $user) || ($parent !== null && ! $this->allowed($parent, $user)) || ! $this->allowed($module, $user)) {
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
     * Eingebundene Anwendung oder Seite («embed»), null wenn unbekannt oder nicht erlaubt.
     *
     * @return Embedded|null
     */
    public function embedded(string $key, ?Authenticatable $user): ?array
    {
        foreach ($this->flat() as ['key' => $k, 'module' => $module, 'group' => $group, 'parent' => $parent]) {
            if ($k !== $key || ! $this->allowed($group, $user) || ($parent !== null && ! $this->allowed($parent, $user)) || ! $this->allowed($module, $user)) {
                continue;
            }
            $embed = $this->embed($module);
            if ($embed === null) {
                continue;
            }

            return [
                'key' => $key,
                'title' => (string) ($module['title'] ?? $key),
                'icon' => (string) ($module['icon'] ?? 'Circle'),
                'group' => (string) ($group['title'] ?? ''),
                'color' => (string) ($group['color'] ?? 'slate'),
                'description' => (string) ($module['description'] ?? ''),
                // Fachanwendungen: Seitenmenü beim Öffnen einklappen (mehr Platz für die Anwendung)
                'collapse' => ($parent['collapse_sidebar'] ?? false) === true,
            ] + $embed;
        }

        return null;
    }

    /**
     * @param  array<mixed>  $module
     * @return array{url: string|null, open: string, repo: string|null, stack: string|null, allowed: bool}|null
     */
    private function embed(array $module): ?array
    {
        $embed = $module['embed'] ?? null;
        if (! is_array($embed)) {
            return null;
        }
        $url = $embed['url'] ?? null;
        $open = $embed['open'] ?? 'both';

        return [
            // Nur https-Adressen (keine javascript:- oder data:-Ziele)
            'url' => is_string($url) && str_starts_with($url, 'https://') ? $url : null,
            'open' => in_array($open, ['embed', 'tab', 'both'], true) ? $open : 'both',
            'repo' => isset($embed['repo']) && is_string($embed['repo']) ? $embed['repo'] : null,
            'stack' => isset($embed['stack']) && is_string($embed['stack']) ? $embed['stack'] : null,
            // Anbieter erlaubt das Einbetten nachweislich (z. B. Escola): keine automatische Prüfung nötig
            'allowed' => ($embed['allowed'] ?? false) === true,
        ];
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
