<?php

namespace Modules\Zeiterfassung;

use InvalidArgumentException;

/**
 * Rechenregeln der Zeiterfassung: Stunden aus Beginn/Ende/Pause, Summen je Kategorie, Monate.
 */
final class Zeitkonto
{
    public const GESPERRT = ['eingereicht', 'visiert'];

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
     * @return array{arbeit: float, abwesenheit: float, total: float, kategorien: array<string, float>, projekte: array<string, float>}
     */
    public static function summen(array $eintraege): array
    {
        $s = ['arbeit' => 0.0, 'abwesenheit' => 0.0, 'total' => 0.0, 'kategorien' => [], 'projekte' => []];
        foreach ($eintraege as $e) {
            $h = (float) ($e['stunden'] ?? 0);
            $art = ($e['art'] ?? 'arbeit') === 'abwesenheit' ? 'abwesenheit' : 'arbeit';
            $s[$art] += $h;
            $s['total'] += $h;
            $k = (string) ($e['kategorie'] ?? '');
            $s['kategorien'][$k] = round(($s['kategorien'][$k] ?? 0) + $h, 2);
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

    public static function gesperrt(string $status): bool
    {
        return in_array($status, self::GESPERRT, true);
    }
}
