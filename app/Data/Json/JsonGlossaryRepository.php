<?php

namespace App\Data\Json;

use App\Data\Contracts\GlossaryRepository;
use App\Data\Store\JsonStore;
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

        // Schlüssel des Startinhalts (#159): früher die ID, seit den UUIDs im Feld «schluessel»
        return array_map(fn (array $i, int $n): array => [
            ...$i,
            'label' => $i['label'] ?? $seed[(string) ($i['schluessel'] ?? $i['id'])] ?? (string) ($i['kuerzel'] ?? ''),
            'farbe' => ($i['farbe'] ?? '') !== '' ? $i['farbe'] : ($colors[(string) ($i['schluessel'] ?? $i['id'])] ?? $palette[$n % count($palette)]),
        ], $items, array_keys($items));
    }

    public function listId(string $list, string $key): ?string
    {
        foreach ($this->listItems($list) as $item) {
            if ((string) $item['id'] === $key || (string) ($item['schluessel'] ?? '') === $key) {
                return (string) $item['id'];
            }
        }

        return null;
    }

    public function migrateListIds(): int
    {
        $this->seed();
        $changed = 0;
        $map = [];
        foreach (self::LISTS as $list => $collection) {
            $map[$list] = [];
            $resolved = array_column($this->listItems($list), null, 'id');
            $records = [];
            foreach ($this->store->all($collection) as $record) {
                $old = (string) $record['id'];
                if (! self::isUuid($old)) {
                    $new = JsonStore::uuid7();
                    $map[$list][$old] = $new;
                    // Label und Farbe des Startinhalts festhalten (hingen an der alten ID), alter Schlüssel bleibt
                    $record = ['id' => $new, 'schluessel' => (string) ($record['schluessel'] ?? $old),
                        'label' => $resolved[$old]['label'] ?? '', 'farbe' => $resolved[$old]['farbe'] ?? ''] + $record;
                    $changed++;
                }
                $records[] = $record;
            }
            if ($map[$list] !== []) {
                $this->store->replace($collection, $records);
            }
        }
        if ($changed === 0) {
            return 0;
        }
        // Verweise der Einträge umstellen; Zeitstempel bleiben (nur Schlüssel ersetzt)
        $entries = [];
        foreach ($this->store->all($this->collection) as $entry) {
            foreach ($map as $list => $ids) {
                $entry[$list] = array_values(array_map(fn ($id): string => $ids[(string) $id] ?? (string) $id, (array) ($entry[$list] ?? [])));
            }
            $entries[] = $entry;
        }
        $this->store->replace($this->collection, $entries);
        // Zuordnung des Syncs (#156)
        $state = $this->syncState();
        if (is_array($state['listen'] ?? null)) {
            foreach ($map as $list => $ids) {
                $state['listen'][$list] = array_map(fn ($id): string => $ids[(string) $id] ?? (string) $id, (array) ($state['listen'][$list] ?? []));
            }
            $this->saveSyncState(['listen' => $state['listen']]);
        }

        return $changed;
    }

    public static function isUuid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
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
        // Listen mit UUID; der Schlüssel des Startinhalts steht in «schluessel» (#159)
        $ids = ['kategorien' => [], 'herkunft' => []];
        if (! $this->store->exists(self::LISTS['kategorien'])) {
            foreach (GlossarySeed::kategorien() as $i => [$key, $kuerzel, $name, $desc, $label]) {
                $ids['kategorien'][$key] = (string) $this->store->insert(self::LISTS['kategorien'], ['id' => JsonStore::uuid7(), 'schluessel' => $key, 'kuerzel' => $kuerzel, 'label' => $label, 'farbe' => GlossarySeed::FARBEN['kategorien'][$key] ?? '', 'bezeichnung' => $name, 'beschreibung' => $desc, 'url' => '', 'notiz' => '', 'position' => $i + 1])['id'];
            }
        }
        if (! $this->store->exists(self::LISTS['herkunft'])) {
            foreach (GlossarySeed::herkunft() as $i => [$key, $kuerzel, $name, $url, $label]) {
                $ids['herkunft'][$key] = (string) $this->store->insert(self::LISTS['herkunft'], ['id' => JsonStore::uuid7(), 'schluessel' => $key, 'kuerzel' => $kuerzel, 'label' => $label, 'farbe' => GlossarySeed::FARBEN['herkunft'][$key] ?? '', 'bezeichnung' => $name, 'beschreibung' => '', 'url' => $url, 'notiz' => '', 'position' => $i + 1])['id'];
            }
        }
        if (! $this->store->exists($this->collection)) {
            $id = function (string $list, string $key) use ($ids): string {
                if (isset($ids[$list][$key])) {
                    return $ids[$list][$key];
                }
                foreach ($this->store->all(self::LISTS[$list]) as $item) {
                    if (($item['schluessel'] ?? $item['id']) === $key) {
                        return (string) $item['id'];
                    }
                }

                return $key;
            };
            foreach (GlossarySeed::eintraege() as [$kat, $abk, $aliase, $bez, $status, $notiz]) {
                $this->store->insert($this->collection, [
                    'kategorien' => [$id('kategorien', $kat)],
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
                    'herkunft' => [$id('herkunft', 'vsa')],
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
