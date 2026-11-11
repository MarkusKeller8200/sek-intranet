<?php

namespace Modules\Zeiterfassung;

use App\Data\Store\JsonStore;

/**
 * Vorgesetzte je Person (#208): unterschreiben den Monatsrapport als zweite Person. Übernommen aus ClickTime
 * (Vorgesetzte der Abteilung) beim «Aus ClickTime übernehmen»; unter Zeiterfassung → Einstellungen von Hand
 * übersteuerbar. Von Hand gesetzte Angaben überschreibt der Import nicht.
 */
final class Vorgesetzte
{
    public const COLLECTION = 'zeit_vorgesetzte';

    public const HAND = 'hand';

    public function __construct(private readonly JsonStore $store) {}

    /**
     * @return array{vorgesetzte: list<string>, quelle: string|null}
     */
    public function fuer(string $personId): array
    {
        $r = $this->store->find(self::COLLECTION, $personId);

        return ['vorgesetzte' => self::ids($r['vorgesetzte'] ?? []), 'quelle' => isset($r['quelle']) ? (string) $r['quelle'] : null];
    }

    /**
     * Alle gespeicherten Zuordnungen: Person → Angaben.
     *
     * @return array<string, array{vorgesetzte: list<string>, quelle: string|null}>
     */
    public function alle(): array
    {
        $result = [];
        foreach ($this->store->all(self::COLLECTION) as $r) {
            $result[(string) $r['id']] = ['vorgesetzte' => self::ids($r['vorgesetzte'] ?? []), 'quelle' => isset($r['quelle']) ? (string) $r['quelle'] : null];
        }

        return $result;
    }

    /**
     * Aus einem System (z. B. ClickTime) übernehmen; von Hand gesetzte Angaben bleiben. Die Person selbst wird nie
     * als eigene vorgesetzte Person eingetragen.
     *
     * @param  list<string>  $ids
     */
    public function ausQuelle(string $personId, array $ids, string $quelle): bool
    {
        if ($this->fuer($personId)['quelle'] === self::HAND) {
            return false;
        }
        $ids = array_values(array_filter(self::ids($ids), fn (string $id): bool => $id !== $personId));
        if ($ids === []) {
            return false;
        }
        $this->setzen($personId, $ids, $quelle);

        return true;
    }

    /**
     * Von Hand setzen; leere Liste = Zuordnung entfernen (danach gilt wieder ClickTime).
     *
     * @param  list<string>  $ids
     */
    public function vonHand(string $personId, array $ids): void
    {
        $ids = array_values(array_filter(self::ids($ids), fn (string $id): bool => $id !== $personId));
        if ($ids === []) {
            if ($this->store->find(self::COLLECTION, $personId) !== null) {
                $this->store->update(self::COLLECTION, $personId, ['vorgesetzte' => [], 'quelle' => null]);
            }

            return;
        }
        $this->setzen($personId, $ids, self::HAND);
    }

    /**
     * @param  list<string>  $ids
     */
    private function setzen(string $personId, array $ids, string $quelle): void
    {
        $daten = ['vorgesetzte' => $ids, 'quelle' => $quelle];
        if ($this->store->find(self::COLLECTION, $personId) !== null) {
            $this->store->update(self::COLLECTION, $personId, $daten);
        } else {
            $this->store->insert(self::COLLECTION, ['id' => $personId] + $daten);
        }
    }

    /**
     * @return list<string>
     */
    private static function ids(mixed $werte): array
    {
        return array_values(array_unique(array_filter(array_map(fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '', is_array($werte) ? $werte : []))));
    }
}
