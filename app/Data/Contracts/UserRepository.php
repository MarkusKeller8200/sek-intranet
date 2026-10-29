<?php

namespace App\Data\Contracts;

interface UserRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * Aktive Personen, alphabetisch nach Name.
     *
     * @return list<array<string, mixed>>
     */
    public function active(): array;

    /**
     * Zuordnung id => Name, für Anzeigen wie «Autor/in».
     *
     * @return array<string, string>
     */
    public function names(): array;
}
