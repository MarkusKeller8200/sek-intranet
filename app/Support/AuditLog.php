<?php

namespace App\Support;

use App\Data\Store\JsonStore;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Änderungsverlauf (Sammlung audit_log): alter und neuer Wert je Feld, wer, wann, Quelle.
 *
 * Gespeichert wird nur, was sich tatsächlich geändert hat. Der Verlauf ist nur für
 * die Administration sichtbar, weil er auch Admin-Felder enthält.
 */
final class AuditLog
{
    public const COLLECTION = 'audit_log';

    public const MAX_ENTRIES = 20000;

    /** Felder, die nie protokolliert werden. */
    private const IGNORED = ['id', 'created_at', 'updated_at'];

    public function __construct(private readonly JsonStore $store) {}

    /**
     * @param  array<string, mixed>|null  $before  null = neu angelegt
     * @param  array<string, mixed>|null  $after  null = gelöscht
     */
    public function record(string $collection, ?array $before, ?array $after, string $source, ?Authenticatable $user): void
    {
        $record = $after ?? $before;
        if ($record === null) {
            return;
        }

        $changes = [];
        $keys = array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? [])));
        foreach ($keys as $key) {
            if (in_array($key, self::IGNORED, true)) {
                continue;
            }
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new && ! ($old === null && $new === '') && ! ($old === '' && $new === null)) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        if ($changes === [] && $before !== null && $after !== null) {
            return;
        }

        $this->store->insert(self::COLLECTION, [
            'collection' => $collection,
            'record_id' => (string) ($record['id'] ?? ''),
            'record_label' => isset($record['name']) ? (string) $record['name'] : null,
            'action' => $before === null ? 'created' : ($after === null ? 'deleted' : 'updated'),
            'changes' => $changes,
            'source' => $source,
            'user_id' => $user !== null ? (string) $user->getAuthIdentifier() : null,
            'user_name' => $user !== null && method_exists($user, 'name') ? (string) $user->name() : null,
        ]);

        $all = $this->store->all(self::COLLECTION);
        if (count($all) > self::MAX_ENTRIES) {
            $this->store->replace(self::COLLECTION, array_slice($all, -self::MAX_ENTRIES));
        }
    }

    /**
     * Verlauf eines Datensatzes, neueste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function for(string $collection, string $id, int $limit = 200): array
    {
        $entries = array_values(array_filter(
            $this->store->all(self::COLLECTION),
            fn (array $e): bool => ($e['collection'] ?? null) === $collection && ($e['record_id'] ?? null) === $id,
        ));

        return array_slice(array_reverse($entries), 0, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return $this->store->find(self::COLLECTION, $id);
    }
}
