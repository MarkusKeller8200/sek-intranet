<?php

namespace App\Auth;

use App\Data\Contracts\UserRepository;
use App\Support\PersonTypes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Laravel-UserProvider auf Basis des UserRepository.
 *
 * Funktioniert unabhängig davon, ob die Personen in JSON-Dateien oder
 * später in PostgreSQL liegen. Passwörter gibt es nicht: Die Anmeldung
 * erfolgt im Prototyp über die Demo-Auswahl, später über OIDC.
 */
final class RepositoryUserProvider implements UserProvider
{
    public function __construct(private readonly UserRepository $users) {}

    public function retrieveById($identifier): ?IntranetUser
    {
        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        $record = $this->users->find($identifier);

        return $this->toUser($record);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?IntranetUser
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        //
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?IntranetUser
    {
        $email = $credentials['email'] ?? null;

        return is_string($email) ? $this->toUser($this->users->findByEmail($email)) : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        // Keine Passwortprüfung im Prototyp.
        return false;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        //
    }

    /**
     * @param  array<string, mixed>|null  $record
     */
    private function toUser(?array $record): ?IntranetUser
    {
        if ($record === null) {
            return null;
        }

        $user = new IntranetUser($record);

        // Schüler:innen, Eltern und «Kein Zugang» haben kein Konto im Intranet
        return $user->isActive() && PersonTypes::canLogin($record) ? $user : null;
    }
}
