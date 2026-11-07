<?php

namespace App\Data\Json;

use App\Data\Contracts\GlossaryRepository;
use App\Support\Glossary;
use App\Support\GlossarySeed;
use InvalidArgumentException;

class JsonGlossaryRepository extends JsonRepository implements GlossaryRepository
{
    public const LISTS = ['kategorien' => 'glossar_kategorien', 'herkunft' => 'glossar_herkunft'];

    /** Stand des Syncs mit dem EduKSX-Glossary (#156), ein Datensatz */
    public const SYNC = 'glossar_sync';

    public const SYNC_ID = 'eduksx';

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

        // Feld «Label» (#64, #66): ältere Einträge erhalten das Label des Startinhalts bzw. die Abkürzung
        $seed = array_column($list === 'herkunft' ? GlossarySeed::herkunft() : GlossarySeed::kategorien(), 4, 0);

        // Feld «Farbe» (#74): Startinhalt mit fester Farbe, weitere reihum aus der Palette
        $colors = GlossarySeed::FARBEN[$list] ?? [];
        $palette = Glossary::COLORS;

        return array_map(fn (array $i, int $n): array => [
            ...$i,
            'label' => $i['label'] ?? $seed[(string) $i['id']] ?? (string) ($i['kuerzel'] ?? ''),
            'farbe' => ($i['farbe'] ?? '') !== '' ? $i['farbe'] : ($colors[(string) $i['id']] ?? $palette[$n % count($palette)]),
        ], $items, array_keys($items));
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

    public function syncState(): array
    {
        $state = $this->store->find(self::SYNC, self::SYNC_ID) ?? [];
        unset($state['id'], $state['created_at'], $state['updated_at']);

        return $state;
    }

    public function saveSyncState(array $state): void
    {
        if ($this->store->find(self::SYNC, self::SYNC_ID) === null) {
            $this->store->insert(self::SYNC, ['id' => self::SYNC_ID] + $state);

            return;
        }
        $this->store->update(self::SYNC, self::SYNC_ID, $state);
    }

    private function listCollection(string $list): string
    {
        return self::LISTS[$list] ?? throw new InvalidArgumentException("Liste «{$list}» gibt es nicht.");
    }

    /** Startinhalt beim ersten Zugriff (danach nie mehr, auch nicht nach dem Löschen). */
    private function seed(): void
    {
        if (! $this->store->exists(self::LISTS['kategorien'])) {
            foreach (GlossarySeed::kategorien() as $i => [$id, $kuerzel, $name, $desc, $label]) {
                $this->store->insert(self::LISTS['kategorien'], ['id' => $id, 'kuerzel' => $kuerzel, 'label' => $label, 'bezeichnung' => $name, 'beschreibung' => $desc, 'url' => '', 'notiz' => '', 'position' => $i + 1]);
            }
        }
        if (! $this->store->exists(self::LISTS['herkunft'])) {
            foreach (GlossarySeed::herkunft() as $i => [$id, $kuerzel, $name, $url, $label]) {
                $this->store->insert(self::LISTS['herkunft'], ['id' => $id, 'kuerzel' => $kuerzel, 'label' => $label, 'bezeichnung' => $name, 'beschreibung' => '', 'url' => $url, 'notiz' => '', 'position' => $i + 1]);
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
