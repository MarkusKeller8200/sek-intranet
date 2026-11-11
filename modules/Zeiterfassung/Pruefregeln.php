<?php

namespace Modules\Zeiterfassung;

use App\Data\Store\JsonStore;
use InvalidArgumentException;

/**
 * Einstellbare Prüfhinweise der Zeiterfassung (#204): welche Hinweise je Tag erscheinen (Meine Zeiterfassung, Visum).
 * Ein Datensatz «pruefungen» in zeit_einstellungen; ohne gespeicherte Einstellung gilt die Vorgabe (wie vor #204).
 */
final class Pruefregeln
{
    public const COLLECTION = 'zeit_einstellungen';

    public const ID = 'pruefungen';

    /**
     * @var array{kommen_fehlt: bool, gehen_fehlt: bool, keine_buchung: bool, tagesist: bool, tagesist_max: string, abweichung: bool, abweichung_prozent: int, mittag: bool, mittag_von: string, mittag_bis: string, mittag_min: int}
     */
    public const VORGABE = [
        'kommen_fehlt' => true,
        'gehen_fehlt' => true,
        'keine_buchung' => true,
        'tagesist' => false,
        'tagesist_max' => '10:00',
        'abweichung' => true,
        'abweichung_prozent' => 20,
        'mittag' => false,
        'mittag_von' => '11:30',
        'mittag_bis' => '13:30',
        'mittag_min' => 30,
    ];

    public function __construct(private readonly JsonStore $store) {}

    /**
     * Gespeicherte Regeln, fehlende Werte aus der Vorgabe.
     *
     * @return array{kommen_fehlt: bool, gehen_fehlt: bool, keine_buchung: bool, tagesist: bool, tagesist_max: string, abweichung: bool, abweichung_prozent: int, mittag: bool, mittag_von: string, mittag_bis: string, mittag_min: int}
     */
    public function regeln(): array
    {
        $gespeichert = $this->store->find(self::COLLECTION, self::ID) ?? [];
        try {
            return self::pruefen(array_intersect_key($gespeichert, self::VORGABE) + self::VORGABE);
        } catch (InvalidArgumentException) {
            return self::VORGABE;
        }
    }

    /**
     * @param  array<string, mixed>  $werte
     * @return array{kommen_fehlt: bool, gehen_fehlt: bool, keine_buchung: bool, tagesist: bool, tagesist_max: string, abweichung: bool, abweichung_prozent: int, mittag: bool, mittag_von: string, mittag_bis: string, mittag_min: int}
     */
    public function speichern(array $werte): array
    {
        $regeln = self::pruefen($werte + self::VORGABE);
        $datensatz = ['id' => self::ID] + $regeln;
        if ($this->store->find(self::COLLECTION, self::ID) !== null) {
            $this->store->update(self::COLLECTION, self::ID, $datensatz);
        } else {
            $this->store->insert(self::COLLECTION, $datensatz);
        }

        return $regeln;
    }

    /**
     * Werte prüfen und in die richtige Form bringen.
     *
     * @param  array<string, mixed>  $w
     * @return array{kommen_fehlt: bool, gehen_fehlt: bool, keine_buchung: bool, tagesist: bool, tagesist_max: string, abweichung: bool, abweichung_prozent: int, mittag: bool, mittag_von: string, mittag_bis: string, mittag_min: int}
     */
    public static function pruefen(array $w): array
    {
        $zeit = function (mixed $wert, string $feld): string {
            $t = trim((string) (is_scalar($wert) ? $wert : ''));
            if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $t, $m) !== 1) {
                throw new InvalidArgumentException("{$feld}: Zeit im Format hh:mm angeben.");
            }

            return sprintf('%02d:%s', (int) $m[1], $m[2]);
        };
        $zahl = function (mixed $wert, int $min, int $max, string $feld): int {
            if (! is_numeric($wert) || (int) $wert < $min || (int) $wert > $max) {
                throw new InvalidArgumentException("{$feld}: Zahl zwischen {$min} und {$max} angeben.");
            }

            return (int) $wert;
        };
        $r = [
            'kommen_fehlt' => (bool) $w['kommen_fehlt'],
            'gehen_fehlt' => (bool) $w['gehen_fehlt'],
            'keine_buchung' => (bool) $w['keine_buchung'],
            'tagesist' => (bool) $w['tagesist'],
            'tagesist_max' => $zeit($w['tagesist_max'], 'Tagesist'),
            'abweichung' => (bool) $w['abweichung'],
            'abweichung_prozent' => $zahl($w['abweichung_prozent'], 1, 100, 'Abweichung'),
            'mittag' => (bool) $w['mittag'],
            'mittag_von' => $zeit($w['mittag_von'], 'Mittag von'),
            'mittag_bis' => $zeit($w['mittag_bis'], 'Mittag bis'),
            'mittag_min' => $zahl($w['mittag_min'], 5, 180, 'Mindestpause'),
        ];
        if ($r['mittag_von'] >= $r['mittag_bis']) {
            throw new InvalidArgumentException('Mittag: «bis» muss nach «von» liegen.');
        }

        return $r;
    }

    /** «hh:mm» → Minuten. */
    public static function minuten(string $zeit): int
    {
        return (int) substr($zeit, 0, 2) * 60 + (int) substr($zeit, 3, 2);
    }
}
