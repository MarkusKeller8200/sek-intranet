<?php

namespace Modules\Zeiterfassung\Data\Json;

use App\Data\Json\JsonRepository;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;

class JsonMonatRepository extends JsonRepository implements MonatRepository
{
    protected string $collection = 'zeit_monate';

    public function status(string $personId, string $monat): array
    {
        return $this->find($personId.':'.$monat) ?? ['id' => $personId.':'.$monat, 'person_id' => $personId, 'monat' => $monat, 'status' => 'offen'];
    }

    public function setzen(string $personId, string $monat, array $attributes): array
    {
        $id = $personId.':'.$monat;
        if ($this->find($id) !== null) {
            return $this->update($id, $attributes);
        }

        return $this->create(['id' => $id, 'person_id' => $personId, 'monat' => $monat] + $attributes);
    }

    public function fuerMonat(string $monat): array
    {
        return array_values(array_filter($this->all(), fn (array $m): bool => ($m['monat'] ?? null) === $monat));
    }
}
