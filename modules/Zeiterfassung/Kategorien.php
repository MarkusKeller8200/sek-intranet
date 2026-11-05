<?php

namespace Modules\Zeiterfassung;

/**
 * Kategorien der Zeiterfassung. Lehrpersonen nach den Tätigkeitsfeldern des Berufsauftrags (Kanton Zürich),
 * übrige Mitarbeitende nach Bereich. Abwesenheiten bewusst ohne Grund/Diagnose (IDG).
 */
final class Kategorien
{
    /** @var array<string, string> */
    public const ARBEIT = [
        'unterricht' => 'Unterricht',
        'schueler' => 'Schülerinnen und Schüler',
        'schule' => 'Schule',
        'zusammenarbeit' => 'Zusammenarbeit',
        'weiterbildung' => 'Weiterbildung',
        'verwaltung' => 'Verwaltung',
        'hauswartung' => 'Hauswartung',
        'ict' => 'ICT-Support',
        'projekt' => 'Projekt / Einsatz',
        'andere' => 'Andere Tätigkeit',
    ];

    /** @var array<string, string> */
    public const ABWESENHEIT = [
        'ferien' => 'Ferien',
        'krank' => 'Krankheit / Unfall',
        'weiterbildung_abw' => 'Weiterbildung (abwesend)',
        'kompensation' => 'Kompensation',
        'militaer' => 'Militär / Zivildienst',
        'andere_abw' => 'Andere Abwesenheit',
    ];

    /** @var array<string, string> */
    public const STATUS = [
        'offen' => 'Offen',
        'eingereicht' => 'Eingereicht',
        'visiert' => 'Visiert',
        'zurueck' => 'Zurück an Person',
    ];

    /** Präfix für Kategorien aus ClickTime (#101): «ct:Arbeitszeit» → «Arbeitszeit (clicktime)». */
    public const CLICKTIME = 'ct:';

    public static function label(string $key): string
    {
        if (str_starts_with($key, self::CLICKTIME)) {
            return substr($key, strlen(self::CLICKTIME)).' (clicktime)';
        }

        return self::ARBEIT[$key] ?? self::ABWESENHEIT[$key] ?? $key;
    }

    /**
     * @return array{arbeit: array<string, string>, abwesenheit: array<string, string>, status: array<string, string>}
     */
    public static function all(): array
    {
        return ['arbeit' => self::ARBEIT, 'abwesenheit' => self::ABWESENHEIT, 'status' => self::STATUS];
    }
}
