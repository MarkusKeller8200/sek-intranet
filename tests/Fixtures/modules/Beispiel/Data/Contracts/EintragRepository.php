<?php

namespace Modules\Beispiel\Data\Contracts;

use App\Data\Contracts\Repository;

/**
 * Einträge des Moduls Beispielmodul (Sammlung beispiel_eintraege).
 */
interface EintragRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function neueste(int $limit = 20): array;
}
