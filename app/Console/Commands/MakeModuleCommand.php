<?php

namespace App\Console\Commands;

use App\Modules\ModuleGenerator;
use App\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Neues Modul als Gerüst anlegen (docs/KONZEPT-MODULE.md).
 */
class MakeModuleCommand extends Command
{
    protected $signature = 'intranet:make-module
        {name : Ordnername, z. B. Zeiterfassung}
        {--titel= : Anzeigename (Standard: Name)}
        {--gruppe= : Hauptmenü, in dem das Modul erscheint (Schlüssel aus config/intranet.php → modules)}
        {--key= : Schlüssel (Standard: aus dem Namen, z. B. zeiterfassung)}
        {--schema= : Datenbank-Schema/Präfix der Sammlungen (Standard: aus dem Schlüssel)}';

    protected $description = 'Neues Modul unter modules/<Name> anlegen (module.json, Provider, Repository, Seite, Test)';

    public function handle(ModuleRegistry $registry): int
    {
        $name = (string) $this->argument('name');
        $groups = array_map('strval', array_keys((array) config('intranet.modules', [])));
        $group = (string) ($this->option('gruppe') ?: '');
        if (! in_array($group, $groups, true)) {
            $this->error('Bitte --gruppe angeben: '.implode(', ', $groups));

            return self::FAILURE;
        }
        try {
            $files = ModuleGenerator::generate(
                $registry->path(), $name, (string) ($this->option('titel') ?: $name), $group,
                (string) config('intranet.version', '0.0.0'),
                $this->option('key') ? (string) $this->option('key') : null,
                $this->option('schema') ? (string) $this->option('schema') : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        foreach ($files as $file) {
            $this->line("  modules/{$name}/{$file}");
        }
        $this->info("Modul {$name} angelegt. Danach: composer dump-autoload, composer ci:check, Eintrag im CHANGELOG.");

        return self::SUCCESS;
    }
}
