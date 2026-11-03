<?php

namespace Modules\Ablaeufe\Data\Contracts;

/**
 * Zugriff auf die Tabellen der Einstellungen nach Schlüssel (siehe Modules\Ablaeufe\Listen).
 */
interface ListenRepository
{
    public function liste(string $key): ListeRepository;
}
