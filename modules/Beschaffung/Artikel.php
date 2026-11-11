<?php

namespace Modules\Beschaffung;

/**
 * Felder eines Katalogeintrags (Sammlung bes_artikel) und Prüfung von Werten aus der Tabelle.
 */
final class Artikel
{
    /** Bezeichnungen der Felder (wie module.json → sammlungen). */
    public const FELDER = [
        'bild' => 'Bild',
        'gruppe' => 'Gruppe / Kategorie',
        'bestellnummer' => 'Bestellnummer',
        'bezeichnung' => 'Bezeichnung',
        'modell' => 'Modell / Produkt',
        'beschreibung' => 'Beschreibung',
        'preis' => 'Preis CHF',
        'kosten' => 'Kostenstufe',
        'preis_hinweis' => 'Hinweis zum Preis',
        'zubehoer' => 'Zubehör',
        'standort' => 'Standort / Schulhaus',
        'menge' => 'Menge',
        'zugang' => 'Zugang / Ausleihe',
        'nutzerkreis' => 'Nutzerkreis / Zuweisung',
        'verantwortung' => 'Verantwortung',
        'lizenzen' => 'Lizenzen / Geräte',
        'url' => 'Webseite',
        'wiki' => 'Beschreibung im ICT-Wiki',
        'angaben' => 'Weitere Angaben',
        'status' => 'Status',
        'pruefen' => 'Prüfen',
        'pruef_hinweis' => 'Hinweis zur Prüfung',
    ];

    public const KOSTEN = [
        'kostenlos' => 'kostenlos',
        'niedrig' => 'niedrig',
        'moderat' => 'moderat',
        'hoch' => 'hoch',
        'variabel' => 'variabel',
        'ausstehend' => 'ausstehend',
    ];

    public const STATUS = ['aktiv' => 'aktiv', 'entwurf' => 'Entwurf', 'gestrichen' => 'gestrichen'];

    /** Farben je Wert (Farbkonzept). */
    public const FARBEN = [
        'status' => ['aktiv' => 'emerald', 'entwurf' => 'amber', 'gestrichen' => 'slate'],
        'kosten' => ['kostenlos' => 'emerald', 'niedrig' => 'teal', 'moderat' => 'sky', 'hoch' => 'orange', 'variabel' => 'violet', 'ausstehend' => 'amber'],
    ];

    /** In der Tabelle bearbeitbar (Bild über Hochladen). */
    public const BEARBEITBAR = [
        'gruppe', 'bestellnummer', 'bezeichnung', 'modell', 'beschreibung', 'preis', 'kosten', 'preis_hinweis', 'zubehoer',
        'standort', 'menge', 'zugang', 'nutzerkreis', 'verantwortung', 'lizenzen', 'url', 'wiki', 'angaben', 'status',
        'pruefen', 'pruef_hinweis',
    ];

    private const LANG = ['beschreibung', 'zubehoer', 'angaben', 'pruef_hinweis'];

    /**
     * Prüft einen Wert aus der Tabelle.
     *
     * @return array{0: mixed, 1: string|null} [Wert, Fehlermeldung]
     */
    public static function wert(string $feld, mixed $wert): array
    {
        if (! in_array($feld, self::BEARBEITBAR, true)) {
            return [null, 'Dieses Feld kann nicht bearbeitet werden.'];
        }
        if ($feld === 'pruefen') {
            return [in_array($wert, [true, 1, '1', 'true', 'ja', 'Ja'], true), null];
        }
        $text = is_scalar($wert) ? trim((string) $wert) : '';

        return match ($feld) {
            'bezeichnung' => $text === '' ? [null, 'Bitte eine Bezeichnung eingeben.'] : (mb_strlen($text) > 200 ? [null, 'Bezeichnung ist zu lang (höchstens 200 Zeichen).'] : [$text, null]),
            'preis' => self::preis($text),
            'kosten' => $text === '' || isset(self::KOSTEN[$text]) ? [$text, null] : [null, 'Unbekannte Kostenstufe.'],
            'status' => isset(self::STATUS[$text]) ? [$text, null] : [null, 'Bitte einen Status wählen.'],
            'url', 'wiki' => $text === '' || preg_match('#^https?://\S+$#i', $text) === 1 ? [$text, null] : [null, 'Bitte eine Adresse mit https:// eingeben.'],
            default => mb_strlen($text) > (in_array($feld, self::LANG, true) ? 5000 : 500) ? [null, 'Text ist zu lang.'] : [$text, null],
        };
    }

    /**
     * Preis in CHF: «1'200.50», «1200,5», «CHF 30» → 1200.5; leer = kein Preis.
     *
     * @return array{0: float|null, 1: string|null}
     */
    public static function preis(string $text): array
    {
        $zahl = str_replace(["'", '’', ' ', 'CHF', 'chf', 'Fr.', '.–', '.-'], '', $text);
        $zahl = str_replace(',', '.', $zahl);
        if ($zahl === '') {
            return [null, null];
        }
        if (! is_numeric($zahl) || (float) $zahl < 0 || (float) $zahl > 1_000_000) {
            return [null, 'Bitte einen Preis in CHF eingeben, z. B. 1200.00.'];
        }

        return [round((float) $zahl, 2), null];
    }

    /**
     * Leerer Eintrag für «Neue Zeile».
     *
     * @return array<string, mixed>
     */
    public static function leer(string $katalog, int $position): array
    {
        $eintrag = array_fill_keys(array_keys(self::FELDER), '');

        return ['katalog' => $katalog, 'position' => $position, 'bezeichnung' => 'Neuer Eintrag', 'preis' => null, 'status' => 'entwurf', 'pruefen' => true, 'pruef_hinweis' => 'Neu erfasst – Angaben ergänzen.', 'quelle' => ''] + $eintrag;
    }
}
