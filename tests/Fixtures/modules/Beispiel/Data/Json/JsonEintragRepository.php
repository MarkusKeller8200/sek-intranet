<?php

namespace Modules\Beispiel\Data\Json;

use App\Data\Json\JsonRepository;
use Modules\Beispiel\Data\Contracts\EintragRepository;

class JsonEintragRepository extends JsonRepository implements EintragRepository
{
    protected string $collection = 'beispiel_eintraege';

    public function neueste(int $limit = 20): array
    {
        return array_slice($this->sortBy($this->all(), 'created_at', true), 0, $limit);
    }
}
