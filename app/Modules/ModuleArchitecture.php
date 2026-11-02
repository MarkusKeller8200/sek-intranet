<?php

namespace App\Modules;

/**
 * Architekturprüfung der Module (läuft als Test in composer ci:check):
 *
 * - kein Modul verwendet Klassen oder Dateien eines anderen Moduls (PHP «Modules\Anderes\…»,
 *   TypeScript-Importe aus «modules/Anderes/…»),
 * - kein Modul greift am Repository vorbei auf Core-Daten zu (JsonStore des Core, JSON-Umsetzungen des Core,
 *   Store::facade, config('intranet.schema')), erlaubt sind Core-Schnittstellen (App\Data\Contracts …)
 *   und die Grundklasse App\Data\Json\JsonRepository.
 */
final class ModuleArchitecture
{
    private const FORBIDDEN_PHP = [
        '/App\\\\Data\\\\Json\\\\(?!JsonRepository\b)\w+/' => 'JSON-Umsetzung des Core (nur Schnittstellen aus App\Data\Contracts verwenden)',
        '/->make\(\s*JsonStore::class\s*\)|app\(\s*JsonStore::class\s*\)/' => 'Datenspeicher des Core (eigenen Speicher über ModuleProvider::store() verwenden)',
        "/config\\(\\s*'intranet\\.schema/" => 'Schema des Core (eigene Sammlungen in module.json)',
        '/storage_path\(\s*[\'"]app\/data/' => 'Datenordner des Core',
    ];

    /**
     * @return list<string> Verstösse «Modul/Datei:Zeile – Grund»
     */
    public static function violations(string $modulesPath): array
    {
        $violations = [];
        foreach (glob($modulesPath.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $dir) {
            $own = basename($dir);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $ext = $file->getExtension();
                if (! in_array($ext, ['php', 'ts', 'tsx'], true)) {
                    continue;
                }
                $relative = $own.substr($file->getPathname(), strlen($dir));
                foreach (preg_split('/\R/', (string) file_get_contents($file->getPathname())) ?: [] as $n => $line) {
                    $where = $relative.':'.($n + 1);
                    if ($ext === 'php') {
                        if (preg_match_all('/Modules\\\\+(\w+)\\\\/', $line, $m) > 0) {
                            foreach ($m[1] as $other) {
                                if ($other !== $own) {
                                    $violations[] = "{$where} – verwendet Modul «{$other}»";
                                }
                            }
                        }
                        foreach (self::FORBIDDEN_PHP as $pattern => $reason) {
                            if (preg_match($pattern, $line) === 1) {
                                $violations[] = "{$where} – {$reason}";
                            }
                        }
                    } elseif (preg_match_all('/(?:from|import)\s*\(?\s*[\'"]([^\'"]+)[\'"]/', $line, $m) > 0) {
                        foreach ($m[1] as $import) {
                            if (preg_match('#modules/(\w+)/#', $import, $mm) === 1 && $mm[1] !== $own) {
                                $violations[] = "{$where} – importiert aus Modul «{$mm[1]}»";
                            } elseif (str_starts_with($import, '../') && substr_count($import, '../') > self::depth($relative)) {
                                $violations[] = "{$where} – relativer Import ausserhalb des Moduls ({$import})";
                            }
                        }
                    }
                }
            }
        }

        return $violations;
    }

    /** Wie tief liegt die Datei im Modulordner (für relative Importe)? */
    private static function depth(string $relative): int
    {
        return substr_count($relative, '/') - 1;
    }
}
