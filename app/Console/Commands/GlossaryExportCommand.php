<?php

namespace App\Console\Commands;

use App\Support\GlossaryTransfer;
use Illuminate\Console\Command;

/**
 * Rohdaten des Glossars als JSON (#157): alle Einträge mit allen gespeicherten Feldern (auch inaktive, eigene Formen,
 * «Intern genutzt in», Doppelte, Zeitstempel) und die Listen Kategorien und Herkunft – für die Übernahme ins
 * EduKSX-Glossary. Ohne --datei auf die Standardausgabe.
 */
class GlossaryExportCommand extends Command
{
    protected $signature = 'intranet:glossar-export {--datei= : Pfad der JSON-Datei}';

    protected $description = 'Glossar vollständig als JSON exportieren (Einträge und Listen)';

    public function handle(GlossaryTransfer $transfer): int
    {
        $json = json_encode($transfer->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = $this->option('datei');
        if (is_string($path) && $path !== '') {
            if (file_put_contents($path, $json."\n") === false) {
                $this->error("Datei {$path} konnte nicht geschrieben werden.");

                return self::FAILURE;
            }
            $this->info("Glossar exportiert: {$path}");

            return self::SUCCESS;
        }
        $this->line($json);

        return self::SUCCESS;
    }
}
