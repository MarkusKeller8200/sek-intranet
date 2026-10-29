<?php

namespace App\Data\Contracts;

interface EventRepository extends Repository
{
    /**
     * Termine ab heute, chronologisch.
     *
     * @return list<array<string, mixed>>
     */
    public function upcoming(?int $limit = null): array;

    /**
     * Alle Termine chronologisch.
     *
     * @return list<array<string, mixed>>
     */
    public function chronological(): array;
}
