<?php

namespace Modules\Ablaeufe\Data\Contracts;

use App\Data\Contracts\Repository;

/**
 * Personengruppen (Sammlung wf_gruppen). Beim ersten Zugriff mit den Gruppen aus der Vorlage gefüllt.
 */
interface GruppeRepository extends Repository
{
    /**
     * @return list<array<string, mixed>> nach Reihenfolge
     */
    public function sortiert(): array;
}
