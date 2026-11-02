<?php

namespace App\Console\Commands;

use App\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * SQL-Vorschlag für eigenes Schema und eigenen DB-Benutzer eines Moduls (später PostgreSQL).
 */
class ModuleSqlCommand extends Command
{
    protected $signature = 'intranet:module-sql {key : Schlüssel des Moduls}';

    protected $description = 'SQL für Schema und DB-Benutzer eines Moduls ausgeben (nur anzeigen, nichts ausführen)';

    public function handle(ModuleRegistry $registry): int
    {
        try {
            $this->line($registry->sql((string) $this->argument('key')));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
