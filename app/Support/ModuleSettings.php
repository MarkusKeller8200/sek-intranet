<?php

namespace App\Support;

use App\Data\Store\JsonStore;

/**
 * Einstellungen einzelner Menüpunkte (Menü «Einstellungen», Sammlung module_settings), z. B. bei
 * Fachanwendungen die Adresse und wie sie geöffnet werden. Gespeicherte Werte übersteuern
 * config/intranet.php und die .env; ohne Eintrag gilt die Vorgabe.
 */
final class ModuleSettings
{
    public const COLLECTION = 'module_settings';

    /** embed = iFrame (eingebettet), both = iFrame mit Knopf «Neuer Tab», tab = neuer Browser-Tab, window = neues
     * Browser-Fenster, extension = Browser-Erweiterung (neuer Tab), split = Browser-Erweiterung (gesplittetes Tab) */
    public const OPEN_MODES = ['embed', 'both', 'tab', 'window', 'extension', 'split'];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(private readonly JsonStore $store) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->store->all(self::COLLECTION) as $record) {
                if (is_string($record['key'] ?? null)) {
                    $this->cache[$record['key']] = $record;
                }
            }
        }

        return $this->cache;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Speichert die Einstellung eines Menüpunkts (null = Vorgabe verwenden).
     *
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    public function save(string $key, ?string $url, ?string $open, ?string $userId): array
    {
        $before = $this->get($key);
        $record = [
            'key' => $key,
            'url' => $url !== null && $url !== '' ? $url : null,
            'open' => $open !== null && in_array($open, self::OPEN_MODES, true) ? $open : null,
            'updated_by' => $userId,
        ];
        $after = $before !== null
            ? $this->store->update(self::COLLECTION, (string) $before['id'], $record)
            : $this->store->insert(self::COLLECTION, $record);
        $this->cache = null;

        return ['before' => $before, 'after' => (array) $after];
    }

    /**
     * Zurück zur Vorgabe (Eintrag entfernen).
     *
     * @return array<string, mixed>|null der entfernte Eintrag
     */
    public function reset(string $key): ?array
    {
        $before = $this->get($key);
        if ($before !== null) {
            $this->store->delete(self::COLLECTION, (string) $before['id']);
            $this->cache = null;
        }

        return $before;
    }
}
