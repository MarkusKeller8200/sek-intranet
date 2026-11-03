<?php

namespace App\Data\Json;

use App\Data\Contracts\GlossaryRepository;
use App\Support\GlossarySeed;
use InvalidArgumentException;

class JsonGlossaryRepository extends JsonRepository implements GlossaryRepository
{
    public const LISTS = ['kategorien' => 'glossar_kategorien', 'herkunft' => 'glossar_herkunft'];

    protected string $collection = 'glossar';

    public function all(): array
    {
        $this->seed();

        return parent::all();
    }

    public function find(string $id): ?array
    {
        $this->seed();

        return parent::find($id);
    }

    public function listItems(string $list): array
    {
        $this->seed();
        $items = $this->store->all($this->listCollection($list));
        usort($items, fn (array $a, array $b): int => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));

        return $items;
    }

    public function saveListItem(string $list, ?string $id, array $attributes): array
    {
        $collection = $this->listCollection($list);
        if ($id !== null) {
            return $this->store->update($collection, $id, $attributes)
                ?? throw new InvalidArgumentException("Eintrag {$id} gibt es nicht.");
        }

        return $this->store->insert($collection, $attributes + ['position' => count($this->store->all($collection)) + 1]);
    }

    public function deleteListItem(string $list, string $id): bool
    {
        return $this->store->delete($this->listCollection($list), $id);
    }

    private function listCollection(string $list): string
    {
        return self::LISTS[$list] ?? throw new InvalidArgumentException("Liste «{$list}» gibt es nicht.");
    }

    /** Startinhalt beim ersten Zugriff (danach nie mehr, auch nicht nach dem Löschen). */
    private function seed(): void
    {
        if (! $this->store->exists(self::LISTS['kategorien'])) {
            foreach (GlossarySeed::kategorien() as $i => [$id, $kuerzel, $name, $desc]) {
                $this->store->insert(self::LISTS['kategorien'], ['id' => $id, 'kuerzel' => $kuerzel, 'bezeichnung' => $name, 'beschreibung' => $desc, 'url' => '', 'notiz' => '', 'position' => $i + 1]);
            }
        }
        if (! $this->store->exists(self::LISTS['herkunft'])) {
            foreach (GlossarySeed::herkunft() as $i => [$id, $kuerzel, $name, $url]) {
                $this->store->insert(self::LISTS['herkunft'], ['id' => $id, 'kuerzel' => $kuerzel, 'bezeichnung' => $name, 'beschreibung' => '', 'url' => $url, 'notiz' => '', 'position' => $i + 1]);
            }
        }
        if (! $this->store->exists($this->collection)) {
            foreach (GlossarySeed::eintraege() as [$kat, $abk, $aliase, $bez, $status, $notiz]) {
                $this->store->insert($this->collection, [
                    'kategorien' => [$kat],
                    'abk_offiziell' => $abk,
                    'aliase_offiziell' => $aliase,
                    'abk_eigen' => '',
                    'alias_eigen' => '',
                    'bez_offiziell' => $bez,
                    'bez_eigen' => '',
                    'kurzbeschreibung' => '',
                    'beschreibung' => '',
                    'url' => '',
                    'notiz' => $notiz,
                    'herkunft' => ['vsa'],
                    'genutzt_in' => [],
                    'status' => $status,
                    'suchbegriffe' => '',
                    'gueltig_ab' => null,
                    'gueltig_bis' => null,
                    'dubletten_ok' => [],
                ]);
            }
        }
    }
}
