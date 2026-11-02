<?php

namespace App\Console\Commands;

use App\Data\Store\JsonStore;
use App\Support\DemoStudents;
use App\Support\PersonFields;
use App\Support\PersonLinks;
use App\Support\PersonTypes;
use App\Support\ReleaseAnnouncements;
use Illuminate\Console\Command;

/**
 * Stellt gespeicherte Werte um, wenn Schlüssel von Auswahllisten umbenannt wurden
 * (config/intranet.php → renames) und ergänzt neue abgeleitete Felder.
 * Idempotent: läuft bei jedem Containerstart.
 * Ändert nur Werte, löscht nie Datensätze. Legt zum freigegebenen Release einmal die Mitteilung «Neu im Intranet» an.
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

        // Demo-Personen auf das Rollenkonzept umstellen (nur erfundene @sekbuelach.example-Adressen)
        /** @var list<array{0: string, 1: string, 2: string}> $demoRoles */
        $demoRoles = config('intranet.demo_roles', []);
        foreach ($demoRoles as [$email, $from, $to]) {
            if (! str_ends_with($email, '@sekbuelach.example')) {
                continue;
            }
            foreach ($store->all('users') as $record) {
                if (($record['email'] ?? null) === $email && ($record['role'] ?? null) === $from) {
                    $store->update('users', (string) $record['id'], ['role' => $to]);
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

        // Personenart nachtragen (Datensätze von vor der Einführung)
        foreach ($store->all('users') as $record) {
            if (($record['person_type'] ?? null) === null) {
                $store->update('users', (string) $record['id'], ['person_type' => PersonTypes::fromRole(is_string($record['role'] ?? null) ? $record['role'] : null)]);
                $total++;
            }
        }

        // Escola-ID als Datenherkunft übernehmen (einmalig je Person)
        $links = app(PersonLinks::class);
        foreach ($store->all('users') as $record) {
            $escolaId = $record['escola_id'] ?? null;
            if (is_string($escolaId) && $escolaId !== '' && $links->findPerson('escola', $escolaId) === null) {
                $links->save(['person_id' => (string) $record['id'], 'direction' => 'source', 'system' => 'escola', 'external_id' => $escolaId, 'id_label' => 'Escola-ID']);
                $total++;
            }
        }

        // Prototyp: erfundene Schüler:innen und Eltern, Beispiel-Datenherkunft
        $total += DemoStudents::addIfMissing($store);
        $total += DemoStudents::addDemoLinks($store);

        // Neuerungen des freigegebenen Releases einmal als Mitteilung (CHANGELOG «Für Mitarbeitende»)
        $version = (string) config('intranet.version', '0.0.0');
        $announcement = app(ReleaseAnnouncements::class)->publish($version, (string) @file_get_contents(base_path('CHANGELOG.md')));
        if ($announcement !== null) {
            $this->components->info("Mitteilung «{$announcement['title']}» angelegt.");
        }

        $total === 0
            ? $this->components->info('Daten aktuell, keine Umstellung nötig.')
            : $this->components->info("{$total} Werte umgestellt.");

        return self::SUCCESS;
    }
}
