<?php

namespace Modules\Zeiterfassung;

use App\Data\Store\JsonStore;

/**
 * Sollzeit pro Person und Tag (#103), z. B. beim Übernehmen aus ClickTime gespeichert. Ein Datensatz je Person und
 * Monat (id = «Person:Monat»), Stunden dezimal (Anzeige in HH:MM).
 */
final class Sollzeiten
{
    public const COLLECTION = 'zeit_soll';

    public function __construct(private readonly JsonStore $store) {}

    /**
     * Soll pro Tag: Datum «JJJJ-MM-TT» → Stunden; leer, wenn keine Sollzeit gespeichert ist.
     *
     * @return array<string, float>
     */
    public function monat(string $personId, string $monat): array
    {
        $tage = $this->store->find(self::COLLECTION, self::id($personId, $monat))['tage'] ?? [];

        $result = [];
        foreach (is_array($tage) ? $tage : [] as $datum => $h) {
            $result[(string) $datum] = round(is_numeric($h) ? (float) $h : 0.0, 2);
        }

        return $result;
    }

    /**
     * Soll eines Monats ersetzen.
     *
     * @param  array<string, float>  $tage
     */
    public function speichern(string $personId, string $monat, array $tage, ?string $quelle = null): void
    {
        ksort($tage);
        $id = self::id($personId, $monat);
        $daten = ['person_id' => $personId, 'monat' => $monat, 'tage' => array_map(fn (float $h): float => round($h, 2), $tage), 'quelle' => $quelle];
        if ($this->store->find(self::COLLECTION, $id) !== null) {
            $this->store->update(self::COLLECTION, $id, $daten);
        } else {
            $this->store->insert(self::COLLECTION, ['id' => $id] + $daten);
        }
    }

    private static function id(string $personId, string $monat): string
    {
        return $personId.':'.$monat;
    }
}
