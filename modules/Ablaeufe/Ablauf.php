<?php

namespace Modules\Ablaeufe;

/**
 * Regeln für einen Ablauf: Bereiche, Status und das Bereinigen von Phasen und Schritten aus dem Formular.
 */
final class Ablauf
{
    public const BEREICHE = [
        'personal' => 'Personal',
        'schueler' => 'Schülerinnen und Schüler',
        'schulbetrieb' => 'Schulbetrieb',
        'anderes' => 'Anderes',
    ];

    public const STATUS = [
        'entwurf' => 'Entwurf',
        'gueltig' => 'Gültig',
        'archiviert' => 'Archiviert',
    ];

    private const KEY = '/^[a-z0-9][a-z0-9-]{0,39}$/';

    /**
     * Phasen bereinigen: Schlüssel eindeutig und gültig, Titel Pflicht.
     *
     * @param  array<mixed>  $phasen
     * @return list<array{key: string, titel: string}>
     */
    public static function phasen(array $phasen): array
    {
        $result = [];
        foreach ($phasen as $phase) {
            if (! is_array($phase)) {
                continue;
            }
            $titel = trim((string) ($phase['titel'] ?? ''));
            if ($titel === '') {
                continue;
            }
            $key = (string) ($phase['key'] ?? '');
            if (preg_match(self::KEY, $key) !== 1) {
                $key = self::slug($titel);
            }
            $base = $key;
            $n = 2;
            while (isset($result[$key])) {
                $key = substr($base, 0, 36).'-'.$n++;
            }
            $result[$key] = ['key' => $key, 'titel' => mb_substr($titel, 0, 120)];
        }

        return array_values($result);
    }

    /**
     * Schritte bereinigen: nur bekannte Phasen, Gruppen und Dokumente; Gegenstück nur auf einen Schritt einer
     * anderen Phase desselben Ablaufs; neue Schritte bekommen eine ID.
     *
     * @param  array<mixed>  $schritte
     * @param  list<string>  $phasen  Schlüssel
     * @param  list<string>  $gruppen  IDs
     * @param  list<string>  $dokumente  IDs
     * @return list<array<string, mixed>>
     */
    public static function schritte(array $schritte, array $phasen, array $gruppen, array $dokumente): array
    {
        $result = [];
        $ids = [];
        foreach ($schritte as $schritt) {
            if (! is_array($schritt)) {
                continue;
            }
            $text = trim((string) ($schritt['text'] ?? ''));
            $phase = (string) ($schritt['phase'] ?? '');
            if ($text === '' || ! in_array($phase, $phasen, true)) {
                continue;
            }
            $id = (string) ($schritt['id'] ?? '');
            if (preg_match(self::KEY, $id) !== 1 || isset($ids[$id])) {
                $id = 's-'.bin2hex(random_bytes(5));
            }
            $ids[$id] = $phase;
            $frist = $schritt['frist_tage'] ?? null;
            $result[] = [
                'id' => $id,
                'phase' => $phase,
                'text' => mb_substr($text, 0, 500),
                'verantwortung' => mb_substr(trim((string) ($schritt['verantwortung'] ?? '')), 0, 200),
                'kommunikation' => mb_substr(trim((string) ($schritt['kommunikation'] ?? '')), 0, 500),
                'dokumente' => self::auswahl($schritt['dokumente'] ?? [], $dokumente),
                'bemerkung' => mb_substr(trim((string) ($schritt['bemerkung'] ?? '')), 0, 2000),
                'frist_tage' => is_numeric($frist) ? max(-365, min(365, (int) $frist)) : null,
                'gilt_fuer' => self::auswahl($schritt['gilt_fuer'] ?? [], $gruppen),
                'gegenstueck' => is_string($schritt['gegenstueck'] ?? null) ? $schritt['gegenstueck'] : null,
            ];
        }
        foreach ($result as $i => $schritt) {
            $ziel = $schritt['gegenstueck'];
            if ($ziel !== null && (! isset($ids[$ziel]) || $ids[$ziel] === $schritt['phase'])) {
                $result[$i]['gegenstueck'] = null;
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $erlaubt
     * @return list<string>
     */
    private static function auswahl(mixed $werte, array $erlaubt): array
    {
        if (! is_array($werte)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('strval', $werte), fn (string $w): bool => in_array($w, $erlaubt, true))));
    }

    public static function slug(string $text): string
    {
        $slug = strtolower(strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'à' => 'a']));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');

        return substr($slug !== '' ? $slug : 'phase', 0, 40);
    }
}
