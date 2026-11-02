<?php

namespace Modules\Zeiterfassung\Data\Json;

use App\Data\Json\JsonRepository;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;

class JsonEintragRepository extends JsonRepository implements EintragRepository
{
    protected string $collection = 'zeit_eintraege';

    public function fuerPersonMonat(string $personId, string $monat): array
    {
        return $this->sortiert(array_filter($this->all(), fn (array $e): bool => ($e['person_id'] ?? null) === $personId && ($e['monat'] ?? null) === $monat));
    }

    public function fuerMonat(string $monat): array
    {
        return $this->sortiert(array_filter($this->all(), fn (array $e): bool => ($e['monat'] ?? null) === $monat));
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function sortiert(array $records): array
    {
        $records = array_values($records);
        usort($records, fn (array $a, array $b): int => [(string) ($a['datum'] ?? ''), (string) ($a['beginn'] ?? '')] <=> [(string) ($b['datum'] ?? ''), (string) ($b['beginn'] ?? '')]);

        return $records;
    }
}
