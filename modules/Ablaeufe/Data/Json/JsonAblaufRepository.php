<?php

namespace Modules\Ablaeufe\Data\Json;

use App\Data\Json\JsonRepository;
use Modules\Ablaeufe\Data\Contracts\AblaufRepository;
use Modules\Ablaeufe\Startinhalt;

class JsonAblaufRepository extends JsonRepository implements AblaufRepository
{
    protected string $collection = 'wf_ablaeufe';

    public function all(): array
    {
        // Erster Zugriff: Ablauf «Mitarbeitende» aus der Vorlage anlegen (danach nie mehr, auch wenn gelöscht)
        if (! $this->store->exists($this->collection)) {
            foreach (Startinhalt::ablaeufe() as $ablauf) {
                $this->store->insert($this->collection, $ablauf);
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
        $order = ['personal' => 1, 'schueler' => 2, 'schulbetrieb' => 3, 'anderes' => 4];
        $items = $this->all();
        usort($items, fn (array $a, array $b): int => [$order[$a['bereich'] ?? ''] ?? 9, mb_strtolower((string) ($a['titel'] ?? ''))]
            <=> [$order[$b['bereich'] ?? ''] ?? 9, mb_strtolower((string) ($b['titel'] ?? ''))]);

        return $items;
    }
}
