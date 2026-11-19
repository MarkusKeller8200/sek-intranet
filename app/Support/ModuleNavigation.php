<?php

namespace App\Support;

use App\Auth\IntranetUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Module aus config/intranet.php → modules: Navigation (Seitenleiste) und Platzhalter.
 *
 * @phpstan-type Module array{key: string, title: string, icon: string, href: string, planned: bool, external: string|null, open: string|null, width?: int|null, height?: int|null, collapse?: bool, children?: list<array<string, mixed>>}
 * @phpstan-type Embedded array{key: string, title: string, icon: string, group: string, color: string, description: string, collapse: bool, url: string|null, open: string, repo: string|null, stack: string|null, system: string|null, allowed: bool, width: int, height: int, split: int}
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
        $this->eduksx = null;

        $restricted = self::restrictedGroups($user);
        foreach ($this->config() as $groupKey => $group) {
            if (! $this->allowed($group, $user) || ($restricted !== null && ! in_array((string) $groupKey, $restricted, true))) {
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
                            'icon' => $this->icon($module, 'Folder'),
                            'href' => $children[0]['href'],
                            'planned' => false,
                            'external' => null,
                            'open' => null,
                            'children' => $children,
                        ];
                    }

                    continue;
                }

                // Hauptmenü mit collapse_sidebar (Fachanwendungen): Seitenmenü beim Öffnen einklappen
                $items[] = $this->item((string) $key, $module) + (($group['collapse_sidebar'] ?? false) === true ? ['collapse' => true] : []);
            }

            if ($items !== []) {
                $groups[] = [
                    'key' => (string) $groupKey,
                    'title' => (string) ($group['title'] ?? $groupKey),
                    'color' => (string) ($group['color'] ?? 'slate'),
                    'icon' => $this->icon($group, 'Folder'),
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    /**
     * Freigegebene Menügruppen einer eingeschränkten Rolle (z. B. Externe, #69); null = keine Einschränkung.
     *
     * @return list<string>|null
     */
    public static function restrictedGroups(?Authenticatable $user): ?array
    {
        $role = $user instanceof IntranetUser ? $user->role() : null;
        $groups = $role !== null ? (config('intranet.restricted_roles')[$role] ?? null) : null;

        return is_array($groups) ? array_values(array_map('strval', $groups)) : null;
    }

    /**
     * Pfade (ohne führenden Schrägstrich), die eine eingeschränkte Rolle öffnen darf: alle Menüpunkte
     * der freigegebenen Gruppen samt Unterseiten.
     *
     * @param  list<string>  $groups
     * @return list<string>
     */
    public function allowedPaths(array $groups): array
    {
        $paths = [];
        foreach ($this->flat() as $entry) {
            if (in_array($entry['groupKey'], $groups, true)) {
                $paths[] = ltrim($this->item($entry['key'], $entry['module'])['href'], '/');
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * @param  array<mixed>  $module
     * @return Module
     */
    private function item(string $key, array $module): array
    {
        $href = $module['href'] ?? null;
        $embed = $this->embed($module, $key);
        if ($embed !== null) {
            // Eingebunden: eigene Seite mit Rahmen, «tab» führt direkt auf die Adresse
            $href = '/einbindung/'.$key;
        }

        return [
            'key' => $key,
            'title' => (string) ($module['title'] ?? $key),
            'icon' => $this->icon($module, 'Circle'),
            'href' => is_string($href) ? $href : '/module/'.$key,
            'planned' => ! is_string($href),
            'external' => $embed['url'] ?? null,
            'open' => $embed['open'] ?? null,
        ] + ($embed !== null ? [
            // Neues Browser-Fenster: Grösse in % des sichtbaren Anzeigebereichs (#151)
            'width' => $embed['width'],
            'height' => $embed['height'],
        ] : []);
    }

    /**
     * Alle Module flach (Untermenüs aufgelöst), mit Gruppe und – falls vorhanden – Obermenü.
     *
     * @return list<array{key: string, module: array<mixed>, group: array<string, mixed>, parent: array<mixed>|null, groupKey: string}>
     */
    private function flat(): array
    {
        $all = [];
        foreach ($this->config() as $groupKey => $group) {
            foreach ((array) ($group['items'] ?? []) as $key => $module) {
                if (! is_array($module)) {
                    continue;
                }
                if (is_array($module['items'] ?? null)) {
                    foreach ($module['items'] as $childKey => $child) {
                        if (is_array($child)) {
                            $all[] = ['key' => (string) $childKey, 'module' => $child, 'group' => $group, 'parent' => $module, 'groupKey' => (string) $groupKey];
                        }
                    }

                    continue;
                }
                $all[] = ['key' => (string) $key, 'module' => $module, 'group' => $group, 'parent' => null, 'groupKey' => (string) $groupKey];
            }
        }

        return $all;
    }

    /**
     * Alle Einbindungen mit Vorgabe und Einstellung (für das Menü «Einstellungen»).
     *
     * @return list<array{key: string, title: string, icon: string, group: string, group_title: string, default_url: string|null, default_open: string, url: string|null, open: string, width: int, height: int, split: int, defaults: array{width: int, height: int, split: int}, custom: bool, allowed: bool, description: string}>
     */
    public function embedSettings(): array
    {
        $settings = app(ModuleSettings::class);
        $list = [];
        foreach ($this->flat() as ['key' => $key, 'module' => $module, 'group' => $group, 'groupKey' => $groupKey]) {
            $default = $this->embed($module);
            $effective = $this->embed($module, $key);
            if ($default === null || $effective === null) {
                continue;
            }
            $list[] = [
                'key' => $key,
                'title' => (string) ($module['title'] ?? $key),
                'icon' => $this->icon($module, 'Circle'),
                'group' => $groupKey,
                'group_title' => (string) ($group['title'] ?? $groupKey),
                'default_url' => $default['url'],
                'default_open' => $default['open'],
                'url' => $effective['url'],
                'open' => $effective['open'],
                'width' => $effective['width'],
                'height' => $effective['height'],
                'split' => $effective['split'],
                'defaults' => ['width' => $default['width'], 'height' => $default['height'], 'split' => $default['split']],
                'custom' => $settings->get($key) !== null,
                'allowed' => $effective['allowed'],
                'description' => (string) ($module['description'] ?? ''),
            ];
        }

        return $list;
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
                'icon' => $this->icon($module, 'Circle'),
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
            $embed = $this->embed($module, $key);
            if ($embed === null) {
                continue;
            }

            return [
                'key' => $key,
                'title' => (string) ($module['title'] ?? $key),
                'icon' => $this->icon($module, 'Circle'),
                'group' => (string) ($group['title'] ?? ''),
                'color' => (string) ($group['color'] ?? 'slate'),
                'description' => (string) ($module['description'] ?? ''),
                // Fachanwendungen: Seitenmenü beim Öffnen einklappen (mehr Platz für die Anwendung)
                'collapse' => ($parent['collapse_sidebar'] ?? $group['collapse_sidebar'] ?? false) === true,
            ] + $embed;
        }

        return null;
    }

    /**
     * Einbindung mit Vorgabe (config/.env) und – falls gespeichert – Einstellung aus dem Menü «Einstellungen».
     *
     * @param  array<mixed>  $module
     * @return array{url: string|null, open: string, repo: string|null, stack: string|null, system: string|null, allowed: bool, width: int, height: int, split: int}|null
     */
    private function embed(array $module, ?string $key = null): ?array
    {
        $embed = $module['embed'] ?? null;
        if (! is_array($embed)) {
            return null;
        }
        $setting = $key !== null ? app(ModuleSettings::class)->get($key) : null;
        $url = is_string($setting['url'] ?? null) ? $setting['url'] : ($embed['url'] ?? null);
        $open = is_string($setting['open'] ?? null) ? $setting['open'] : ($embed['open'] ?? 'both');

        return [
            // Nur https-Adressen (keine javascript:- oder data:-Ziele)
            'url' => is_string($url) && str_starts_with($url, 'https://') ? $url : null,
            'open' => in_array($open, ModuleSettings::OPEN_MODES, true) ? $open : 'both',
            'repo' => isset($embed['repo']) && is_string($embed['repo']) ? $embed['repo'] : null,
            'stack' => isset($embed['stack']) && is_string($embed['stack']) ? $embed['stack'] : null,
            // System der Datenherkunft (PersonLinks), z. B. escola: IDs aus Adressen der Anwendung zuordnen
            'system' => isset($embed['system']) && is_string($embed['system']) ? $embed['system'] : null,
            // Anbieter erlaubt das Einbetten nachweislich (z. B. Escola): keine automatische Prüfung nötig
            // gilt nur für die vorgegebene Adresse – eine andere Adresse wird wieder geprüft
            'allowed' => ($embed['allowed'] ?? false) === true && $url === ($embed['url'] ?? null),
        ] + self::layout($embed, $setting);
    }

    /**
     * Fenstergrösse und Trennlinie (#151): Einstellung, sonst Konfiguration, sonst Vorgabe.
     *
     * @param  array<mixed>  $embed
     * @param  array<string, mixed>|null  $setting
     * @return array{width: int, height: int, split: int}
     */
    private static function layout(array $embed, ?array $setting): array
    {
        $result = [];
        foreach (ModuleSettings::LAYOUT_RANGES as $field => [$min, $max]) {
            $value = $setting[$field] ?? $embed[$field] ?? ModuleSettings::LAYOUT[$field];
            $result[$field] = max($min, min($max, is_numeric($value) ? (int) $value : ModuleSettings::LAYOUT[$field]));
        }

        /** @var array{width: int, height: int, split: int} $result */
        return $result;
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

    /** Symbolsatz des Erscheinungsbilds (#220): EduKSX-Symbole oder Standard (Lucide). */
    private ?bool $eduksx = null;

    /**
     * Symbol eines Eintrags: mit Symbolsatz «EduKSX» das freigegebene EduKSX-Icon («icon_eduksx»), sonst «icon».
     *
     * @param  array<mixed>  $entry
     */
    private function icon(array $entry, string $fallback): string
    {
        $this->eduksx ??= app(Branding::class)->einstellung()['symbole'] === 'eduksx';
        if ($this->eduksx && is_string($entry['icon_eduksx'] ?? null) && $entry['icon_eduksx'] !== '') {
            return $entry['icon_eduksx'];
        }

        return is_string($entry['icon'] ?? null) ? $entry['icon'] : $fallback;
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
