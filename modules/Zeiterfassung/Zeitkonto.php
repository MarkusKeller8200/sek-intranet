<?php

namespace Modules\Zeiterfassung;

use InvalidArgumentException;

/**
 * Rechenregeln der Zeiterfassung: Stunden aus Beginn/Ende/Pause, Summen je Kategorie, Monate.
 */
final class Zeitkonto
{
    public const GESPERRT = ['eingereicht', 'visiert', 'abgeschlossen'];

    public static function monat(?string $monat): string
    {
        if ($monat === null || $monat === '') {
            return now()->format('Y-m');
        }
        if (preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $monat) !== 1) {
            throw new InvalidArgumentException('Monat im Format JJJJ-MM angeben.');
        }

        return $monat;
    }

    /** Stunden aus «HH:MM»-Zeiten und Pause in Minuten, auf Viertelstunden gerundet (2 Dezimalen). */
    public static function stunden(string $beginn, string $ende, int $pause = 0): float
    {
        $min = fn (string $t): int => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $dauer = $min($ende) - $min($beginn) - $pause;
        if ($dauer <= 0) {
            throw new InvalidArgumentException('Ende muss nach Beginn (abzüglich Pause) liegen.');
        }

        return round($dauer / 60, 2);
    }

    /**
     * Summen je Art und Kategorie.
     *
     * @param  list<array<string, mixed>>  $eintraege
     * @return array{arbeit: float, abwesenheit: float, total: float, kategorien: array<string, float>, projekte: array<string, float>, buchungsarten: array<string, float>}
     */
    public static function summen(array $eintraege): array
    {
        $s = ['arbeit' => 0.0, 'abwesenheit' => 0.0, 'total' => 0.0, 'kategorien' => [], 'projekte' => [], 'buchungsarten' => []];
        foreach ($eintraege as $e) {
            $h = (float) ($e['stunden'] ?? 0);
            $art = ($e['art'] ?? 'arbeit') === 'abwesenheit' ? 'abwesenheit' : 'arbeit';
            $s[$art] += $h;
            $s['total'] += $h;
            $k = (string) ($e['kategorie'] ?? '');
            $s['kategorien'][$k] = round(($s['kategorien'][$k] ?? 0) + $h, 2);
            $b = Buchungsarten::von($e);
            $s['buchungsarten'][$b] = round(($s['buchungsarten'][$b] ?? 0) + $h, 2);
            $p = trim((string) ($e['projekt'] ?? ''));
            if ($p !== '') {
                $s['projekte'][$p] = round(($s['projekte'][$p] ?? 0) + $h, 2);
            }
        }
        foreach (['arbeit', 'abwesenheit', 'total'] as $k) {
            $s[$k] = round($s[$k], 2);
        }

        return $s;
    }

    /**
     * Arbeitstage ohne Eintrag bis heute (#33, Vollständigkeit vor dem Einreichen): mit Sollzeit die Tage mit Soll > 0,
     * sonst Montag bis Freitag ohne Feiertag.
     *
     * @param  list<array<string, mixed>>  $eintraege
     * @param  array<string, string>  $feiertage  Datum → Name
     * @param  array<string, float>  $soll  Datum → Stunden
     * @return list<string> Daten «JJJJ-MM-TT»
     */
    public static function fehlendeTage(string $monat, array $eintraege, array $feiertage, array $soll, ?string $heute = null): array
    {
        $heute ??= now()->format('Y-m-d');
        $mitEintrag = array_flip(array_map(fn (array $e): string => (string) ($e['datum'] ?? ''), $eintraege));
        $fehlend = [];
        $ende = (int) date('t', (int) strtotime($monat.'-01'));
        for ($d = 1; $d <= $ende; $d++) {
            $tag = sprintf('%s-%02d', $monat, $d);
            if ($tag > $heute) {
                break;
            }
            $arbeitstag = $soll !== [] ? ($soll[$tag] ?? 0.0) > 0 : (int) date('N', (int) strtotime($tag)) <= 5 && ! isset($feiertage[$tag]);
            if ($arbeitstag && ! isset($mitEintrag[$tag])) {
                $fehlend[] = $tag;
            }
        }

        return $fehlend;
    }

    public static function gesperrt(string $status): bool
    {
        return in_array($status, self::GESPERRT, true);
    }
}
