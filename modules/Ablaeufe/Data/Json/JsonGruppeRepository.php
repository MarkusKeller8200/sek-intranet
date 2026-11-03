<?php

namespace Modules\Ablaeufe\Data\Json;

use App\Data\Json\JsonRepository;
use Modules\Ablaeufe\Data\Contracts\GruppeRepository;
use Modules\Ablaeufe\Startinhalt;

class JsonGruppeRepository extends JsonRepository implements GruppeRepository
{
    protected string $collection = 'wf_gruppen';

    public function all(): array
    {
        if (! $this->store->exists($this->collection)) {
            foreach (Startinhalt::gruppen() as $gruppe) {
                $this->store->insert($this->collection, $gruppe);
            }
        }

        return parent::all();
    }

    public function find(string $id): ?array
    {
        $this->all();

        return parent::find($id);
    }

    public function sortiert(): array
    {
        $items = $this->all();
        usort($items, fn (array $a, array $b): int => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));

        return $items;
    }
}
