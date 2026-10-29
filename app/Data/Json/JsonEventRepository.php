<?php

namespace App\Data\Json;

use App\Data\Contracts\EventRepository;

class JsonEventRepository extends JsonRepository implements EventRepository
{
    protected string $collection = 'events';

    public function upcoming(?int $limit = null): array
    {
        $today = now()->startOfDay()->getTimestamp();

        $records = array_values(array_filter($this->chronological(), function (array $event) use ($today): bool {
            $end = self::stringValue($event['ends_at'] ?? '');
            $reference = $end !== '' ? $end : self::stringValue($event['starts_at'] ?? '');

            return (int) strtotime($reference) >= $today;
        }));

        return $limit === null ? $records : array_slice($records, 0, $limit);
    }

    public function chronological(): array
    {
        return $this->sortBy($this->all(), 'starts_at');
    }
}
