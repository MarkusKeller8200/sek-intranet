<?php

namespace App\Data\Contracts;

interface AnnouncementRepository extends Repository
{
    /**
     * Veröffentlichte Mitteilungen: angeheftete zuerst, dann neueste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function published(?int $limit = null): array;
}
