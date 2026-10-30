<?php

namespace App\Support;

use App\Data\Store\JsonStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * API-Zugänge (Sammlung api_tokens). Das Token wird nur beim Erstellen einmal angezeigt;
 * gespeichert wird der SHA-256-Hash.
 */
final class ApiTokens
{
    public const COLLECTION = 'api_tokens';

    public const ABILITIES = ['read', 'write'];

    public function __construct(private readonly JsonStore $store) {}

    /**
     * @param  list<string>  $abilities
     * @return array{token: string, record: array<string, mixed>}
     */
    public function create(string $name, array $abilities, string $ownerId, ?Carbon $expiresAt): array
    {
        $token = 'sbi_'.Str::random(40);

        $record = $this->store->insert(self::COLLECTION, [
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'prefix' => substr($token, 0, 8),
            'abilities' => array_values(array_intersect(self::ABILITIES, $abilities)),
            'owner_id' => $ownerId,
            'expires_at' => $expiresAt?->toAtomString(),
            'last_used_at' => null,
        ]);

        return ['token' => $token, 'record' => self::public($record)];
    }

    /**
     * Gültiger Zugang zum Token oder null (unbekannt, abgelaufen).
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $token): ?array
    {
        if (! str_starts_with($token, 'sbi_')) {
            return null;
        }
        $hash = hash('sha256', $token);

        foreach ($this->store->all(self::COLLECTION) as $record) {
            if (hash_equals((string) ($record['token_hash'] ?? ''), $hash)) {
                $expires = $record['expires_at'] ?? null;
                if (is_string($expires) && Carbon::parse($expires)->isPast()) {
                    return null;
                }
                // Höchstens einmal pro Minute schreiben
                $last = $record['last_used_at'] ?? null;
                if (! is_string($last) || Carbon::parse($last)->lt(now()->subMinute())) {
                    $this->store->update(self::COLLECTION, (string) $record['id'], ['last_used_at' => now()->toAtomString()]);
                }

                return $record;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(self::public(...), $this->store->all(self::COLLECTION));
    }

    public function delete(string $id): bool
    {
        return $this->store->delete(self::COLLECTION, $id);
    }

    /**
     * Ohne Hash (nie an den Browser).
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function public(array $record): array
    {
        unset($record['token_hash']);

        return $record;
    }
}
