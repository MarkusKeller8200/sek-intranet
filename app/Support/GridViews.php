<?php

namespace App\Support;

use App\Data\Store\JsonStore;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Gespeicherte Tabellenansichten (Sammlung grid_views): persönlich oder für alle.
 */
final class GridViews
{
    public const COLLECTION = 'grid_views';

    public function __construct(private readonly JsonStore $store) {}

    /**
     * Eigene und geteilte Ansichten einer Tabelle, nach Name sortiert.
     *
     * @return list<array<string, mixed>>
     */
    public function for(string $grid, ?Authenticatable $user): array
    {
        $own = $user !== null ? (string) $user->getAuthIdentifier() : null;
        $views = array_values(array_filter(
            $this->store->all(self::COLLECTION),
            fn (array $v): bool => ($v['grid'] ?? null) === $grid && (($v['shared'] ?? false) === true || ($v['owner_id'] ?? null) === $own),
        ));
        usort($views, fn (array $a, array $b): int => strnatcasecmp((string) $a['name'], (string) $b['name']));

        return array_map(fn (array $v): array => $v + ['mine' => ($v['owner_id'] ?? null) === $own], $views);
    }
}
