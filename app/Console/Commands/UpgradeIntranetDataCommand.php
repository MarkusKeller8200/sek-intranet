<?php

namespace App\Console\Commands;

use App\Data\Store\JsonStore;
use Illuminate\Console\Command;

/**
 * Stellt gespeicherte Werte um, wenn Schlüssel von Auswahllisten umbenannt wurden
 * (config/intranet.php → renames). Idempotent: läuft bei jedem Containerstart.
 * Ändert nur Werte, löscht nie Datensätze.
 */
class UpgradeIntranetDataCommand extends Command
{
    protected $signature = 'intranet:upgrade';

    protected $description = 'Stellt gespeicherte Werte gemäss config/intranet.php → renames um';

    public function handle(JsonStore $store): int
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: string}> $renames */
        $renames = config('intranet.renames', []);
        $total = 0;

        foreach ($renames as [$collection, $field, $from, $to]) {
            foreach ($store->all($collection) as $record) {
                if (($record[$field] ?? null) === $from) {
                    $store->update($collection, (string) $record['id'], [$field => $to]);
                    $total++;
                }
            }
        }

        $total === 0
            ? $this->components->info('Daten aktuell, keine Umstellung nötig.')
            : $this->components->info("{$total} Werte umgestellt.");

        return self::SUCCESS;
    }
}
