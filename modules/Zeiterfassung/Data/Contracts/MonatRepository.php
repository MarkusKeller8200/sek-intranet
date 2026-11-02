<?php

namespace Modules\Zeiterfassung\Data\Contracts;

use App\Data\Contracts\Repository;

/**
 * Status je Person und Monat (Sammlung zeit_monate, id = Person:JJJJ-MM).
 */
interface MonatRepository extends Repository
{
    /**
     * @return array<string, mixed> Status (ohne Datensatz: offen)
     */
    public function status(string $personId, string $monat): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function setzen(string $personId, string $monat, array $attributes): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fuerMonat(string $monat): array;
}
