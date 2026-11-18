<?php

namespace App\Support;

use App\Data\Store\JsonStore;
use InvalidArgumentException;

/**
 * Themes (Farbkonzepte): gespeicherte Farbsätze für hell und dunkel, die die Farbwerte aus
 * resources/css/app.css übersteuern. Das Theme «SekBülach (Standard)» ist app.css selbst und kann
 * nicht geändert werden. Ein Theme ist für alle aktiv (Administration wählt es im Farbkonzept).
 * Eingebaute Farbkonzepte (EduKSX, MarKusSXCH; #216) stehen in BuiltinThemes und sind ebenfalls nicht änderbar;
 * ist eines aktiv, steht das im Datensatz «_eingebaut».
 *
 * Gespeichert werden nur abweichende Farben (#rrggbb); fehlende Farben kommen aus der Vorgabe.
 */
final class Themes
{
    public const COLLECTION = 'themes';

    public const STANDARD = 'standard';

    /** Datensatz, der das aktive eingebaute Farbkonzept festhält (#216). */
    private const EINGEBAUT = '_eingebaut';

    /** Bearbeitbare Farbwerte nach Gruppe (Name in app.css ohne «--»). */
    public const GROUPS = [
        'Marke & Hauptfarbe' => ['brand', 'primary', 'primary-foreground', 'ring'],
        'Flächen & Text' => ['background', 'foreground', 'card', 'card-foreground', 'popover', 'popover-foreground', 'muted', 'muted-foreground', 'border', 'input'],
        'Akzent & Neben' => ['accent', 'accent-foreground', 'secondary', 'secondary-foreground'],
        'Status' => ['destructive', 'destructive-foreground', 'success', 'success-soft', 'warning', 'warning-soft', 'info', 'info-soft'],
        'Seitenleiste' => ['sidebar', 'sidebar-foreground', 'sidebar-primary', 'sidebar-primary-foreground', 'sidebar-accent', 'sidebar-accent-foreground', 'sidebar-border', 'sidebar-ring'],
        'Diagramme' => ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5'],
        'Glossar-Begriffe' => ['term-underline', 'term-underline-hover'],
        'Zeiterfassung' => ['vacation-soft', 'compensation-soft', 'sick-soft'],
    ];

    /** @var array{light: array<string, string>, dark: array<string, string>}|null */
    private static ?array $defaults = null;

    public function __construct(private readonly JsonStore $store) {}

    /**
     * @return list<string>
     */
    public static function tokens(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /**
     * Vorgabe aus resources/css/app.css (:root und .dark).
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function defaults(): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }
        $css = (string) @file_get_contents(resource_path('css/app.css'));
        $read = function (string $selector) use ($css): array {
            $values = [];
            if (preg_match('/(?:^|\n)'.preg_quote($selector, '/').'\s*\{([^}]*)\}/', $css, $m) === 1) {
                preg_match_all('/--([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})\s*;/', $m[1], $all, PREG_SET_ORDER);
                foreach ($all as [, $name, $value]) {
                    if (in_array($name, self::tokens(), true)) {
                        $values[$name] = strtolower($value);
                    }
                }
            }

            return $values;
        };

        return self::$defaults = ['light' => $read(':root'), 'dark' => $read('.dark')];
    }

    /**
     * Alle Themes: Standard zuerst, dann gespeicherte (nach Name).
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $active = $this->activeId();
        $stored = array_map(fn (array $t): array => [
            'id' => (string) $t['id'],
            'name' => (string) $t['name'],
            'description' => (string) ($t['description'] ?? ''),
            'light' => (array) ($t['light'] ?? []),
            'dark' => (array) ($t['dark'] ?? []),
            'builtin' => false,
            'active' => $t['id'] === $active,
        ], $this->stored());
        usort($stored, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        $builtin = [];
        foreach (BuiltinThemes::all() as $id => $t) {
            $builtin[] = ['id' => $id, 'name' => $t['name'], 'description' => $t['description'], 'light' => $t['light'], 'dark' => $t['dark'],
                'builtin' => true, 'active' => $id === $active];
        }

        return [[
            'id' => self::STANDARD,
            'name' => 'SekBülach (Standard)',
            'description' => 'Farben aus resources/css/app.css, abgeleitet vom Auftritt sekbuelach.ch.',
            'light' => [],
            'dark' => [],
            'builtin' => true,
            'active' => $active === self::STANDARD,
        ], ...$builtin, ...$stored];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $theme) {
            if ($theme['id'] === $id) {
                return $theme;
            }
        }

        return null;
    }

    public function activeId(): string
    {
        foreach ($this->stored() as $t) {
            if (($t['active'] ?? false) === true) {
                return (string) $t['id'];
            }
        }
        $eingebaut = (string) ($this->store->find(self::COLLECTION, self::EINGEBAUT)['theme'] ?? '');

        return isset(BuiltinThemes::all()[$eingebaut]) ? $eingebaut : self::STANDARD;
    }

    public static function isBuiltin(string $id): bool
    {
        return $id === self::STANDARD || isset(BuiltinThemes::all()[$id]);
    }

    /**
     * Theme speichern (neu oder ändern). Nur bekannte Farbwerte als #rrggbb; gleiche Werte wie die
     * Vorgabe werden nicht gespeichert.
     *
     * @param  array<string, mixed>  $light
     * @param  array<string, mixed>  $dark
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    public function save(?string $id, string $name, string $description, array $light, array $dark, ?string $userId): array
    {
        if ($id !== null && self::isBuiltin($id)) {
            throw new InvalidArgumentException('Eingebaute Themes lassen sich nicht ändern – als neues Theme speichern.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Bitte einen Namen angeben.');
        }
        $data = [
            'name' => $name,
            'description' => trim($description),
            'light' => self::clean($light, self::defaults()['light']),
            'dark' => self::clean($dark, self::defaults()['dark']),
            'updated_by' => $userId,
        ];

        $before = $id !== null ? $this->store->find(self::COLLECTION, $id) : null;
        if ($id !== null && $before === null) {
            throw new InvalidArgumentException('Theme nicht gefunden.');
        }
        $after = $before !== null
            ? $this->store->update(self::COLLECTION, $id, $data)
            : $this->store->insert(self::COLLECTION, $data + ['active' => false]);

        return ['before' => $before, 'after' => (array) $after];
    }

    /**
     * Theme für alle aktivieren (Standard = keine Übersteuerung).
     *
     * @return list<array{before: array<string, mixed>, after: array<string, mixed>}>
     */
    public function activate(string $id): array
    {
        if (! self::isBuiltin($id) && ($id === self::EINGEBAUT || $this->store->find(self::COLLECTION, $id) === null)) {
            throw new InvalidArgumentException('Theme nicht gefunden.');
        }
        $changes = [];
        // eingebautes Farbkonzept merken (Standard bzw. gespeichertes Theme: keines)
        $alt = $this->store->find(self::COLLECTION, self::EINGEBAUT);
        $neu = isset(BuiltinThemes::all()[$id]) ? $id : null;
        if (($alt['theme'] ?? null) !== $neu) {
            $after = $alt !== null
                ? $this->store->update(self::COLLECTION, self::EINGEBAUT, ['theme' => $neu])
                : $this->store->insert(self::COLLECTION, ['id' => self::EINGEBAUT, 'name' => 'Eingebautes Farbkonzept', 'light' => [], 'dark' => [], 'active' => false, 'theme' => $neu]);
            $changes[] = ['before' => $alt ?? [], 'after' => (array) $after];
        }
        foreach ($this->stored() as $t) {
            $should = $t['id'] === $id;
            if ((($t['active'] ?? false) === true) !== $should) {
                $changes[] = ['before' => $t, 'after' => (array) $this->store->update(self::COLLECTION, (string) $t['id'], ['active' => $should])];
            }
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>|null der entfernte Eintrag
     */
    public function delete(string $id): ?array
    {
        if (self::isBuiltin($id) || $id === self::EINGEBAUT) {
            throw new InvalidArgumentException('Eingebaute Themes lassen sich nicht löschen.');
        }
        $before = $this->store->find(self::COLLECTION, $id);
        if ($before !== null) {
            $this->store->delete(self::COLLECTION, $id);
        }

        return $before;
    }

    /**
     * CSS für das aktive Theme (leer beim Standard). Nur geprüfte Namen und #rrggbb-Werte.
     */
    public function activeCss(): string
    {
        $theme = $this->find($this->activeId());
        if ($theme === null || $theme['id'] === self::STANDARD) {
            return '';
        }

        return self::css(':root', (array) $theme['light']).self::css('.dark', (array) $theme['dark'])
            .(isset($theme['light']['background']) ? 'html{background-color:'.$theme['light']['background'].'}' : '')
            .(isset($theme['dark']['background']) ? 'html.dark{background-color:'.$theme['dark']['background'].'}' : '');
    }

    /**
     * Vollständige Farben je Modus (Vorgabe + Theme), z. B. für die Vorschau.
     *
     * @param  array<string, mixed>  $theme
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function resolved(array $theme): array
    {
        $defaults = self::defaults();

        return [
            'light' => array_merge($defaults['light'], self::clean((array) ($theme['light'] ?? []), [])),
            'dark' => array_merge($defaults['dark'], self::clean((array) ($theme['dark'] ?? []), [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    private static function clean(array $values, array $defaults): array
    {
        $clean = [];
        foreach ($values as $name => $value) {
            if (! in_array($name, self::tokens(), true) || ! is_string($value)) {
                continue;
            }
            $value = strtolower(trim($value));
            if (preg_match('/^#[0-9a-f]{6}$/', $value) !== 1) {
                throw new InvalidArgumentException("Farbe «{$name}» ist kein gültiger Wert (#rrggbb).");
            }
            if (($defaults[$name] ?? null) !== $value) {
                $clean[$name] = $value;
            }
        }
        ksort($clean);

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function css(string $selector, array $values): string
    {
        $rules = [];
        foreach ($values as $name => $value) {
            if (in_array($name, self::tokens(), true) && is_string($value) && preg_match('/^#[0-9a-f]{6}$/', $value) === 1) {
                $rules[] = "--{$name}:{$value}";
            }
        }

        return $rules === [] ? '' : $selector.'{'.implode(';', $rules).'}';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stored(): array
    {
        return array_values(array_filter($this->store->all(self::COLLECTION), fn (array $t): bool => ($t['id'] ?? null) !== self::EINGEBAUT));
    }
}
