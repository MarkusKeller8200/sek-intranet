<?php

namespace Modules\Zeiterfassung;

use App\Support\SimplePdf;

/**
 * Monatsrapport als PDF auf dem Server (#43): Einträge, Summen und unten zwei Unterschriftsfelder
 * (Mitarbeiter:in, Schulleitung). Die Positionen der Felder gehen an den Anbieter (DeepSign: autographPosition).
 */
final class MonatsblattPdf
{
    private const LEFT = 42.0;

    private const RIGHT = 553.0;

    private const BOTTOM = 790.0;

    /** Höhe des Unterschriftsbereichs auf der letzten Seite. */
    private const SIGN_HEIGHT = 130.0;

    /**
     * @param  list<array<string, mixed>>  $eintraege
     * @param  array{arbeit: float, abwesenheit: float, total: float, kategorien: array<string, float>, projekte: array<string, float>}  $summen
     * @param  array{person: string, schulleitung: string}  $unterzeichnende
     * @return array{pdf: string, felder: array{person: array{pageNumber: int, x: float, y: float, width: float, height: float}, schulleitung: array{pageNumber: int, x: float, y: float, width: float, height: float}}}
     */
    public static function erstellen(string $person, string $monat, array $eintraege, array $summen, ?string $visiertVon, ?string $visiertAm, array $unterzeichnende): array
    {
        $titel = 'Monatsrapport '.self::monatName($monat);
        $pdf = new SimplePdf("{$titel} – {$person}");
        $y = self::kopf($pdf, $titel, $person, $visiertVon, $visiertAm);

        // Spalten: Datum, Art, Kategorie, Zeit, Pause, Stunden, Projekt
        $spalten = [[self::LEFT, 'Datum'], [100, 'Art'], [160, 'Kategorie'], [300, 'Zeit'], [370, 'Pause'], [410, 'Stunden'], [462, 'Projekt']];
        $y = self::tabellenkopf($pdf, $spalten, $y);
        foreach ($eintraege as $e) {
            if ($y > self::BOTTOM - 14) {
                $pdf->addPage();
                $y = self::tabellenkopf($pdf, $spalten, 50.0);
            }
            $zeit = trim((string) ($e['beginn'] ?? '').(isset($e['ende']) && $e['ende'] !== '' ? '–'.$e['ende'] : ''));
            $pdf->text(self::LEFT, $y, self::datum((string) $e['datum']), 8.5);
            $pdf->text(100, $y, ($e['art'] ?? '') === 'abwesenheit' ? 'Abwesenheit' : 'Arbeit', 8.5);
            $pdf->text(160, $y, SimplePdf::fit(Kategorien::label((string) $e['kategorie']), 135, 8.5), 8.5);
            $pdf->text(300, $y, $zeit, 8.5);
            $pdf->textRight(395, $y, isset($e['pause_min']) ? (string) $e['pause_min'] : '', 8.5);
            $pdf->textRight(450, $y, number_format((float) $e['stunden'], 2, '.', "'"), 8.5);
            $pdf->text(462, $y, SimplePdf::fit((string) ($e['projekt'] ?? ''), self::RIGHT - 462, 8.5), 8.5);
            $y += 13;
        }
        if ($eintraege === []) {
            $pdf->text(self::LEFT, $y, 'Keine Einträge in diesem Monat.', 9);
            $y += 13;
        }

        // Summen
        if ($y > self::BOTTOM - 70) {
            $pdf->addPage();
            $y = 50.0;
        }
        $pdf->line(self::LEFT, $y - 4, self::RIGHT, $y - 4);
        $y += 10;
        foreach (['Arbeit' => $summen['arbeit'], 'Abwesenheit' => $summen['abwesenheit'], 'Total' => $summen['total']] as $label => $h) {
            $pdf->text(300, $y, $label, 9, $label === 'Total');
            $pdf->textRight(450, $y, number_format($h, 2, '.', "'").' h', 9, $label === 'Total');
            $y += 13;
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

        return ['pageNumber' => $pdf->page(), 'x' => $x, 'y' => $top + 6, 'width' => $breite, 'height' => $hoehe];
    }

    private static function kopf(SimplePdf $pdf, string $titel, string $person, ?string $visiertVon, ?string $visiertAm): float
    {
        $pdf->text(self::LEFT, 50, 'Sekundarschule Bülach', 9);
        $pdf->text(self::LEFT, 74, $titel, 16, true);
        $pdf->text(self::LEFT, 94, 'Mitarbeiter:in: '.$person, 10);
        if ($visiertVon !== null && $visiertVon !== '') {
            $pdf->text(self::LEFT, 108, 'Visiert von '.$visiertVon.($visiertAm !== null ? ' am '.self::datum($visiertAm) : ''), 9);
        }

        return 134.0;
    }

    /**
     * @param  list<array{0: float|int, 1: string}>  $spalten
     */
    private static function tabellenkopf(SimplePdf $pdf, array $spalten, float $y): float
    {
        foreach ($spalten as [$x, $label]) {
            $pdf->text((float) $x, $y, $label, 8.5, true);
        }
        $pdf->line(self::LEFT, $y + 4, self::RIGHT, $y + 4);

        return $y + 16;
    }

    private static function datum(string $iso): string
    {
        $t = strtotime($iso);

        return $t === false ? $iso : date('d.m.Y', $t);
    }

    public static function monatName(string $monat): string
    {
        $namen = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        [$jahr, $m] = array_map('intval', explode('-', $monat) + [1 => '1']);

        return ($namen[$m - 1] ?? $monat).' '.$jahr;
    }
}
