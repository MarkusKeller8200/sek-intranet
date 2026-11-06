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

    /** Vorgaben (#151): neues Fenster in % des sichtbaren Anzeigebereichs, Trennlinie «nebeneinander» in % von links */
    public const LAYOUT = ['width' => 80, 'height' => 80, 'split' => 50];

    /** Erlaubte Bereiche je Wert (Prozent) */
    public const LAYOUT_RANGES = ['width' => [20, 100], 'height' => [20, 100], 'split' => [20, 80]];

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
     * Speichert die Einstellung eines Menüpunkts (null = Vorgabe verwenden). Layout: width, height, split in Prozent.
     *
     * @param  array<string, mixed>  $layout
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    public function save(string $key, ?string $url, ?string $open, ?string $userId, array $layout = []): array
    {
        $before = $this->get($key);
        $record = [
            'key' => $key,
            'url' => $url !== null && $url !== '' ? $url : null,
            'open' => $open !== null && in_array($open, self::OPEN_MODES, true) ? $open : null,
            'updated_by' => $userId,
        ];
        foreach (self::LAYOUT_RANGES as $field => [$min, $max]) {
            $value = $layout[$field] ?? null;
            $record[$field] = is_numeric($value) ? max($min, min($max, (int) $value)) : null;
        }
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
