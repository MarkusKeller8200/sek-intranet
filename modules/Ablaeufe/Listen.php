<?php

namespace Modules\Ablaeufe;

/**
 * Einstellungen der Abläufe (#52): erweiterbare Tabellen mit Abkürzung, Bezeichnung, Beschreibung, URL und Notiz.
 * Rollen gehören zu einem Fachbereich, Orte haben einen Typ und einen übergeordneten Ort.
 * Startwerte werden beim ersten Zugriff angelegt; Abkürzungen der Fachbereiche und einzelne Rollen sind Vorschläge.
 */
final class Listen
{
    public const ORT_TYPEN = [
        'standort' => 'Standort',
        'schulhaus' => 'Schulhaus',
        'gebaeude' => 'Gebäude',
        'etage' => 'Etage',
        'zimmer' => 'Zimmer',
    ];

    /**
     * @return array<string, array{collection: string, titel: string, einzahl: string, beschreibung: string}>
     */
    public static function alle(): array
    {
        return [
            'orte' => ['collection' => 'wf_orte', 'titel' => 'Orte', 'einzahl' => 'Ort', 'beschreibung' => 'Standort, Schulhaus, Gebäude, Etage, Zimmer – mit übergeordnetem Ort.'],
            'fachbereiche' => ['collection' => 'wf_fachbereiche', 'titel' => 'Fachbereiche', 'einzahl' => 'Fachbereich', 'beschreibung' => 'Organisatorische Bereiche, z. B. Schulverwaltung oder Fachstelle ICT.'],
            'rollen' => ['collection' => 'wf_rollen', 'titel' => 'Rollen', 'einzahl' => 'Rolle', 'beschreibung' => 'Funktionen in Abläufen (nicht abschliessend), je einem Fachbereich zugeordnet.'],
            'zeitlich' => ['collection' => 'wf_zeitlich', 'titel' => 'Zeitlich', 'einzahl' => 'Zeitangabe', 'beschreibung' => 'Wann bzw. wie oft ein Ablauf oder Schritt stattfindet.'],
            'mitwirkung' => ['collection' => 'wf_mitwirkung', 'titel' => 'Mitwirkung', 'einzahl' => 'Mitwirkung', 'beschreibung' => 'Art der Beteiligung an einem Schritt, z. B. verantwortlich oder informiert.'],
        ];
    }

    public static function gibt(string $liste): bool
    {
        return isset(self::alle()[$liste]);
    }

    /**
     * Startwerte je Tabelle (id = Kürzel klein, damit Verweise stabil sind).
     *
     * @return list<array<string, mixed>>
     */
    public static function startwerte(string $liste): array
    {
        $rows = match ($liste) {
            'orte' => [
                ['SEK', 'Sekundarschule Bülach', 'Standort der Schule', 'standort', null, 'Beispiel – Schulhäuser, Gebäude und Zimmer ergänzen'],
            ],
            'fachbereiche' => [
                ['SB', 'Schulbehörde', 'Schulpflege bzw. Schulbehörde', null, null, 'Abkürzung: Vorschlag'],
                ['SV', 'Schulverwaltung', '', null, null, ''],
                ['SL', 'Schulleitung', '', null, null, ''],
                ['FP', 'Fachstelle Personal', '', null, null, 'Abkürzung: Vorschlag'],
                ['FL', 'Fachstelle Lernende', '', null, null, 'Abkürzung: Vorschlag'],
                ['FF', 'Fachstelle Finanzen', '', null, null, 'Abkürzung: Vorschlag'],
                ['FI', 'Fachstelle Infrastruktur', 'Gebäude, Räume, Hauswartung', null, null, 'Abkürzung: Vorschlag'],
                ['ICT', 'Fachstelle ICT', 'Geräte, Lizenzen, Zugänge', null, null, ''],
                ['FSP', 'Fachstelle Sonderpädagogik', '', null, null, 'Abkürzung: Vorschlag'],
            ],
            'rollen' => [
                ['SL', 'Schulleitung', '', 'sl', null, ''],
                ['LP', 'Lehrperson', '', 'sl', null, ''],
                ['FLP', 'Fachlehrperson', '', 'sl', null, 'Bezeichnung und Fachbereich prüfen'],
                ['SSA', 'Schulsozialarbeit', '', 'fl', null, 'Fachbereich prüfen'],
                ['SA', 'Schulassistenz', '', 'sl', null, 'Bezeichnung und Fachbereich prüfen'],
                ['SHP', 'Schulische Heilpädagogik', '', 'fsp', null, 'Fachbereich prüfen'],
                ['KA', 'Klassenassistenz', '', 'sl', null, 'Fachbereich prüfen'],
                ['VIK', 'Vikariat', 'Stellvertretung', 'sl', null, 'Grosser Themenbereich für sich'],
                ['SVW', 'Mitarbeitende Schulverwaltung', '', 'sv', null, ''],
                ['HW', 'Hauswartung', '', 'fi', null, ''],
                ['TICTS', 'Technischer ICT-Support', '', 'ict', null, 'Bezeichnung prüfen'],
                ['PICTS', 'Pädagogischer ICT-Support', '', 'ict', null, 'Bezeichnung prüfen'],
            ],
            'zeitlich' => [
                ['E', 'Einmalig', '', null, null, ''],
                ['EIN', 'Bei Eintritt', 'Vor bzw. am ersten Arbeitstag', null, null, ''],
                ['AUS', 'Bei Austritt', 'Vor bzw. am letzten Arbeitstag', null, null, ''],
                ['MUT', 'Bei Mutation', 'Bei Änderung von Funktion, Name oder Pensum', null, null, ''],
                ['SJB', 'Schuljahresbeginn', '', null, null, ''],
                ['SJE', 'Schuljahresende', '', null, null, ''],
                ['J', 'Jährlich', '', null, null, ''],
                ['SEM', 'Semesterweise', '', null, null, ''],
                ['Q', 'Quartalsweise', '', null, null, ''],
                ['M', 'Monatlich', '', null, null, ''],
                ['W', 'Wöchentlich', '', null, null, ''],
                ['B', 'Bei Bedarf', '', null, null, ''],
            ],
            'mitwirkung' => [
                ['V', 'Verantwortlich', 'Führt den Schritt aus bzw. sorgt dafür, dass er erledigt wird', null, null, ''],
                ['E', 'Entscheid', 'Entscheidet bzw. genehmigt', null, null, ''],
                ['M', 'Mitwirkung', 'Arbeitet mit bzw. wird vorher angehört', null, null, ''],
                ['I', 'Information', 'Wird informiert', null, null, ''],
            ],
            default => [],
        };

        return array_map(fn (array $r, int $i): array => array_filter([
            'id' => strtolower($r[0]),
            'kuerzel' => $r[0],
            'bezeichnung' => $r[1],
            'beschreibung' => $r[2],
            'url' => '',
            'notiz' => $r[5],
            'position' => $i + 1,
            'fachbereich_id' => $liste === 'rollen' ? $r[3] : null,
            'typ' => $liste === 'orte' ? $r[3] : null,
            'parent_id' => $liste === 'orte' ? $r[4] : null,
        ], fn ($v, string $k): bool => $v !== null || in_array($k, $liste === 'orte' ? ['parent_id'] : [], true), ARRAY_FILTER_USE_BOTH), $rows, array_keys($rows));
    }
}
