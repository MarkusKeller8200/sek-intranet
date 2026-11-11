<?php

namespace Modules\Beschaffung\Data\Contracts;

use App\Data\Contracts\Repository;

/**
 * Katalogeinträge (Sammlung bes_artikel). Beim ersten Zugriff mit dem Startinhalt aus dem ICT-Wiki gefüllt.
 */
interface ArtikelRepository extends Repository
{
    /**
     * Einträge eines Katalogs nach Reihenfolge.
     *
     * @return list<array<string, mixed>>
     */
    public function katalog(string $katalog): array;

    /**
     * Anzahl Einträge und zu prüfende Einträge je Katalog.
     *
     * @return array<string, array{anzahl: int, pruefen: int}>
     */
    public function zaehlen(): array;
}
