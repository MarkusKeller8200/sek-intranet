<?php

namespace Modules\Ablaeufe\Data\Json;

use App\Data\Json\JsonRepository;
use App\Data\Store\JsonStore;
use Modules\Ablaeufe\Data\Contracts\ListeRepository;
use Modules\Ablaeufe\Listen;

class JsonListeRepository extends JsonRepository implements ListeRepository
{
    public function __construct(JsonStore $store, private readonly string $key)
    {
        parent::__construct($store);
        $this->collection = Listen::alle()[$key]['collection'];
    }

    public function all(): array
    {
        // Erster Zugriff: Startwerte anlegen (danach nie mehr, auch nicht nach dem Löschen)
        if (! $this->store->exists($this->collection)) {
            foreach (Listen::startwerte($this->key) as $row) {
                $this->store->insert($this->collection, $row);
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
