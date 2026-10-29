<?php

namespace App\Data\Json;

use App\Data\Contracts\Repository;
use App\Data\Store\JsonStore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class JsonRepository implements Repository
{
    /**
     * Name der Sammlung = Dateiname = späterer Tabellenname.
     */
    protected string $collection;

    public function __construct(protected readonly JsonStore $store) {}

    public function all(): array
    {
        return $this->store->all($this->collection);
    }

    public function find(string $id): ?array
    {
        return $this->store->find($this->collection, $id);
    }

    public function findOrFail(string $id): array
    {
        return $this->find($id) ?? throw new NotFoundHttpException("Datensatz {$id} nicht gefunden.");
    }

    public function create(array $attributes): array
    {
        return $this->store->insert($this->collection, $attributes);
    }

    public function update(string $id, array $attributes): array
    {
        return $this->store->update($this->collection, $id, $attributes)
            ?? throw new NotFoundHttpException("Datensatz {$id} nicht gefunden.");
    }

    public function delete(string $id): bool
    {
        return $this->store->delete($this->collection, $id);
    }

    public function count(): int
    {
        return $this->store->count($this->collection);
    }

    /**
     * Sortiert nach einem Feld (Strings, ISO-Daten und Zahlen).
     *
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    protected function sortBy(array $records, string $field, bool $descending = false): array
    {
        usort($records, function (array $a, array $b) use ($field, $descending): int {
            $result = strnatcasecmp(self::stringValue($a[$field] ?? ''), self::stringValue($b[$field] ?? ''));

            return $descending ? -$result : $result;
        });

        return $records;
    }

    protected static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
