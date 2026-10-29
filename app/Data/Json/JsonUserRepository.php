<?php

namespace App\Data\Json;

use App\Data\Contracts\UserRepository;

class JsonUserRepository extends JsonRepository implements UserRepository
{
    protected string $collection = 'users';

    public function findByEmail(string $email): ?array
    {
        $needle = mb_strtolower(trim($email));

        foreach ($this->all() as $user) {
            if (mb_strtolower(self::stringValue($user['email'] ?? '')) === $needle) {
                return $user;
            }
        }

        return null;
    }

    public function active(): array
    {
        return $this->sortBy(
            array_values(array_filter($this->all(), fn (array $user): bool => ($user['active'] ?? true) === true)),
            'name',
        );
    }

    public function names(): array
    {
        $names = [];

        foreach ($this->all() as $user) {
            $names[self::stringValue($user['id'] ?? '')] = self::stringValue($user['name'] ?? '');
        }

        return $names;
    }
}
