<?php

namespace Modules\Ablaeufe\Data\Json;

use App\Data\Store\JsonStore;
use InvalidArgumentException;
use Modules\Ablaeufe\Data\Contracts\ListenRepository;
use Modules\Ablaeufe\Data\Contracts\ListeRepository;
use Modules\Ablaeufe\Listen;

class JsonListenRepository implements ListenRepository
{
    /** @var array<string, ListeRepository> */
    private array $listen = [];

    public function __construct(private readonly JsonStore $store) {}

    public function liste(string $key): ListeRepository
    {
        if (! Listen::gibt($key)) {
            throw new InvalidArgumentException("Tabelle «{$key}» gibt es nicht.");
        }

        return $this->listen[$key] ??= new JsonListeRepository($this->store, $key);
    }
}
