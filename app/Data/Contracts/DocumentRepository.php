<?php

namespace App\Data\Contracts;

interface DocumentRepository extends Repository
{
    /**
     * Alle Dokumente, sortiert nach Kategorie und Titel.
     *
     * @return list<array<string, mixed>>
     */
    public function sorted(): array;
}
