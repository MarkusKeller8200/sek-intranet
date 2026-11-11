<?php

namespace Modules\Zeiterfassung;

use InvalidArgumentException;

/**
 * Rechenregeln der Zeiterfassung: Stunden aus Beginn/Ende/Pause, Summen je Kategorie, Monate.
 */
final class Zeitkonto
{
    public const GESPERRT = ['eingereicht', 'visiert', 'abgeschlossen'];

    /**
     * Nicht als Arbeitszeit angerechnete Buchungsarten (#169, wie ClickTime): Kompensation baut Überzeit ab und zählt im
     * Ist nicht – an einem Kompensationstag ist das Ist 0:00 und die Differenz minus Soll. Erkannt an Buchungsart oder
     * Kategorie (auch «ct_…»-Arten aus ClickTime).
     */
    public const NICHT_ANGERECHNET = ['kompensation'];

    /**
     * @param  array<string, mixed>  $eintrag
     */
    public static function angerechnet(array $eintrag): bool
    {
        $text = mb_strtolower(Buchungsarten::von($eintrag).' '.(string) ($eintrag['kategorie'] ?? ''));
        foreach (self::NICHT_ANGERECHNET as $art) {
            if (str_contains($text, $art)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Angerechnete Stunden (Ist) einer Liste von Einträgen.
     *
     * @param  list<array<string, mixed>>  $eintraege
     */
    public static function ist(array $eintraege): float
    {
        return round(array_sum(array_map(fn (array $e): float => self::angerechnet($e) ? (float) ($e['stunden'] ?? 0) : 0.0, $eintraege)), 2);
    }

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
     * @return array{arbeit: float, abwesenheit: float, total: float, kategorien: array<string, float>, projekte: array<string, float>, buchungsarten: array<string, float>, ist: float}
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
        // Ist für Soll/Delta: ohne Kompensation (#169)
        $s['ist'] = self::ist($eintraege);

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

    /** Abweichung Ist/Soll, ab der «Arbeitszeit prüfen» erscheint (#167). */
    public const ABWEICHUNG = 0.2;

    /**
     * Prüfhinweise je Tag (#167):
     * - Arbeitstag mit Sollzeit (aus ClickTime, Soll > 0, bis heute) ohne Eintrag → Hinweis «Prüfen – keine Buchung»
     * - Beginn ohne Ende → Fehler «Prüfen – Austrittsbuchung fehlt», Ende ohne Beginn → «Prüfen – Eintrittsbuchung fehlt»
     * - Ist weicht mehr als 20 % vom Soll ab → Warnung «Arbeitszeit prüfen»
     * - (#204, einstellbar) Tagesist über hh:mm → Warnung; keine Mittagspause im Zeitfenster → Warnung
     * Einträge ohne Zeiten (z. B. Ferien, Stunden direkt) erzeugen keinen Fehler. Welche Hinweise erscheinen, steht in
     * den Prüfregeln (Zeiterfassung → Einstellungen); ohne Angabe gilt die Vorgabe.
     *
     * @param  list<array<string, mixed>>  $eintraege
     * @param  array<string, float>  $soll  Datum → Stunden
     * @param  array<string, mixed>|null  $regeln  siehe Pruefregeln::VORGABE
     * @return array<string, list<array{art: string, text: string, eintraege: list<string>}>> Datum → Hinweise (art: fehler, hinweis, warnung)
     */
    public static function pruefungen(string $monat, array $eintraege, array $soll, ?string $heute = null, ?array $regeln = null): array
    {
        $heute ??= now()->format('Y-m-d');
        $r = $regeln ?? Pruefregeln::VORGABE;
        $max = Pruefregeln::minuten((string) ($r['tagesist_max'] ?? '10:00')) / 60;
        $anteil = ((int) ($r['abweichung_prozent'] ?? 20)) / 100;
        $proTag = [];
        foreach ($eintraege as $e) {
            $proTag[(string) ($e['datum'] ?? '')][] = $e;
        }
        $result = [];
        $ende = (int) date('t', (int) strtotime($monat.'-01'));
        for ($d = 1; $d <= $ende; $d++) {
            $tag = sprintf('%s-%02d', $monat, $d);
            $liste = $proTag[$tag] ?? [];
            $sollTag = $soll[$tag] ?? null;
            $hinweise = [];
            if (($r['keine_buchung'] ?? true) && $liste === [] && $sollTag !== null && $sollTag > 0 && $tag <= $heute) {
                $hinweise[] = ['art' => 'hinweis', 'text' => 'Prüfen – keine Buchung', 'eintraege' => []];
            }
            $ohneEnde = [];
            $ohneBeginn = [];
            foreach ($liste as $e) {
                $beginn = trim((string) ($e['beginn'] ?? ''));
                $endeZeit = trim((string) ($e['ende'] ?? ''));
                if ($beginn !== '' && $endeZeit === '') {
                    $ohneEnde[] = (string) ($e['id'] ?? '');
                } elseif ($beginn === '' && $endeZeit !== '') {
                    $ohneBeginn[] = (string) ($e['id'] ?? '');
                }
            }
            if (($r['gehen_fehlt'] ?? true) && $ohneEnde !== []) {
                $hinweise[] = ['art' => 'fehler', 'text' => 'Prüfen – Austrittsbuchung fehlt', 'eintraege' => $ohneEnde];
            }
            if (($r['kommen_fehlt'] ?? true) && $ohneBeginn !== []) {
                $hinweise[] = ['art' => 'fehler', 'text' => 'Prüfen – Eintrittsbuchung fehlt', 'eintraege' => $ohneBeginn];
            }
            // Kompensation (nicht angerechnet, #169) ist eine gewollte Abweichung: keine Warnung an solchen Tagen
            $mitKompensation = array_filter($liste, fn (array $e): bool => ! self::angerechnet($e)) !== [];
            if (($r['abweichung'] ?? true) && $liste !== [] && ! $mitKompensation && $sollTag !== null && $sollTag > 0) {
                $ist = self::ist($liste);
                if (abs($ist - $sollTag) > $anteil * $sollTag + 0.004) {
                    $hinweise[] = ['art' => 'warnung', 'text' => 'Arbeitszeit prüfen', 'eintraege' => []];
                }
            }
            if (($r['tagesist'] ?? false) && $liste !== [] && self::ist($liste) > $max + 0.004) {
                $hinweise[] = ['art' => 'warnung', 'text' => 'Prüfen – Tagesist über '.self::hhmm($max), 'eintraege' => []];
            }
            if (($r['mittag'] ?? false) && self::ohneMittag($liste, $r)) {
                $hinweise[] = ['art' => 'warnung', 'text' => 'Prüfen – keine Mittagsbuchung', 'eintraege' => []];
            }
            if ($hinweise !== []) {
                $result[$tag] = $hinweise;
            }
        }

        return $result;
    }

    /**
     * Arbeitstag über das Mittagsfenster ohne Pause (#204): gearbeitet wird vor «von» und nach «bis», aber im Fenster
     * gibt es weder eine Lücke zwischen den Buchungen noch eine erfasste Pause von mindestens «mittag_min» Minuten.
     *
     * @param  list<array<string, mixed>>  $liste
     * @param  array<string, mixed>  $r
     */
    private static function ohneMittag(array $liste, array $r): bool
    {
        $von = Pruefregeln::minuten((string) ($r['mittag_von'] ?? '11:30'));
        $bis = Pruefregeln::minuten((string) ($r['mittag_bis'] ?? '13:30'));
        $min = (int) ($r['mittag_min'] ?? 30);
        $bloecke = [];
        foreach ($liste as $e) {
            $b = trim((string) ($e['beginn'] ?? ''));
            $en = trim((string) ($e['ende'] ?? ''));
            if (($e['art'] ?? 'arbeit') !== 'arbeit' || $b === '' || $en === '') {
                continue;
            }
            $a = Pruefregeln::minuten($b);
            $z = Pruefregeln::minuten($en);
            if ($z <= $a) {
                continue;
            }
            // erfasste Pause in einem Block, der ins Fenster reicht, zählt als Mittagsbuchung
            if ($a < $bis && $z > $von && (int) ($e['pause_min'] ?? 0) >= $min) {
                return false;
            }
            $bloecke[] = [$a, $z];
        }
        if ($bloecke === []) {
            return false;
        }
        usort($bloecke, fn (array $x, array $y): int => $x[0] <=> $y[0]);
        if ($bloecke[0][0] >= $von || max(array_column($bloecke, 1)) <= $bis) {
            return false; // nicht über das ganze Fenster gearbeitet
        }
        // Lücken im Fenster suchen
        $bisher = $bloecke[0][1];
        foreach (array_slice($bloecke, 1) as [$a, $z]) {
            $luecke = min($a, $bis) - max($bisher, $von);
            if ($luecke >= $min) {
                return false;
            }
            $bisher = max($bisher, $z);
        }

        return true;
    }

    private static function hhmm(float $stunden): string
    {
        $m = (int) round($stunden * 60);

        return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    }

    public static function gesperrt(string $status): bool
    {
        return in_array($status, self::GESPERRT, true);
    }
}
