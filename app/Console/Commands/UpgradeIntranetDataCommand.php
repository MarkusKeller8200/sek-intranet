<?php

namespace App\Console\Commands;

use App\Data\Store\JsonStore;
use App\Support\PersonFields;
use Illuminate\Console\Command;

/**
 * Stellt gespeicherte Werte um, wenn Schlüssel von Auswahllisten umbenannt wurden
 * (config/intranet.php → renames) und ergänzt neue abgeleitete Felder.
 * Idempotent: läuft bei jedem Containerstart.
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

        // Vor-/Nachname aus dem Anzeigenamen ableiten (Personen von vor der Feldaufteilung)
        foreach ($store->all('users') as $record) {
            if (($record['last_name'] ?? null) === null && ($record['name'] ?? '') !== '') {
                $names = PersonFields::withNames($record);
                $store->update('users', (string) $record['id'], array_intersect_key($names, array_flip(['first_name', 'last_name', 'name'])));
                $total++;
            }
        }

        $total === 0
            ? $this->components->info('Daten aktuell, keine Umstellung nötig.')
            : $this->components->info("{$total} Werte umgestellt.");

        return self::SUCCESS;
    }
}
