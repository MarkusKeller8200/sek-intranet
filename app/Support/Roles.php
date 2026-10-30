<?php

namespace App\Support;

/**
 * Rollenkonzept (docs/KONZEPT-ROLLEN.md): Rollen und Rechte aus config/intranet.php.
 * Menüs und Routen fragen nur Rechte (Gates) ab, nie Rollen direkt.
 */
class Roles
{
    /**
     * @return array<string, string> Schlüssel → Bezeichnung
     */
    public static function all(): array
    {
        /** @var array<string, string> $roles */
        $roles = (array) config('intranet.roles', []);

        return $roles;
    }

    /**
     * @return array<string, array{label: string, roles: list<string>}>
     */
    public static function abilities(): array
    {
        $abilities = [];
        foreach ((array) config('intranet.abilities', []) as $key => $ability) {
            if (! is_array($ability)) {
                continue;
            }
            $abilities[(string) $key] = [
                'label' => (string) ($ability['label'] ?? $key),
                'roles' => array_values(array_map('strval', (array) ($ability['roles'] ?? []))),
            ];
        }

        return $abilities;
    }

    public static function allows(string $role, string $ability): bool
    {
        return in_array($role, self::abilities()[$ability]['roles'] ?? [], true);
    }
}
