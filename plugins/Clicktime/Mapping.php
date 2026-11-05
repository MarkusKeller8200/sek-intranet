<?php

namespace Plugins\Clicktime;

/**
 * Mapping-Tabelle Buchungsart ClickTime ↔ Buchungsart der Zeiterfassung (#100). Entkoppelt: die Zeiterfassung kennt
 * ClickTime nicht. Eine Zeile je ClickTime-Buchungsart, «Name = Schlüssel» (Einstellung «mapping»):
 *
 * - Name: deutscher Name der Buchungsart in ClickTime (entry_type_name.de), alternativ die Nummer, falls gesetzt
 * - Schlüssel: Buchungsart der Zeiterfassung, «pause» (Pause des Tages, negative Stunden) oder «-» (nicht übernehmen,
 *   z. B. Sollzeit, Überzeit)
 *
 * Mehrere ClickTime-Arten dürfen auf dieselbe Buchungsart zeigen. Gross-/Kleinschreibung und Leerzeichen zählen nicht.
 */
final class Mapping
{
    public const PAUSE = 'pause';

    public const SKIP = '-';

    /** Vorgabe nach den Buchungsarten der Schule (Kumulative, 05.11.2026). */
    public const DEFAULT = "Arbeitszeit = arbeitszeit\nHomeoffice = homeoffice\nFerien = ferien\nKrankheit = krankheit\nUnfall = unfall\n"
        ."Kompensation = kompensation\nKompensation (Minussaldo) = kompensation\nUeberzeit-Kompensation = kompensation\n"
        ."Weiterbildung = weiterbildung\nMilitär/Zivilschutz = militaer\nBezahlte Absenzen = bezahlt\nunbezahlte Absenz = unbezahlt\n"
        ."Pause = pause\nSollzeit = -\nUeberzeit = -\nÜberzeit abgerechnet = -";

    /** ClickTime-Kategorie (entry_type_type) → Vorschlag. */
    private const CATEGORY = ['WORK' => 'arbeitszeit', 'HOLIDAYS' => 'ferien', 'COMPENSATION' => 'kompensation'];

    /**
     * @return array<string, array{name: string, target: string}> normalisierter Name → Zeile
     */
    public static function parse(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            $pos = (int) strrpos($line, '=');
            $name = trim(substr($line, 0, $pos));
            $target = trim(substr($line, $pos + 1));
            if ($name !== '' && $target !== '') {
                $rows[self::key($name)] = ['name' => $name, 'target' => $target];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, array{name: string, target: string}>  $rows
     */
    public static function format(array $rows): string
    {
        return implode("\n", array_map(fn (array $r): string => $r['name'].' = '.$r['target'], array_values($rows)));
    }

    public static function key(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim($name)));
    }

    /**
     * Ziel einer ClickTime-Buchung: über die Nummer (falls gesetzt), sonst den Namen. null = nicht zugeordnet.
     *
     * @param  array<string, array{name: string, target: string}>  $mapping
     */
    public static function target(array $mapping, string $type, string $name): ?string
    {
        return $mapping[self::key($type)]['target'] ?? $mapping[self::key($name)]['target'] ?? null;
    }

    /**
     * Vorschlag für eine ClickTime-Buchungsart: gleicher Name in der Zeiterfassung, sonst nach Kategorie.
     *
     * @param  array<string, array{name: string, art: string, aktiv: bool}>  $buchungsarten
     */
    public static function suggest(string $name, ?string $category, array $buchungsarten): ?string
    {
        $key = self::key($name);
        foreach (self::parse(self::DEFAULT) as $k => $row) {
            if ($k === $key && (in_array($row['target'], [self::PAUSE, self::SKIP], true) || isset($buchungsarten[$row['target']]))) {
                return $row['target'];
            }
        }
        foreach ($buchungsarten as $k => $b) {
            if (self::key($b['name']) === $key || $k === $key) {
                return (string) $k;
            }
        }
        $byCategory = self::CATEGORY[strtoupper((string) $category)] ?? null;

        return $byCategory !== null && isset($buchungsarten[$byCategory]) ? $byCategory : null;
    }
}
