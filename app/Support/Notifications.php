<?php

namespace App\Support;

use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use Illuminate\Support\Carbon;

/**
 * Benachrichtigungen im Intranet (Glocke oben rechts, Seite «Benachrichtigungen»). Andere Teile des
 * Intranets melden Ereignisse mit `toAbility()` bzw. `toUsers()`; die auslösende Person erhält keine Meldung.
 * Keine Personendaten von Schüler:innen in Titel oder Text.
 */
final class Notifications
{
    public const COLLECTION = 'notifications';

    /** Höchstens so viele je Person (ältere gelesene werden beim Senden entfernt). */
    private const KEEP = 200;

    public function __construct(private readonly JsonStore $store, private readonly UserRepository $users) {}

    /**
     * An alle aktiven Personen mit einem Recht (config/intranet.php → abilities) senden.
     *
     * @return int Anzahl Empfänger:innen
     */
    public function toAbility(string $ability, string $kind, string $title, string $body, string $href, ?string $actorId): int
    {
        $ids = [];
        foreach ($this->users->active() as $user) {
            if (Roles::allows((string) ($user['role'] ?? ''), $ability)) {
                $ids[] = (string) $user['id'];
            }
        }

        return $this->toUsers($ids, $kind, $title, $body, $href, $actorId);
    }

    /**
     * @param  list<string|null>  $userIds
     * @return int Anzahl Empfänger:innen
     */
    public function toUsers(array $userIds, string $kind, string $title, string $body, string $href, ?string $actorId): int
    {
        $ids = array_values(array_unique(array_filter($userIds, fn (?string $id): bool => is_string($id) && $id !== '' && $id !== $actorId)));
        $now = Carbon::now()->toIso8601String();
        foreach ($ids as $id) {
            $this->store->insert(self::COLLECTION, [
                'user_id' => $id,
                'kind' => $kind,
                'title' => mb_substr($title, 0, 160),
                'body' => mb_substr($body, 0, 500),
                'href' => str_starts_with($href, '/') ? $href : '/',
                'created_by' => $actorId,
                'read_at' => null,
                'sent_at' => $now,
            ]);
        }
        if ($ids !== []) {
            $this->prune($ids);
        }

        return count($ids);
    }

    /**
     * @return list<array<string, mixed>> neueste zuerst
     */
    public function forUser(string $userId, int $limit = 50): array
    {
        $own = array_values(array_filter($this->store->all(self::COLLECTION), fn (array $n): bool => ($n['user_id'] ?? null) === $userId));
        usort($own, fn (array $a, array $b): int => strcmp((string) $b['sent_at'], (string) $a['sent_at']));

        return array_slice($own, 0, $limit);
    }

    /**
     * Für die Glocke: Anzahl ungelesen und die neuesten Einträge.
     *
     * @return array{unread: int, latest: list<array<string, mixed>>}
     */
    public function summary(string $userId): array
    {
        $all = $this->forUser($userId, self::KEEP);

        return [
            'unread' => count(array_filter($all, fn (array $n): bool => $n['read_at'] === null)),
            'latest' => array_map(fn (array $n): array => array_intersect_key($n, array_flip(['id', 'kind', 'title', 'body', 'href', 'read_at', 'sent_at'])), array_slice($all, 0, 8)),
        ];
    }

    /**
     * @return array<string, mixed>|null die Benachrichtigung (nur eigene)
     */
    public function markRead(string $userId, string $id): ?array
    {
        $n = $this->store->find(self::COLLECTION, $id);
        if ($n === null || $n['user_id'] !== $userId) {
            return null;
        }
        if ($n['read_at'] === null) {
            $n = (array) $this->store->update(self::COLLECTION, $id, ['read_at' => Carbon::now()->toIso8601String()]);
        }

        return $n;
    }

    public function markAllRead(string $userId): int
    {
        $count = 0;
        foreach ($this->forUser($userId, self::KEEP) as $n) {
            if ($n['read_at'] === null) {
                $this->store->update(self::COLLECTION, (string) $n['id'], ['read_at' => Carbon::now()->toIso8601String()]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<string>  $userIds
     */
    private function prune(array $userIds): void
    {
        foreach ($userIds as $id) {
            $read = array_filter($this->forUser($id, PHP_INT_MAX), fn (array $n): bool => $n['read_at'] !== null);
            foreach (array_slice(array_values($read), self::KEEP) as $old) {
                $this->store->delete(self::COLLECTION, (string) $old['id']);
            }
        }
    }
}
