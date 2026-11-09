<?php

namespace Modules\Zeiterfassung;

use App\Support\SimplePdf;

/**
 * Monatsrapport als PDF auf dem Server (#43, #34), aufgebaut wie der Monatsrapport aus ClickTime (#168):
 *
 * - Kopf: «Monatsrapport <Monat>», darunter Personalnummer und Name.
 * - Tabelle Tag | Von | Bis | Std. | Soll | Diff. | Typ | Bemerkungen: je Buchung eine Zeile (Pause als eigene Zeile
 *   mit negativer Zeit, Arbeitsblock brutto), darunter das Tagestotal mit Soll und Differenz; Montage grau hinterlegt,
 *   Typ mit Farbfeld. Arbeitstage mit Sollzeit ohne Buchung erscheinen mit «keine Buchung».
 * - Total, Stunden je Buchungsart, Saldi (z. B. aus ClickTime: Vormonat und Ende Monat), Bestätigungen.
 * - Optional zwei Unterschriftsfelder (Mitarbeiter:in, Schulleitung) für DeepSign; die Positionen gehen an den
 *   Anbieter (Punkte ab links unten, wie PDF).
 * - Fusszeile «<Datum> (Seite n / m)».
 */
final class MonatsblattPdf
{
    private const LEFT = 42.0;

    private const RIGHT = 553.0;

    private const BOTTOM = 790.0;

    /** Höhe des Unterschriftsbereichs auf der letzten Seite. */
    private const SIGN_HEIGHT = 130.0;

    private const ZEILE = 11.0;

    private const SCHRIFT = 8.0;

    /** Spalten: Tag (links), Datum/Von/Bis/Std./Soll/Diff. (rechtsbündig bis), Typ und Bemerkungen (links). */
    private const X = ['tag' => 44.0, 'datum' => 98.0, 'von' => 132.0, 'bis' => 162.0, 'std' => 198.0, 'soll' => 240.0, 'diff' => 276.0, 'typ' => 286.0, 'bem' => 362.0];

    /** Senkrechte Linien zwischen den Spaltengruppen. */
    private const TRENNER = [102.0, 280.0, 357.0];

    private const GRAU = [0.9, 0.9, 0.9];

    /**
     * @param  list<array<string, mixed>>  $eintraege
     * @param  array{arbeit: float, abwesenheit: float, total: float, kategorien: array<string, float>, projekte: array<string, float>, buchungsarten?: array<string, float>}  $summen
     * @param  array{person: string, schulleitung: string}  $unterzeichnende
     * @param  array<string, string>  $buchungsarten  Schlüssel → Bezeichnung (#99)
     * @param  array{vermerke?: list<string>, unterschriftsfelder?: bool, soll?: array<string, float>, untertitel?: string, salden?: list<array{name: string, vormonat: float|null, ende: float|null, einheit: string}>, heute?: string}  $optionen
     * @return array{pdf: string, felder: array<string, array{pageNumber: int, x: float, y: float, width: float, height: float}>}
     */
    public static function erstellen(string $person, string $monat, array $eintraege, array $summen, ?string $visiertVon, ?string $visiertAm, array $unterzeichnende, array $buchungsarten = [], array $optionen = []): array
    {
        $buchungsarten += array_map(fn (array $b): string => $b['name'], Buchungsarten::VORGABE);
        $soll = $optionen['soll'] ?? [];
        $heute = $optionen['heute'] ?? date('Y-m-d');
        $titel = 'Monatsrapport '.self::monatName($monat);
        $pdf = new SimplePdf("{$titel} – {$person}");
        $pdf->footerRight(self::datumLang($heute).' (Seite {seite} / {seiten})', self::RIGHT);

        $pdf->text(self::LEFT, 50, $titel, 15, true);
        $pdf->text(self::LEFT, 66, $optionen['untertitel'] ?? $person, 10);
        $oben = 82.0;
        $y = self::tabellenkopf($pdf, $oben);

        // Tage mit Buchungen oder Sollzeit (bis heute)
        $proTag = [];
        foreach ($eintraege as $e) {
            $proTag[(string) $e['datum']][] = $e;
        }
        $ende = (int) date('t', (int) strtotime($monat.'-01'));
        $istTotal = 0.0;
        $sollTotal = 0.0;
        for ($d = 1; $d <= $ende; $d++) {
            $tag = sprintf('%s-%02d', $monat, $d);
            $liste = $proTag[$tag] ?? [];
            $sollTag = $soll[$tag] ?? null;
            if ($liste === [] && ! ($sollTag !== null && $sollTag > 0 && $tag <= $heute)) {
                continue;
            }
            usort($liste, fn (array $a, array $b): int => strcmp((string) ($a['beginn'] ?? ''), (string) ($b['beginn'] ?? '')));
            $zeilen = self::zeilen($liste, $buchungsarten);
            $ist = array_sum(array_map(fn (array $e): float => (float) ($e['stunden'] ?? 0), $liste));
            $istTotal += $ist;
            $sollTotal += (float) ($sollTag ?? 0);

            $hoehe = (count($zeilen) + 1) * self::ZEILE + 3;
            if ($y + $hoehe > self::BOTTOM) {
                self::trenner($pdf, $oben, $y);
                $pdf->addPage();
                $oben = 50.0;
                $y = self::tabellenkopf($pdf, $oben);
            }
            // Wochenbeginn grau hinterlegt
            if ((int) date('N', (int) strtotime($tag)) === 1) {
                $pdf->fill(self::LEFT, $y - 8, self::RIGHT - self::LEFT, $hoehe, self::GRAU);
            }
            $pdf->text(self::X['tag'], $y, self::wochentag($tag), self::SCHRIFT);
            $pdf->textRight(self::X['datum'], $y, (int) substr($tag, 8).'.'.substr($tag, 5, 2), self::SCHRIFT);
            foreach ($zeilen as $z) {
                $pdf->textRight(self::X['von'], $y, $z['von'], self::SCHRIFT);
                $pdf->textRight(self::X['bis'], $y, $z['bis'], self::SCHRIFT);
                $pdf->textRight(self::X['std'], $y, $z['std'], self::SCHRIFT);
                if ($z['typ'] !== '') {
                    $pdf->fill(self::X['typ'], $y - 6, 6, 6, self::farbe($z['schluessel'], $z['typ']));
                    $pdf->text(self::X['typ'] + 9, $y, SimplePdf::fit($z['typ'], 64, self::SCHRIFT), self::SCHRIFT);
                }
                $pdf->text(self::X['bem'], $y, SimplePdf::fit($z['bem'], self::RIGHT - self::X['bem'] - 2, self::SCHRIFT), self::SCHRIFT);
                $y += self::ZEILE;
            }
            // Tagestotal
            $pdf->dotted(self::X['std'] - 30, $y - 8, self::X['std'], $y - 8);
            $pdf->textRight(self::X['std'], $y, self::hm($ist), self::SCHRIFT);
            if ($sollTag !== null) {
                $pdf->textRight(self::X['soll'], $y, self::hm($sollTag), self::SCHRIFT);
                $pdf->textRight(self::X['diff'], $y, self::hm($ist - $sollTag), self::SCHRIFT);
            }
            $y += self::ZEILE + 3;
        }
        if ($eintraege === [] && $soll === []) {
            $pdf->text(self::X['von'] - 25, $y, 'Keine Einträge in diesem Monat.', self::SCHRIFT);
            $y += self::ZEILE;
        }
        self::trenner($pdf, $oben, $y);
        $pdf->line(self::LEFT, $y - 8, self::RIGHT, $y - 8);

        // Total
        if ($y + 30 > self::BOTTOM) {
            $pdf->addPage();
            $y = self::tabellenkopf($pdf, 50.0);
        }
        $pdf->rect(self::LEFT, $y - 8, self::RIGHT - self::LEFT, 14);
        $pdf->text(self::X['tag'], $y + 2, 'Total', self::SCHRIFT, true);
        $pdf->textRight(self::X['std'], $y + 2, self::hm($istTotal), self::SCHRIFT, true);
        if ($soll !== []) {
            $pdf->textRight(self::X['soll'], $y + 2, self::hm($sollTotal), self::SCHRIFT, true);
            $pdf->textRight(self::X['diff'], $y + 2, self::hm($istTotal - $sollTotal), self::SCHRIFT, true);
        }
        $y += 30;

        // Stunden je Buchungsart
        $arten = [];
        foreach ($summen['buchungsarten'] ?? [] as $b => $h) {
            $arten[] = [$buchungsarten[$b] ?? (string) $b, self::hm((float) $h)];
        }
        $arten[] = ['Arbeit', self::hm($summen['arbeit'])];
        $arten[] = ['Abwesenheit', self::hm($summen['abwesenheit'])];
        $y = self::kleineTabelle($pdf, $y, self::monatName($monat), ['Buchungsart', 'Stunden'], $arten);

        // Saldi (z. B. aus ClickTime)
        $salden = $optionen['salden'] ?? [];
        if ($salden !== []) {
            $zeilen = [];
            foreach ($salden as $s) {
                $wert = fn (?float $v): string => $v === null ? '' : ($s['einheit'] === 'h' ? self::hm($v) : number_format($v, 2, '.', "'").' '.$s['einheit']);
                $zeilen[] = [$s['name'], $wert($s['vormonat']), $wert($s['ende'])];
            }
            $y = self::kleineTabelle($pdf, $y, 'Saldi', ['Saldo', 'Vormonat', 'Ende Monat'], $zeilen);
        }

        // Bestätigungen
        $vermerke = $optionen['vermerke'] ?? [];
        if ($visiertVon !== null && $visiertVon !== '') {
            $vermerke[] = 'Visiert von '.$visiertVon.($visiertAm !== null ? ' am '.self::datum($visiertAm) : '');
        }
        if ($vermerke !== []) {
            if ($y + 14 * (count($vermerke) + 1) > self::BOTTOM) {
                $pdf->addPage();
                $y = 50.0;
            }
            $pdf->text(self::LEFT, $y, 'Bestätigungen', 9, true);
            $y += 13;
            foreach ($vermerke as $vermerk) {
                $pdf->text(self::LEFT, $y, SimplePdf::fit($vermerk, self::RIGHT - self::LEFT, 8.5), 8.5);
                $y += 12;
            }
        }

        if (($optionen['unterschriftsfelder'] ?? true) === false) {
            return ['pdf' => $pdf->output(), 'felder' => []];
        }

        // Unterschriften auf der letzten Seite unten
        if ($y > self::BOTTOM - self::SIGN_HEIGHT) {
            $pdf->addPage();
        }
        $top = self::BOTTOM - self::SIGN_HEIGHT + 20;
        $pdf->text(self::LEFT, $top - 6, 'Unterschriften', 10, true);
        $felder = [
            'person' => self::feld($pdf, self::LEFT, $top, 'Mitarbeiter:in', $unterzeichnende['person']),
            'schulleitung' => self::feld($pdf, self::RIGHT - 240, $top, 'Schulleitung', $unterzeichnende['schulleitung']),
        ];
        $pdf->text(self::LEFT, $top + 98, 'Elektronisch unterschrieben über das Intranet SekBülach (DeepSign).', 7.5);

        return ['pdf' => $pdf->output(), 'felder' => $felder];
    }

    /**
     * Zeilen eines Tages: Pause als eigene Zeile (negativ), Arbeitsblock brutto; ohne Buchung «keine Buchung».
     *
     * @param  list<array<string, mixed>>  $liste
     * @param  array<string, string>  $buchungsarten
     * @return list<array{von: string, bis: string, std: string, typ: string, schluessel: string, bem: string}>
     */
    private static function zeilen(array $liste, array $buchungsarten): array
    {
        if ($liste === []) {
            return [['von' => '-', 'bis' => '-', 'std' => '', 'typ' => '', 'schluessel' => '', 'bem' => 'keine Buchung']];
        }
        $zeilen = [];
        foreach ($liste as $e) {
            $pause = (int) ($e['pause_min'] ?? 0);
            $b = Buchungsarten::von($e);
            if ($pause > 0) {
                $zeilen[] = ['von' => '-', 'bis' => '-', 'std' => self::hm(-$pause / 60), 'typ' => 'Pause', 'schluessel' => 'pause', 'bem' => ''];
            }
            $notiz = trim(implode(' · ', array_filter([(string) ($e['projekt'] ?? ''), (string) ($e['notiz'] ?? '')])));
            $zeilen[] = [
                'von' => self::uhr($e['beginn'] ?? null),
                'bis' => self::uhr($e['ende'] ?? null),
                'std' => self::hm((float) ($e['stunden'] ?? 0) + $pause / 60),
                'typ' => $buchungsarten[$b] ?? $b,
                'schluessel' => $b,
                'bem' => $notiz,
            ];
        }

        return $zeilen;
    }

    private static function tabellenkopf(SimplePdf $pdf, float $oben): float
    {
        $pdf->rect(self::LEFT, $oben, self::RIGHT - self::LEFT, 14);
        $y = $oben + 10;
        $pdf->text(self::X['tag'], $y, 'Tag', self::SCHRIFT, true);
        foreach (['von' => 'Von', 'bis' => 'Bis', 'std' => 'Std.', 'soll' => 'Soll', 'diff' => 'Diff.'] as $k => $label) {
            $pdf->textRight(self::X[$k], $y, $label, self::SCHRIFT, true);
        }
        $pdf->text(self::X['typ'] + 9, $y, 'Typ', self::SCHRIFT, true);
        $pdf->text(self::X['bem'], $y, 'Bemerkungen', self::SCHRIFT, true);

        return $oben + 26;
    }

    /** Senkrechte Linien und Rahmen links/rechts der Tabelle auf der aktuellen Seite. */
    private static function trenner(SimplePdf $pdf, float $oben, float $y): void
    {
        foreach ([self::LEFT, ...self::TRENNER, self::RIGHT] as $x) {
            $pdf->line($x, $oben, $x, $y - 8, 0.4);
        }
    }

    /**
     * Kleine Tabelle mit Titel, Kopf und Zeilen (erste Spalte links, übrige rechtsbündig).
     *
     * @param  list<string>  $kopf
     * @param  list<list<string>>  $zeilen
     */
    private static function kleineTabelle(SimplePdf $pdf, float $y, string $titel, array $kopf, array $zeilen): float
    {
        $hoehe = 14.0 * (count($zeilen) + 2) + 16;
        if ($y + $hoehe > self::BOTTOM) {
            $pdf->addPage();
            $y = 50.0;
        }
        $breite = [150.0, 70.0, 70.0];
        $x = self::LEFT;
        $pdf->text($x, $y, $titel, 9, true);
        $y += 6;
        $oben = $y;
        $spalten = count($kopf);
        $gesamt = array_sum(array_slice($breite, 0, $spalten));
        foreach ([$kopf, ...$zeilen] as $i => $zeile) {
            $cx = $x;
            foreach ($zeile as $j => $wert) {
                $w = $breite[$j] ?? 70.0;
                if ($j === 0) {
                    $pdf->text($cx + 3, $y + 10, SimplePdf::fit($wert, $w - 6, self::SCHRIFT), self::SCHRIFT, $i === 0);
                } else {
                    $pdf->textRight($cx + $w - 4, $y + 10, $wert, self::SCHRIFT, $i === 0);
                }
                $cx += $w;
            }
            $y += 14;
            $pdf->line($x, $y, $x + $gesamt, $y, 0.3);
        }
        $pdf->rect($x, $oben, $gesamt, $y - $oben, 0.4);
        $cx = $x;
        for ($j = 0; $j < $spalten - 1; $j++) {
            $cx += $breite[$j];
            $pdf->line($cx, $oben, $cx, $y, 0.3);
        }

        return $y + 18;
    }

    /**
     * Farbfeld des Typs (wie ClickTime): Arbeit hellgrün, Pause grün, Ferien hellgelb, Kompensation beige,
     * Krankheit/Unfall hellrot, übrige grau.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function farbe(string $schluessel, string $name): array
    {
        $text = mb_strtolower($schluessel.' '.$name);

        return match (true) {
            str_contains($text, 'pause') => [0.42, 0.72, 0.33],
            str_contains($text, 'krank'), str_contains($text, 'unfall') => [0.96, 0.75, 0.75],
            str_contains($text, 'ferien') => [0.98, 0.94, 0.68],
            str_contains($text, 'kompensation') => [0.95, 0.83, 0.68],
            str_contains($text, 'arbeit'), str_contains($text, 'homeoffice') => [0.80, 0.91, 0.74],
            default => [0.8, 0.8, 0.8],
        };
    }

    /**
     * Rahmen für eine Unterschrift mit Name darunter; liefert die Position für den Anbieter.
     *
     * @return array{pageNumber: int, x: float, y: float, width: float, height: float}
     */
    private static function feld(SimplePdf $pdf, float $x, float $top, string $rolle, string $name): array
    {
        $breite = 240.0;
        $hoehe = 60.0;
        $pdf->rect($x, $top + 6, $breite, $hoehe, 0.4);
        $pdf->text($x, $top + $hoehe + 20, $rolle.': '.SimplePdf::fit($name, $breite - 70, 9), 9);

        // DeepSign misst ab der linken unteren Ecke der Seite (Punkte), SimplePdf ab oben
        return ['pageNumber' => $pdf->page(), 'x' => $x, 'y' => round(SimplePdf::HEIGHT - ($top + 6) - $hoehe, 2), 'width' => $breite, 'height' => $hoehe];
    }

    /** Stunden als «H:MM», negativ mit «-» (wie ClickTime), z. B. 10.2167 → «10:13». */
    public static function hm(float $h): string
    {
        $min = (int) round(abs($h) * 60);

        return ($h < 0 && $min > 0 ? '-' : '').intdiv($min, 60).':'.str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT);
    }

    /** Uhrzeit «07:35» → «7:35», leer → «-». */
    private static function uhr(mixed $zeit): string
    {
        $zeit = is_string($zeit) ? trim($zeit) : '';

        return $zeit === '' ? '-' : ltrim(substr($zeit, 0, 5), '0');
    }

    private static function wochentag(string $tag): string
    {
        return ['Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.', 'So.'][(int) date('N', (int) strtotime($tag)) - 1];
    }

    private static function datum(string $iso): string
    {
        $t = strtotime($iso);

        return $t === false ? $iso : date('d.m.Y', $t);
    }

    /** «2026-11-09» → «9. Nov. 2026» */
    private static function datumLang(string $iso): string
    {
        $t = strtotime($iso);
        if ($t === false) {
            return $iso;
        }
        $monate = ['Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sept.', 'Okt.', 'Nov.', 'Dez.'];

        return date('j', $t).'. '.$monate[(int) date('n', $t) - 1].' '.date('Y', $t);
    }

    public static function monatName(string $monat): string
    {
        $namen = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        [$jahr, $m] = array_map('intval', explode('-', $monat) + [1 => '1']);

        return ($namen[$m - 1] ?? $monat).' '.$jahr;
    }
}
