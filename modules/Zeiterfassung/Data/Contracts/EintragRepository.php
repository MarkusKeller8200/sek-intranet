<?php

namespace Modules\Zeiterfassung\Data\Contracts;

use App\Data\Contracts\Repository;

/**
 * Zeiteinträge (Sammlung zeit_eintraege).
 */
interface EintragRepository extends Repository
{
    /**
     * Einträge einer Person in einem Monat, nach Datum und Beginn.
     *
     * @return list<array<string, mixed>>
     */
    public function fuerPersonMonat(string $personId, string $monat): array;

    /**
     * Alle Einträge eines Monats.
     *
     * @return list<array<string, mixed>>
     */
    public function fuerMonat(string $monat): array;
}
