<?php

namespace Plugins\EduksxGlossary;

use App\Data\Contracts\GlossaryRepository;
use App\Data\Store\JsonStore;
use App\Support\Glossary;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Probelauf des Syncs (#161): liest aus dem echten Glossar, hält alle Änderungen nur im Speicher und protokolliert
 * sie (neu, geändert mit Feldern, ausgeblendet, Listen). Es wird nichts gespeichert.
 */
final class DryRunRepository implements GlossaryRepository
{
    /** @var array<string, array<string, mixed>> geänderte oder neue Einträge nach ID */
    private array $entries = [];

    /** @var array<string, array<string, array<string, mixed>>> geänderte oder neue Listeneinträge je Liste */
    private array $lists = ['kategorien' => [], 'herkunft' => []];

    /** @var array<string, mixed> */
    private array $state;

    /** @var list<array{art: string, liste: string, titel: string, felder: string}> */
    private array $log = [];

    public function __construct(private readonly GlossaryRepository $inner)
    {
        $this->state = $inner->syncState();
    }

    /**
     * @return list<array{art: string, liste: string, titel: string, felder: string}>
     */
    public function log(): array
    {
        return $this->log;
    }

    public function all(): array
    {
        $all = array_column($this->inner->all(), null, 'id');
        foreach ($this->entries as $id => $entry) {
            $all[$id] = $entry;
        }

        return array_values($all);
    }

    public function find(string $id): ?array
    {
        return $this->entries[$id] ?? $this->inner->find($id);
    }

    public function findOrFail(string $id): array
    {
        return $this->find($id) ?? throw new NotFoundHttpException("Datensatz {$id} nicht gefunden.");
    }

    public function create(array $attributes): array
    {
        $id = is_string($attributes['id'] ?? null) && $attributes['id'] !== '' ? $attributes['id'] : JsonStore::uuid7();
        $this->entries[$id] = ['id' => $id] + $attributes;
        $this->log[] = ['art' => 'neu', 'liste' => 'Glossar', 'titel' => self::title($this->entries[$id]), 'felder' => ''];

        return $this->entries[$id];
    }

    public function update(string $id, array $attributes): array
    {
        $before = $this->findOrFail($id);
        $after = array_merge($before, $attributes);
        $this->entries[$id] = $after;
        $fields = array_keys(array_filter($attributes, fn ($v, string $f): bool => ($before[$f] ?? null) != $v, ARRAY_FILTER_USE_BOTH));
        $art = ($attributes['aktiv'] ?? true) === false && Glossary::active($before) ? 'ausgeblendet' : 'geändert';
        $this->log[] = ['art' => $art, 'liste' => 'Glossar', 'titel' => self::title($after), 'felder' => implode(', ', array_map(fn (string $f): string => Glossary::FIELDS[$f] ?? $f, $fields))];

        return $after;
    }

    public function delete(string $id): bool
    {
        throw new RuntimeException('Der Sync löscht nie.');
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function listItems(string $list): array
    {
        $items = array_column($this->inner->listItems($list), null, 'id');
        foreach ($this->lists[$list] ?? [] as $id => $item) {
            $items[$id] = $item;
        }

        return array_values($items);
    }

    public function saveListItem(string $list, ?string $id, array $attributes): array
    {
        $current = $id !== null ? (array_column($this->listItems($list), null, 'id')[$id] ?? null) : null;
        $id ??= is_string($attributes['id'] ?? null) ? $attributes['id'] : JsonStore::uuid7();
        $item = array_merge($current ?? [], $attributes, ['id' => $id]);
        $this->lists[$list][$id] = $item;
        $name = $list === 'herkunft' ? 'Herkunft' : 'Kategorien';
        $this->log[] = ['art' => $current === null ? 'neu' : 'geändert', 'liste' => $name, 'titel' => trim((string) ($item['kuerzel'] ?? '').' – '.(string) ($item['bezeichnung'] ?? ''), ' –'), 'felder' => $current === null ? '' : implode(', ', array_keys($attributes))];

        return $item;
    }

    public function deleteListItem(string $list, string $id): bool
    {
        throw new RuntimeException('Der Sync löscht nie.');
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
        return 0;
    }

    public function syncState(): array
    {
        return $this->state;
    }

    public function saveSyncState(array $state): void
    {
        $this->state = array_merge($this->state, $state);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private static function title(array $entry): string
    {
        return trim(Glossary::shortName($entry).' – '.Glossary::displayName($entry), ' –');
    }
}
