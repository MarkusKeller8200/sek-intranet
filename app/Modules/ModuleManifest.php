<?php

namespace App\Modules;

use App\Support\Release;
use InvalidArgumentException;

/**
 * Beschreibung eines Moduls aus modules/<Ordner>/module.json (docs/KONZEPT-MODULE.md).
 *
 * Ein Modul ist ein Fachthema im Intranet (z. B. Zeiterfassung) mit eigenen Rechten, eigenem Menü,
 * eigenen Seiten und eigener Datenhaltung. Alles, was der Core einhängt, steht im Manifest und wird hier geprüft.
 */
final class ModuleManifest
{
    /** Datenklassen nach IDG (Schutzbedarf der Daten des Moduls) */
    public const DATA_CLASSES = ['oeffentlich', 'intern', 'vertraulich', 'besonders'];

    /**
     * @param  array<string, array{label: string, roles: list<string>}>  $rights
     * @param  array{gruppe: string, eintraege: array<string, array<string, mixed>>}  $menu
     * @param  array<string, array<string, mixed>>  $collections
     * @param  array{sendet: list<string>, empfaengt: list<string>}  $events
     * @param  list<string>  $plugins
     */
    private function __construct(
        public readonly string $folder,
        public readonly string $directory,
        public readonly string $key,
        public readonly string $name,
        public readonly string $version,
        public readonly string $core,
        public readonly string $description,
        public readonly bool $enabledByDefault,
        public readonly string $dataClass,
        public readonly string $dbSchema,
        public readonly ?string $provider,
        public readonly array $rights,
        public readonly array $menu,
        public readonly array $collections,
        public readonly array $events,
        public readonly array $plugins,
    ) {}

    public static function fromFile(string $file): self
    {
        $data = json_decode((string) @file_get_contents($file), true);
        if (! is_array($data)) {
            throw new InvalidArgumentException(basename(dirname($file)).': module.json ist kein gültiges JSON.');
        }

        return self::fromArray($data, dirname($file));
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<string, mixed>|null  $coreAbilities  bestehende Rechte des Core (keine Überschneidung erlaubt)
     * @param  list<string>|null  $menuGroups  vorhandene Hauptmenüs
     */
    public static function fromArray(array $data, string $directory, ?array $coreAbilities = null, ?array $menuGroups = null, ?string $coreVersion = null): self
    {
        $folder = basename($directory);
        $errors = [];
        $fail = function (string $message) use (&$errors): void {
            $errors[] = $message;
        };

        $key = (string) ($data['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9-]{1,40}$/', $key) !== 1) {
            $fail('«key» fehlt oder ist ungültig (Kleinbuchstaben, Ziffern, Bindestrich).');
        }
        if (preg_match('/^[A-Z][A-Za-z0-9]{1,40}$/', $folder) !== 1) {
            $fail("Ordnername «{$folder}» muss mit einem Grossbuchstaben beginnen (z. B. Zeiterfassung).");
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $fail('«name» fehlt.');
        }
        $version = (string) ($data['version'] ?? '');
        if (! Release::isValid($version)) {
            $fail('«version» muss SemVer sein (z. B. 0.1.0).');
        }
        $core = (string) ($data['core'] ?? '');
        if (preg_match('/^>=\s*(\S+)$/', $core, $m) !== 1 || ! Release::isValid($m[1])) {
            $fail('«core» muss die Form «>=0.33.0» haben.');
        } elseif ($coreVersion !== null && Release::isValid($coreVersion) && Release::compare($coreVersion, $m[1]) < 0) {
            $fail("Benötigt Intranet {$core}, installiert ist {$coreVersion}.");
        }
        $dataClass = (string) ($data['datenklasse'] ?? '');
        if (! in_array($dataClass, self::DATA_CLASSES, true)) {
            $fail('«datenklasse» muss eine von '.implode(', ', self::DATA_CLASSES).' sein.');
        }
        $dbSchema = (string) ($data['db_schema'] ?? '');
        if (preg_match('/^[a-z][a-z0-9]{1,20}$/', $dbSchema) !== 1) {
            $fail('«db_schema» fehlt oder ist ungültig (z. B. «zeit»).');
        }
        $provider = isset($data['provider']) ? (string) $data['provider'] : null;
        if ($provider !== null && ! str_starts_with($provider, 'Modules\\'.$folder.'\\')) {
            $fail("«provider» muss im Namensraum Modules\\{$folder}\\ liegen.");
        }

        $rights = [];
        foreach ((array) ($data['rechte'] ?? []) as $ability => $right) {
            $ability = (string) $ability;
            if (preg_match('/^[a-z][a-z0-9-]{2,60}$/', $ability) !== 1) {
                $fail("Recht «{$ability}» ist ungültig.");

                continue;
            }
            if ($coreAbilities !== null && array_key_exists($ability, $coreAbilities)) {
                $fail("Recht «{$ability}» gibt es im Core schon – Module brauchen eigene Rechte.");
            }
            if (! is_array($right) || trim((string) ($right['label'] ?? '')) === '' || ! is_array($right['roles'] ?? null)) {
                $fail("Recht «{$ability}» braucht «label» und «roles».");

                continue;
            }
            $rights[$ability] = ['label' => (string) $right['label'], 'roles' => array_values(array_map('strval', $right['roles']))];
        }

        $menuData = is_array($data['menu'] ?? null) ? $data['menu'] : [];
        $group = (string) ($menuData['gruppe'] ?? '');
        if ($menuGroups !== null && $menuData !== [] && ! in_array($group, $menuGroups, true)) {
            $fail("Menü-Gruppe «{$group}» gibt es nicht (vorhanden: ".implode(', ', $menuGroups).').');
        }
        $entries = [];
        foreach ((array) ($menuData['eintraege'] ?? []) as $entryKey => $entry) {
            $entryKey = (string) $entryKey;
            if (! is_array($entry) || trim((string) ($entry['title'] ?? '')) === '') {
                $fail("Menüeintrag «{$entryKey}» braucht «title».");

                continue;
            }
            $href = (string) ($entry['href'] ?? '');
            if ($href !== '' && ! str_starts_with($href, '/'.$key)) {
                $fail("Menüeintrag «{$entryKey}»: «href» muss mit /{$key} beginnen.");
            }
            if (isset($entry['can']) && ! array_key_exists((string) $entry['can'], $rights) && ($coreAbilities === null || ! array_key_exists((string) $entry['can'], $coreAbilities))) {
                $fail("Menüeintrag «{$entryKey}»: Recht «{$entry['can']}» ist unbekannt.");
            }
            $entries[$key.'-'.$entryKey] = $entry;
        }

        $collections = [];
        foreach ((array) ($data['sammlungen'] ?? []) as $collection => $definition) {
            $collection = (string) $collection;
            if (preg_match('/^'.preg_quote($dbSchema, '/').'_[a-z0-9_]{1,40}$/', $collection) !== 1) {
                $fail("Sammlung «{$collection}» muss mit «{$dbSchema}_» beginnen.");

                continue;
            }
            if (! is_array($definition) || ! is_array($definition['fields'] ?? null)) {
                $fail("Sammlung «{$collection}» braucht «fields».");

                continue;
            }
            $collections[$collection] = $definition;
        }

        $events = ['sendet' => [], 'empfaengt' => []];
        foreach (['sendet', 'empfaengt'] as $direction) {
            foreach ((array) (($data['ereignisse'] ?? [])[$direction] ?? []) as $event) {
                if (preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_.]*$/', (string) $event) !== 1) {
                    $fail("Ereignis «{$event}» ist ungültig (z. B. zeit.eintrag_erfasst).");
                }
                $events[$direction][] = (string) $event;
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException($folder.': '.implode(' ', $errors));
        }

        return new self(
            $folder, $directory, $key, $name, $version, $core, trim((string) ($data['description'] ?? '')),
            ($data['aktiv'] ?? true) !== false, $dataClass, $dbSchema, $provider, $rights,
            ['gruppe' => $group, 'eintraege' => $entries], $collections, $events,
            array_values(array_map('strval', (array) ($data['plugins'] ?? []))),
        );
    }

    /** Ordner mit den Seiten des Moduls (Inertia-Name «Ordner::seite»). */
    public function page(string $name): string
    {
        return $this->folder.'::'.$name;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'key' => $this->key,
            'folder' => $this->folder,
            'name' => $this->name,
            'version' => $this->version,
            'core' => $this->core,
            'description' => $this->description,
            'data_class' => $this->dataClass,
            'db_schema' => $this->dbSchema,
            'rights' => $this->rights,
            'menu_group' => $this->menu['gruppe'],
            'menu' => array_values(array_map(fn (array $e): string => (string) $e['title'], $this->menu['eintraege'])),
            'collections' => array_keys($this->collections),
            'events' => $this->events,
            'plugins' => $this->plugins,
        ];
    }
}
