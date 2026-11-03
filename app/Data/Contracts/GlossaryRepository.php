<?php

namespace App\Data\Contracts;

/**
 * Glossar (Sammlung glossar) mit den Listen Kategorien (glossar_kategorien) und Herkunft (glossar_herkunft).
 * Beim ersten Zugriff mit dem Startinhalt gefüllt (App\Support\GlossarySeed).
 */
interface GlossaryRepository extends Repository
{
    /**
     * Einträge einer Liste («kategorien» oder «herkunft»), nach Reihenfolge.
     *
     * @return list<array<string, mixed>>
     */
    public function listItems(string $list): array;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function saveListItem(string $list, ?string $id, array $attributes): array;

    public function deleteListItem(string $list, string $id): bool;
}
