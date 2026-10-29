<?php

namespace App\Data\Contracts;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gemeinsame Schnittstelle aller Fach-Repositories.
 *
 * Datensätze sind assoziative Arrays mit mindestens id, created_at, updated_at.
 * Heute: JSON-Dateien (App\Data\Json). Später: PostgreSQL-Implementierung
 * derselben Schnittstelle, Controller und Oberfläche bleiben unverändert.
 */
interface Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array;

    /**
     * @return array<string, mixed>
     *
     * @throws NotFoundHttpException
     */
    public function findOrFail(string $id): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws NotFoundHttpException
     */
    public function update(string $id, array $attributes): array;

    public function delete(string $id): bool;

    public function count(): int;
}
