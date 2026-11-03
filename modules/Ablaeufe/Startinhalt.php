<?php

namespace Modules\Ablaeufe;

/**
 * Startinhalt aus der Excel-Vorlage «On-/Offboarding, Mutationen Mitarbeitende» (03.11.2026, #48):
 * Personengruppen und der Ablauf «Mitarbeitende: Eintritt, Austritt, Mutation». Wird beim ersten Zugriff
 * angelegt. Gegenstücke Onboarding ↔ Offboarding sind aus der Zeile der Tabelle abgeleitet.
 */
final class Startinhalt
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function gruppen(): array
    {
        return [
            ['id' => 'schulpflege', 'name' => 'Schulpflege', 'bemerkung' => '', 'position' => 1],
            ['id' => 'vikare', 'name' => 'Vikare', 'bemerkung' => 'Grosser Themenbereich für sich …', 'position' => 2],
            ['id' => 'lehrpersonen', 'name' => 'Lehrpersonen', 'bemerkung' => '', 'position' => 3],
            ['id' => 'schulleitung', 'name' => 'Schulleitung', 'bemerkung' => '', 'position' => 4],
            ['id' => 'klassenassistenzen', 'name' => 'Klassenassistenzen', 'bemerkung' => '', 'position' => 5],
            ['id' => 'hauswartung', 'name' => 'Hauswartung', 'bemerkung' => '', 'position' => 6],
            ['id' => 'shp', 'name' => 'SHP', 'bemerkung' => '', 'position' => 7],
            ['id' => 'schulverwaltung', 'name' => 'Schulverwaltung', 'bemerkung' => '', 'position' => 8],
            ['id' => 'weitere-stellen', 'name' => 'Weitere Stellen (z. B. Zahnprophylaxe)', 'bemerkung' => '', 'position' => 9],
            ['id' => 'tagesbetreuung', 'name' => 'Tagesbetreuung', 'bemerkung' => '', 'position' => 10],
            ['id' => 'sonderpaedagogik', 'name' => 'Kommunales sonderpädagogisches Fachpersonal (DaZ, …)', 'bemerkung' => '', 'position' => 11],
            ['id' => 'schulsozialarbeit', 'name' => 'Schulsozialarbeit', 'bemerkung' => 'Zugriff auf SuS-Daten: Wie viel ist notwendig zur Erfüllung ihrer Aufgabe?', 'position' => 12],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function ablaeufe(): array
    {
        return [[
            'id' => 'mitarbeitende',
            'titel' => 'Mitarbeitende: Eintritt, Austritt, Mutation',
            'bereich' => 'personal',
            'beschreibung' => 'On- und Offboarding sowie Mutationen von Mitarbeitenden. Übernommen aus der Excel-Vorlage; Spalten «Kommunikation an» und «Dokumente» sind noch zu ergänzen.',
            'verantwortlich' => 'Schulverwaltung',
            'status' => 'entwurf',
            'gueltig_ab' => null,
            'phasen' => [
                ['key' => 'onboarding', 'titel' => 'Onboarding (Eintritt)'],
                ['key' => 'offboarding', 'titel' => 'Offboarding (Austritt)'],
                ['key' => 'mutation', 'titel' => 'Mutationen (Änderung)'],
            ],
            'schritte' => self::schritte(),
            'geaendert_von' => null,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function schritte(): array
    {
        return [
            ['id' => 'on-01', 'phase' => 'onboarding', 'text' => 'Meldung über Eintritt an Schulverwaltung', 'verantwortung' => 'vorgesetzte Stelle', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-01'],
            ['id' => 'on-02', 'phase' => 'onboarding', 'text' => 'Ausstellung Anstellungsverfügung', 'verantwortung' => 'SV', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-02'],
            ['id' => 'on-03', 'phase' => 'onboarding', 'text' => 'Personaldaten erfassen, Personaldossier eröffnen', 'verantwortung' => 'SV', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-03'],
            ['id' => 'on-04', 'phase' => 'onboarding', 'text' => 'Zuweisung Funktions-Gruppe (→ Berechtigungen, Tools)', 'verantwortung' => 'SV', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-04'],
            ['id' => 'on-05', 'phase' => 'onboarding', 'text' => 'Schlüssel (welche Räume, …)', 'verantwortung' => 'SV/Hauswartung', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-05'],
            ['id' => 'on-06', 'phase' => 'onboarding', 'text' => 'Strafregister-Auszug einholen', 'verantwortung' => 'SV', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-07', 'phase' => 'onboarding', 'text' => 'Microsoft Login erstellen gemäss Rollen- und Berechtigungskonzept', 'verantwortung' => 'IT', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => 'Ab wann dürfen Logins erstellt werden?', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-06'],
            ['id' => 'on-08', 'phase' => 'onboarding', 'text' => 'Datenschutz-Weisung unterzeichnen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-09', 'phase' => 'onboarding', 'text' => 'Nutzungsvereinbarung/Verschwiegenheitserklärung/… unterzeichnen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-10', 'phase' => 'onboarding', 'text' => 'Login Schulverwaltungssystem gemäss Rollen- und Berechtigungskonzept', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-08'],
            ['id' => 'on-11', 'phase' => 'onboarding', 'text' => 'Login Escola/… gemäss Rollen- und Berechtigungskonzept', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-09'],
            ['id' => 'on-12', 'phase' => 'onboarding', 'text' => 'Login Lehrmittel gemäss Rollen- und Berechtigungskonzept', 'verantwortung' => 'TICTS/PICTS', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-10'],
            ['id' => 'on-13', 'phase' => 'onboarding', 'text' => 'Kommunikation an Eltern und Klasse', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-11'],
            ['id' => 'on-14', 'phase' => 'onboarding', 'text' => 'Erstellung Zugang Notfallapp', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-12'],
            ['id' => 'on-15', 'phase' => 'onboarding', 'text' => 'Einführung durch SL', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-13'],
            ['id' => 'on-16', 'phase' => 'onboarding', 'text' => 'Begrüssungsmail SL an LP', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-17', 'phase' => 'onboarding', 'text' => 'Mitteilung an die LP via Wocheninfo', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-18', 'phase' => 'onboarding', 'text' => 'Datum Elternabend bestimmen und mitteilen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-19', 'phase' => 'onboarding', 'text' => 'Vorstellungsbrief einfordern und erstellen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-20', 'phase' => 'onboarding', 'text' => 'Abgabe Stundenplan', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-21', 'phase' => 'onboarding', 'text' => 'Berufsauftrag definieren', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-22', 'phase' => 'onboarding', 'text' => 'Datum Wellentag neuer LP mitteilen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-23', 'phase' => 'onboarding', 'text' => 'Klassenspiegel abgeben', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-24', 'phase' => 'onboarding', 'text' => 'Kontaktdaten ans pädagogische Team weiterleiten', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-25', 'phase' => 'onboarding', 'text' => 'Ämter der SV mitteilen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-26', 'phase' => 'onboarding', 'text' => 'Material bestellen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-15'],
            ['id' => 'on-27', 'phase' => 'onboarding', 'text' => 'Lehrmittel bestellen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-16'],
            ['id' => 'on-28', 'phase' => 'onboarding', 'text' => 'Einladung Begrüssungsapero', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'on-29', 'phase' => 'onboarding', 'text' => 'Übergabe Geräte und Einführung, Übergabeprotokoll', 'verantwortung' => 'IT', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'off-17'],
            ['id' => 'off-01', 'phase' => 'offboarding', 'text' => 'Meldung über Austritt', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-01'],
            ['id' => 'off-02', 'phase' => 'offboarding', 'text' => 'Nicht-Verlängerung der Verfügung', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-02'],
            ['id' => 'off-03', 'phase' => 'offboarding', 'text' => 'Personaldossier abschliessen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '→ Digitale Aktenführung ab 1.1.29', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-03'],
            ['id' => 'off-04', 'phase' => 'offboarding', 'text' => 'Berechtigungen entziehen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-04'],
            ['id' => 'off-05', 'phase' => 'offboarding', 'text' => 'Schlüssel zurückfordern', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-05'],
            ['id' => 'off-06', 'phase' => 'offboarding', 'text' => 'Logins entziehen/sperren, allenfalls Autoresponder (Microsoft)', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-07'],
            ['id' => 'off-07', 'phase' => 'offboarding', 'text' => 'Austrittserklärung', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'off-08', 'phase' => 'offboarding', 'text' => 'Logins entziehen/sperren (Schulverwaltungssystem)', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => 'Regeln festlegen, wann was gesperrt wird, …', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-10'],
            ['id' => 'off-09', 'phase' => 'offboarding', 'text' => 'Logins entziehen/sperren (Escola/…)', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-11'],
            ['id' => 'off-10', 'phase' => 'offboarding', 'text' => 'Logins entziehen/sperren (Lehrmittel)', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-12'],
            ['id' => 'off-11', 'phase' => 'offboarding', 'text' => 'Kommunikation', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-13'],
            ['id' => 'off-12', 'phase' => 'offboarding', 'text' => 'Entfernung Zugang Notfallapp', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-14'],
            ['id' => 'off-13', 'phase' => 'offboarding', 'text' => 'Austrittsgespräch SL', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-15'],
            ['id' => 'off-14', 'phase' => 'offboarding', 'text' => 'Nachfolge festlegen, Übergabe', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'off-15', 'phase' => 'offboarding', 'text' => 'Rückgabe Material', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-26'],
            ['id' => 'off-16', 'phase' => 'offboarding', 'text' => 'Rückgabe Lehrmittel', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-27'],
            ['id' => 'off-17', 'phase' => 'offboarding', 'text' => 'Rückgabe Geräte und Zubehör', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => 'on-29'],
            ['id' => 'mut-01', 'phase' => 'mutation', 'text' => 'Meldung über Mutation', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => 'Arten von Mutationen: Funktion/Rolle, Zusatzrolle · Name · Pensum (bei Reduktion: Teilkündigung)', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'mut-02', 'phase' => 'mutation', 'text' => 'Gruppen/Berechtigungen anpassen', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'mut-03', 'phase' => 'mutation', 'text' => 'Überprüfung Zugriffsberechtigungen (Schulverwaltungssystem)', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'mut-04', 'phase' => 'mutation', 'text' => 'Überprüfung Zugriffsberechtigungen (Escola/…)', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
            ['id' => 'mut-05', 'phase' => 'mutation', 'text' => 'Überprüfung Zugriffsberechtigungen (Lehrmittel)', 'verantwortung' => '', 'kommunikation' => '', 'dokumente' => [], 'bemerkung' => '', 'frist_tage' => null, 'gilt_fuer' => [], 'gegenstueck' => null],
        ];
    }
}
