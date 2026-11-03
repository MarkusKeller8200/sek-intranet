<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Personenart (config/intranet.php → person_types): wer ist Personal, wer Schüler:in oder Eltern.
 * Bestimmt Anmeldung und Sichtbarkeit; die Rechte im Intranet kommen weiterhin aus der Rolle.
 */
final class PersonTypes
{
    public const STUDENT = 'schueler';

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        /** @var array<string, string> $types */
        $types = (array) config('intranet.person_types', []);

        return $types;
    }

    /** Art für ältere Datensätze und Eingaben ohne Angabe, abgeleitet aus der Rolle. */
    public static function fromRole(?string $role): string
    {
        return match ($role) {
            'lehrperson', 'ict' => 'lehrperson',
            'schulleitung' => 'schulleitung',
            'schueler', 'eltern', 'extern' => $role,
            default => 'mitarbeitende',
        };
    }

    /**
     * @param  array<string, mixed>  $person
     */
    public static function of(array $person): string
    {
        $type = $person['person_type'] ?? null;

        return is_string($type) && $type !== '' ? $type : self::fromRole(is_string($person['role'] ?? null) ? $person['role'] : null);
    }

    /**
     * Darf sich anmelden: Personalart und nicht «Kein Zugang».
     *
     * @param  array<string, mixed>  $person
     */
    public static function canLogin(array $person): bool
    {
        return ($person['role'] ?? null) !== 'kein-zugang'
            && in_array(self::of($person), (array) config('intranet.login_person_types', []), true);
    }

    /**
     * @param  array<string, mixed>  $person
     */
    public static function isProtected(array $person): bool
    {
        return in_array(self::of($person), (array) config('intranet.protected_person_types', []), true);
    }

    /**
     * Personen, die diese Person sehen darf (Schüler:innen und Eltern nur mit «view-students»).
     *
     * @param  list<array<string, mixed>>  $people
     * @return list<array<string, mixed>>
     */
    public static function visibleFor(array $people, ?Authenticatable $user): array
    {
        if ($user !== null && Gate::forUser($user)->allows('view-students')) {
            return $people;
        }

        return array_values(array_filter($people, fn (array $p): bool => ! self::isProtected($p)));
    }
}
