<?php

namespace App\Modules;

use App\Data\Store\JsonStore;
use InvalidArgumentException;

/**
 * Findet die Module unter modules/<Ordner>/module.json, prüft sie und hält fest, welche eingeschaltet sind
 * (Sammlung «module_states», Vorgabe aus «aktiv» im Manifest). Jedes Modul hat einen eigenen Datenordner
 * (<data_path>/modules/<key>) – Core-Daten und Daten anderer Module sind so getrennt.
 */
final class ModuleRegistry
{
    public const STATE_COLLECTION = 'module_states';

    /** @var array<string, ModuleManifest>|null */
    private ?array $manifests = null;

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, JsonStore> */
    private array $stores = [];

    private bool $autoloader = false;

    /**
     * @param  list<string>  $paths  Modulordner (erster = modules/, in Tests zusätzlich tests/Fixtures/modules)
     */
    public function __construct(
        private readonly JsonStore $core,
        private readonly array $paths,
        private readonly string $dataPath,
    ) {}

    public function path(): string
    {
        return $this->paths[0] ?? '';
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Alle gültigen Module (Schlüssel → Manifest), sortiert nach Name.
     *
     * @return array<string, ModuleManifest>
     */
    public function all(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }
        $this->manifests = [];
        $this->errors = [];
        // Nur Rechte des Core: eine zwischengespeicherte Konfiguration (php artisan optimize) enthält die
        // Modulrechte schon – sonst würde das Modul als «Recht gibt es schon» abgelehnt.
        $coreAbilities = self::withoutModuleEntries((array) config('intranet.abilities', []));
        $groups = array_map('strval', array_keys((array) config('intranet.modules', [])));
        $version = (string) config('intranet.version', '0.0.0');

        $files = [];
        foreach ($this->paths as $path) {
            $files = [...$files, ...(glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'module.json') ?: [])];
        }
        foreach ($files as $file) {
            try {
                $data = json_decode((string) file_get_contents($file), true);
                if (! is_array($data)) {
                    throw new InvalidArgumentException(basename(dirname($file)).': module.json ist kein gültiges JSON.');
                }
                $manifest = ModuleManifest::fromArray($data, dirname($file), $coreAbilities, $groups, $version);
                if (isset($this->manifests[$manifest->key])) {
                    throw new InvalidArgumentException("{$manifest->folder}: Schlüssel «{$manifest->key}» ist doppelt.");
                }
                foreach ($manifest->rights as $ability => $_) {
                    foreach ($this->manifests as $other) {
                        if (isset($other->rights[$ability])) {
                            throw new InvalidArgumentException("{$manifest->folder}: Recht «{$ability}» gibt es schon im Modul {$other->name}.");
                        }
                    }
                }
                $this->manifests[$manifest->key] = $manifest;
            } catch (InvalidArgumentException $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        uasort($this->manifests, fn (ModuleManifest $a, ModuleManifest $b): int => strcmp($a->name, $b->name));

        return $this->manifests;
    }

    /**
     * Fehlerhafte Module (werden nicht geladen).
     *
     * @return list<string>
     */
    public function errors(): array
    {
        $this->all();

        return $this->errors;
    }

    public function find(string $key): ?ModuleManifest
    {
        return $this->all()[$key] ?? null;
    }

    public function enabled(string $key): bool
    {
        $manifest = $this->find($key);
        if ($manifest === null) {
            return false;
        }
        $state = $this->core->find(self::STATE_COLLECTION, $key);

        return $state !== null ? ($state['enabled'] ?? false) === true : $manifest->enabledByDefault;
    }

    /**
     * @return array<string, ModuleManifest>
     */
    public function active(): array
    {
        return array_filter($this->all(), fn (ModuleManifest $m): bool => $this->enabled($m->key));
    }

    public function setEnabled(string $key, bool $enabled, ?string $by): void
    {
        if ($this->find($key) === null) {
            throw new InvalidArgumentException("Modul «{$key}» gibt es nicht.");
        }
        $attributes = ['enabled' => $enabled, 'changed_by' => $by];
        if ($this->core->find(self::STATE_COLLECTION, $key) !== null) {
            $this->core->update(self::STATE_COLLECTION, $key, $attributes);
        } else {
            $this->core->insert(self::STATE_COLLECTION, ['id' => $key] + $attributes);
        }
    }

    /** Eigener Datenspeicher des Moduls (<data_path>/modules/<key>). */
    public function store(string $key): JsonStore
    {
        if (preg_match('/^[a-z][a-z0-9-]{1,40}$/', $key) !== 1) {
            throw new InvalidArgumentException("Ungültiger Modulschlüssel: {$key}");
        }
        if (! isset($this->stores[$key])) {
            $dir = $this->dataPath.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$key;
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $this->stores[$key] = new JsonStore($dir, (string) config('app.timezone', 'Europe/Zurich'));
        }

        return $this->stores[$key];
    }

    /** Speicherart des Moduls: <KEY>_STORE (z. B. ZEITERFASSUNG_STORE), sonst wie der Core. */
    public function storeType(string $key): string
    {
        $env = getenv(strtoupper(str_replace('-', '_', $key)).'_STORE');

        return is_string($env) && $env !== '' ? $env : (string) config('intranet.store', 'json');
    }

    /** Klassen Modules\<Ordner>\… aus dem Modulordner laden (auch für Module ausserhalb von modules/, z. B. Tests). */
    public function registerAutoloader(): void
    {
        if ($this->autoloader) {
            return;
        }
        $this->autoloader = true;
        $paths = $this->paths;
        spl_autoload_register(function (string $class) use ($paths): void {
            if (! str_starts_with($class, 'Modules\\')) {
                return;
            }
            foreach ($paths as $path) {
                $file = $path.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 8)).'.php';
                if (is_file($file)) {
                    require_once $file;

                    return;
                }
            }
        });
    }

    /**
     * Rechte und Menüeinträge der eingeschalteten Module in die Konfiguration übernehmen.
     */
    public function integrateConfig(): void
    {
        // Zuerst alle Einträge von Modulen entfernen (z. B. aus der zwischengespeicherten Konfiguration),
        // dann nur die der eingeschalteten Module wieder einfügen – so verschwinden ausgeschaltete Module.
        $abilities = self::withoutModuleEntries((array) config('intranet.abilities', []));
        $modules = (array) config('intranet.modules', []);
        foreach ($modules as $groupKey => $group) {
            if (is_array($group) && is_array($group['items'] ?? null)) {
                $modules[$groupKey]['items'] = self::withoutModuleEntries($group['items']);
            }
        }
        foreach ($this->active() as $manifest) {
            foreach ($manifest->rights as $ability => $right) {
                $abilities[$ability] = $right + ['module' => $manifest->key];
            }
            $group = $manifest->menu['gruppe'];
            if ($group !== '' && isset($modules[$group]) && is_array($modules[$group])) {
                $items = (array) ($modules[$group]['items'] ?? []);
                foreach ($manifest->menu['eintraege'] as $entryKey => $entry) {
                    $items[$entryKey] = $entry + ['module' => $manifest->key];
                }
                $modules[$group]['items'] = $items;
            }
        }
        config(['intranet.abilities' => $abilities, 'intranet.modules' => $modules]);
    }

    /**
     * @param  array<mixed>  $entries
     * @return array<mixed>
     */
    private static function withoutModuleEntries(array $entries): array
    {
        return array_filter($entries, fn ($e): bool => ! (is_array($e) && isset($e['module'])));
    }

    /**
     * SQL-Vorschlag für den späteren Betrieb mit PostgreSQL: eigenes Schema und eigener DB-Benutzer, der nur
     * im Schema des Moduls schreiben darf. Passwort nie hier – wird beim Einrichten gesetzt.
     */
    public function sql(string $key): string
    {
        $manifest = $this->find($key) ?? throw new InvalidArgumentException("Modul «{$key}» gibt es nicht.");
        $schema = $manifest->dbSchema;
        $role = 'intranet_'.$schema;

        return implode("\n", [
            "-- Modul {$manifest->name} ({$manifest->key}) – eigenes Schema und eigener DB-Benutzer",
            "CREATE SCHEMA IF NOT EXISTS {$schema};",
            "CREATE ROLE {$role} LOGIN; -- Passwort danach setzen: ALTER ROLE {$role} PASSWORD '…';",
            "REVOKE ALL ON SCHEMA {$schema} FROM PUBLIC;",
            "GRANT USAGE, CREATE ON SCHEMA {$schema} TO {$role};",
            "ALTER DEFAULT PRIVILEGES IN SCHEMA {$schema} GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$role};",
            '-- Lesen von Core-Stammdaten nur über Views/Schnittstellen, z. B.:',
            "-- GRANT USAGE ON SCHEMA core TO {$role}; GRANT SELECT ON core.people_directory TO {$role};",
            "ALTER ROLE {$role} SET search_path = {$schema};",
        ])."\n";
    }
}
