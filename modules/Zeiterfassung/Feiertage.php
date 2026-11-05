<?php

namespace Modules\Zeiterfassung;

/**
 * Gesetzliche Feiertage im Kanton Zürich (#102), für die Einfärbung der Tagestotale. Ohne fremdes System, damit die
 * Zeiterfassung auch ohne ClickTime auskommt. Ostern nach der gaussschen Osterformel (gregorianisch).
 */
final class Feiertage
{
    /**
     * Feiertage eines Jahres: Datum «JJJJ-MM-TT» → Name.
     *
     * @return array<string, string>
     */
    public static function jahr(int $jahr): array
    {
        $ostern = self::ostern($jahr);
        $relativ = fn (int $tage): string => date('Y-m-d', $ostern + $tage * 86400);

        $liste = [
            "{$jahr}-01-01" => 'Neujahr',
            "{$jahr}-01-02" => 'Berchtoldstag',
            $relativ(-2) => 'Karfreitag',
            $relativ(1) => 'Ostermontag',
            "{$jahr}-05-01" => 'Tag der Arbeit',
            $relativ(39) => 'Auffahrt',
            $relativ(50) => 'Pfingstmontag',
            "{$jahr}-08-01" => 'Bundesfeier',
            "{$jahr}-12-25" => 'Weihnachten',
            "{$jahr}-12-26" => 'Stephanstag',
        ];
        ksort($liste);

        return $liste;
    }

    /**
     * Feiertage eines Monats «JJJJ-MM».
     *
     * @return array<string, string>
     */
    public static function monat(string $monat): array
    {
        return array_filter(self::jahr((int) substr($monat, 0, 4)), fn (string $datum): bool => str_starts_with($datum, $monat.'-'), ARRAY_FILTER_USE_KEY);
    }

    /** Ostersonntag 12:00 UTC als Zeitstempel (Mittag, damit Tage addiert werden können). */
    private static function ostern(int $jahr): int
    {
        $a = $jahr % 19;
        $b = intdiv($jahr, 100);
        $c = $jahr % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $monat = intdiv($h + $l - 7 * $m + 114, 31);
        $tag = (($h + $l - 7 * $m + 114) % 31) + 1;

        return (int) gmmktime(12, 0, 0, $monat, $tag, $jahr);
    }
}
