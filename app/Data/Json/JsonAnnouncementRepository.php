<?php

namespace App\Data\Json;

use App\Data\Contracts\AnnouncementRepository;

class JsonAnnouncementRepository extends JsonRepository implements AnnouncementRepository
{
    protected string $collection = 'announcements';

    public function published(?int $limit = null): array
    {
        $now = now()->getTimestamp();

        $records = array_values(array_filter(
            $this->all(),
            fn (array $item): bool => (int) strtotime(self::stringValue($item['published_at'] ?? '')) <= $now,
        ));

        usort($records, function (array $a, array $b): int {
            $pinned = (int) (($b['pinned'] ?? false) === true) <=> (int) (($a['pinned'] ?? false) === true);

            return $pinned !== 0
                ? $pinned
                : strcmp(self::stringValue($b['published_at'] ?? ''), self::stringValue($a['published_at'] ?? ''));
        });

        return $limit === null ? $records : array_slice($records, 0, $limit);
    }
}
