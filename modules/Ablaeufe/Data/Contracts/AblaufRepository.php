<?php

namespace Modules\Ablaeufe\Data\Contracts;

use App\Data\Contracts\Repository;

/**
 * Abläufe (Sammlung wf_ablaeufe). Beim ersten Zugriff mit dem Startinhalt gefüllt.
 */
interface AblaufRepository extends Repository
{
    /**
     * Alle Abläufe, sortiert nach Bereich und Titel.
     *
     * @return list<array<string, mixed>>
     */
    public function sortiert(): array;
}
