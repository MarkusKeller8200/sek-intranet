<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Angemeldete Person, aufgebaut aus einem Datensatz des UserRepository.
 *
 * Ersetzt im Prototyp das Eloquent-Modell User. Später (Datenbank) kann
 * hier ein Eloquent-Modell treten; die Felder bleiben gleich.
 *
 * @implements Arrayable<string, mixed>
 */
final class IntranetUser implements Arrayable, Authenticatable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private readonly array $attributes) {}

    public function id(): string
    {
        return $this->string('id');
    }

    public function name(): string
    {
        return $this->string('name');
    }

    public function email(): string
    {
        return $this->string('email');
    }

    public function role(): string
    {
        return $this->string('role');
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    public function isActive(): bool
    {
        return ($this->attributes['active'] ?? true) === true;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * Keine Passwörter im Prototyp (später OIDC).
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // «Angemeldet bleiben» wird im Prototyp nicht unterstützt.
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Für die Oberfläche freigegebene Felder.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'email' => $this->email(),
            'role' => $this->role(),
            'function' => $this->attributes['function'] ?? null,
            'school' => $this->attributes['school'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function string(string $key): string
    {
        $value = $this->attributes[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
