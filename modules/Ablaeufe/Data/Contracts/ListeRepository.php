<?php

namespace Modules\Ablaeufe\Data\Contracts;

use App\Data\Contracts\Repository;

/**
 * Eine Tabelle der Einstellungen (Orte, Fachbereiche, Rollen, Zeitlich, Mitwirkung).
 */
interface ListeRepository extends Repository
{
    /**
     * @return list<array<string, mixed>> nach Reihenfolge
     */
    public function sortiert(): array;
}
