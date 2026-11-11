<?php

namespace Modules\Beschaffung\Data\Json;

use App\Data\Json\JsonRepository;
use Modules\Beschaffung\Data\Contracts\ArtikelRepository;
use Modules\Beschaffung\Startinhalt;

class JsonArtikelRepository extends JsonRepository implements ArtikelRepository
{
    protected string $collection = 'bes_artikel';

    public function all(): array
    {
        if (! $this->store->exists($this->collection)) {
            // Startinhalt in einem Schritt schreiben (rund 500 Einträge)
            $this->store->replace($this->collection, Startinhalt::artikel());
        }

        return parent::all();
    }

    public function find(string $id): ?array
    {
        $this->all();

        return parent::find($id);
    }

    public function katalog(string $katalog): array
    {
        $items = array_values(array_filter($this->all(), fn (array $a): bool => ($a['katalog'] ?? '') === $katalog));
        usort($items, fn (array $a, array $b): int => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));

        return $items;
    }

    public function zaehlen(): array
    {
        $result = [];
        foreach ($this->all() as $a) {
            $k = (string) ($a['katalog'] ?? '');
            $result[$k] ??= ['anzahl' => 0, 'pruefen' => 0];
            $result[$k]['anzahl']++;
            $result[$k]['pruefen'] += ($a['pruefen'] ?? false) === true ? 1 : 0;
        }

        return $result;
    }
}
