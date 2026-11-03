<?php

namespace App\Support;

use App\Data\Store\JsonStore;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Anmeldung im Prototyp mit einem 8-stelligen PIN je Rolle (#69; später OIDC).
 *
 * Gespeichert wird nur der Hash (access_pins, id = Rolle) – nie der PIN, nie im Repository oder Log.
 * Sobald der PIN der Administration gesetzt ist, gilt die PIN-Anmeldung und das gemeinsame
 * Passwort (HTTP Basic Auth) entfällt.
 */
final class RolePins
{
    public const COLLECTION = 'access_pins';

    public const ADMIN = 'admin';

    public const PATTERN = '/^\d{8}$/';

    public function __construct(private readonly JsonStore $store) {}

    /**
     * @return array<string, string> Rolle → Bezeichnung (Anmeldeseite)
     */
    public static function roles(): array
    {
        /** @var array<string, string> $roles */
        $roles = (array) config('intranet.pin_roles', []);

        return $roles;
    }

    /** PIN-Anmeldung aktiv (PIN der Administration gesetzt). */
    public function active(): bool
    {
        return $this->hash(self::ADMIN) !== null;
    }

    public function isSet(string $role): bool
    {
        return $this->hash($role) !== null;
    }

    /**
     * @return array<string, array{label: string, set: bool, updated_at: string|null}>
     */
    public function overview(): array
    {
        $list = [];
        foreach (self::roles() as $role => $label) {
            $record = $this->store->find(self::COLLECTION, $role);
            $list[$role] = ['label' => $label, 'set' => $record !== null, 'updated_at' => is_string($record['updated_at'] ?? null) ? $record['updated_at'] : null];
        }

        return $list;
    }

    public function check(string $role, string $pin): bool
    {
        $hash = $this->hash($role);

        return $hash !== null && preg_match(self::PATTERN, $pin) === 1 && Hash::check($pin, $hash);
    }

    public function set(string $role, string $pin, ?string $userId): void
    {
        if (! array_key_exists($role, self::roles())) {
            throw new InvalidArgumentException("Rolle «{$role}» hat keinen PIN.");
        }
        if (preg_match(self::PATTERN, $pin) !== 1) {
            throw new InvalidArgumentException('Der PIN muss genau 8 Ziffern haben.');
        }
        $attributes = ['hash' => Hash::make($pin), 'updated_by' => $userId];
        $this->store->find(self::COLLECTION, $role) !== null
            ? $this->store->update(self::COLLECTION, $role, $attributes)
            : $this->store->insert(self::COLLECTION, ['id' => $role] + $attributes);
    }

    /** Ist dieser PIN schon für eine andere Rolle gesetzt? (Jede Rolle braucht einen eigenen PIN.) */
    public function usedByOther(string $role, string $pin): bool
    {
        foreach (array_keys(self::roles()) as $other) {
            if ($other !== $role && $this->check($other, $pin)) {
                return true;
            }
        }

        return false;
    }

    public function clear(string $role): void
    {
        $this->store->delete(self::COLLECTION, $role);
    }

    private function hash(string $role): ?string
    {
        $hash = $this->store->find(self::COLLECTION, $role)['hash'] ?? null;

        return is_string($hash) && $hash !== '' ? $hash : null;
    }
}
