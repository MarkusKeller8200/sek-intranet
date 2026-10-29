<?php

namespace App\Data\Json;

use App\Data\Contracts\DocumentRepository;

class JsonDocumentRepository extends JsonRepository implements DocumentRepository
{
    protected string $collection = 'documents';

    public function sorted(): array
    {
        $records = $this->all();

        usort($records, fn (array $a, array $b): int => [self::stringValue($a['category'] ?? ''), mb_strtolower(self::stringValue($a['title'] ?? ''))]
            <=> [self::stringValue($b['category'] ?? ''), mb_strtolower(self::stringValue($b['title'] ?? ''))]);

        return $records;
    }
}
