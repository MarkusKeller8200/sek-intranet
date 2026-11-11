<?php

namespace Modules\Beschaffung;

/**
 * Die Kataloge unter «Bestellen» (#197, #198). Alle haben denselben Aufbau (Sammlung bes_artikel); je Katalog
 * sind nur die vorgeschlagenen Spalten und die Texte der Seite verschieden. Quelle: ICT-Wiki (Confluence).
 */
final class Kataloge
{
    private const WIKI = 'https://sekbuelach.atlassian.net/wiki/spaces/ictwiki/pages/';

    /**
     * Schlüssel => Angaben. «spalten»: in der Tabelle anfangs sichtbar (übrige ein-/ausblendbar).
     *
     * @var array<string, array{title: string, bereich: string, icon: string, description: string, features: list<string>, spalten: list<string>, quellen: list<array{titel: string, url: string}>, plugin?: string}>
     */
    public const ALLE = [
        'zimmer-ausruestung' => [
            'title' => 'Zimmerausrüstung',
            'bereich' => 'Hardware',
            'icon' => 'Projector',
            'description' => 'Standardausrüstung der Schulzimmer bestellen oder ersetzen: Beamer, Leinwand, Bildschirm, AV-Receiver, Apple TV, Lautsprecher, Drucker.',
            'features' => ['Katalog mit Standardgeräten', 'Auswahl nach Zimmer', 'Bestehende Ausrüstung aus dem Inventar', 'Defekt melden und Ersatz bestellen'],
            'spalten' => ['bild', 'bezeichnung', 'zubehoer', 'preis', 'standort', 'wiki'],
            'quellen' => [['titel' => 'ICT – Zimmerausrüstung – Hardware – ab SJ25-26', 'url' => self::WIKI.'306118657']],
            'plugin' => 'inventory360',
        ],
        'ausruestung-lehrpersonen' => [
            'title' => 'Persönliche Ausrüstung – Lehrpersonen',
            'bereich' => 'Hardware',
            'icon' => 'GraduationCap',
            'description' => 'Persönliche Geräte für Lehrpersonen: MacBook, iPad und Zubehör.',
            'features' => ['Katalog mit Standardgeräten', 'Richtpreise aus der letzten Ausschreibung', 'Bestellung mit Begründung', 'Status bis zur Ausgabe'],
            'spalten' => ['bild', 'gruppe', 'bezeichnung', 'modell', 'beschreibung', 'preis', 'wiki'],
            'quellen' => [['titel' => 'ICT – Persönliche Ausrüstung – Lehrpersonen – ab SJ25/26', 'url' => self::WIKI.'98336771']],
        ],
        'ausruestung-schuelerinnen' => [
            'title' => 'Persönliche Ausrüstung – Schüler:innen',
            'bereich' => 'Hardware',
            'icon' => 'Tablet',
            'description' => 'Persönliche Geräte für Schüler:innen beim Eintritt in die Sekundarschule: iPad und Zubehör.',
            'features' => ['Katalog mit Standardgeräten', 'Richtpreise aus der letzten Ausschreibung', 'Ersatz bei Verlust oder Defekt'],
            'spalten' => ['bild', 'bezeichnung', 'modell', 'beschreibung', 'preis', 'wiki'],
            'quellen' => [['titel' => 'ICT – Persönliche Ausrüstung – Schüler:Innen – ab SJ25/26', 'url' => self::WIKI.'314343425']],
        ],
        'ausruestung-schulverwaltung' => [
            'title' => 'Persönliche Ausrüstung – Schulverwaltung',
            'bereich' => 'Hardware',
            'icon' => 'Landmark',
            'description' => 'Persönliche Geräte für die Schulverwaltung: Notebook, Bildschirm, Docking und Zubehör.',
            'features' => ['Katalog mit Standardgeräten', 'Richtpreise aus der letzten Ausschreibung', 'Bestellung mit Begründung', 'Status bis zur Ausgabe'],
            'spalten' => ['bild', 'bezeichnung', 'modell', 'beschreibung', 'preis', 'pruefen'],
            'quellen' => [['titel' => 'ICT – Persönliche Ausrüstung – Schulverwaltung – ab 2026', 'url' => self::WIKI.'535986177']],
        ],
        'ausruestung-hausdienst' => [
            'title' => 'Persönliche Ausrüstung – Hausdienst',
            'bereich' => 'Hardware',
            'icon' => 'Wrench',
            'description' => 'Persönliche Geräte für den Hausdienst: Notebook (Lenovo), iPad und Zubehör.',
            'features' => ['Katalog mit Standardgeräten', 'Richtpreise aus der letzten Ausschreibung', 'Bestellung mit Begründung', 'Status bis zur Ausgabe'],
            'spalten' => ['bild', 'gruppe', 'bezeichnung', 'modell', 'beschreibung', 'preis', 'pruefen'],
            'quellen' => [['titel' => 'ICT – Persönliche Ausrüstung – Hausdienst – ab 2026', 'url' => self::WIKI.'536313857']],
        ],
        'plattformen-unterricht' => [
            'title' => 'Plattformen im Schulunterricht',
            'bereich' => 'Plattformen',
            'icon' => 'School',
            'description' => 'Für den Unterricht freigegebene Anwendungen, Plattformen und digitale Lehrmittel bestellen.',
            'features' => ['Katalog mit Bestellnummern', 'Kostenstufe je Anwendung', 'Bestellung und Lizenzierung durch die Fachstelle ICT'],
            'spalten' => ['bild', 'bestellnummer', 'bezeichnung', 'gruppe', 'kosten', 'preis', 'url', 'wiki', 'status'],
            'quellen' => [['titel' => 'Anwendungen – Plattformen im Schulunterricht (Übersicht)', 'url' => self::WIKI.'279085072'], ['titel' => 'ICT – Anwendungen – Plattformen im Schulunterricht ab SJ26/27', 'url' => self::WIKI.'314540033'], ['titel' => 'Anwendungen – Plattformen im Fachunterricht (Musik)', 'url' => self::WIKI.'848560141']],
        ],
        'plattformen-administration' => [
            'title' => 'Plattformen in der Schuladministration',
            'bereich' => 'Plattformen',
            'icon' => 'Building2',
            'description' => 'Für Schulverwaltung und Schulleitung freigegebene Anwendungen und Plattformen bestellen.',
            'features' => ['Katalog mit Bestellnummern', 'Nutzerkreis je Anwendung', 'Bestellung und Lizenzierung durch die Fachstelle ICT'],
            'spalten' => ['bild', 'bestellnummer', 'bezeichnung', 'gruppe', 'nutzerkreis', 'preis', 'url', 'wiki', 'status'],
            'quellen' => [['titel' => 'Anwendungen – Plattformen in der Schuladministration (Übersicht)', 'url' => self::WIKI.'347701252'], ['titel' => 'ICT – Anwendungen – Plattformen in der Administration (SV, SL)', 'url' => self::WIKI.'347209729']],
        ],
        'plattformen-ict' => [
            'title' => 'Plattformen der Fachstelle ICT',
            'bereich' => 'Plattformen',
            'icon' => 'ShieldCheck',
            'description' => 'Anwendungen und Systeme für Betrieb, Support und Verwaltung der ICT-Infrastruktur.',
            'features' => ['Katalog mit Bestellnummern', 'Einführung und Änderung nur durch die Fachstelle ICT'],
            'spalten' => ['bild', 'bestellnummer', 'bezeichnung', 'gruppe', 'preis', 'url', 'wiki', 'status'],
            'quellen' => [['titel' => 'Anwendungen – Plattformen der ICT Fachstelle (Übersicht)', 'url' => self::WIKI.'349339701'], ['titel' => 'ICT – Anwendungen – Plattformen in der Fachstelle (ICT)', 'url' => self::WIKI.'349372417']],
        ],
        'fachanwendungen-schulbetrieb' => [
            'title' => 'Fachanwendungen – Schulbetrieb',
            'bereich' => 'Plattformen',
            'icon' => 'AppWindow',
            'description' => 'Im Schulbetrieb eingesetzte Fachanwendungen mit Anleitungen.',
            'features' => ['Liste freigegebener Anwendungen', 'Anleitungen im ICT-Wiki', 'Zugang beantragen'],
            'spalten' => ['bild', 'bezeichnung', 'beschreibung', 'preis', 'wiki', 'angaben'],
            'quellen' => [['titel' => 'Fachanwendungen – Schulbetrieb', 'url' => self::WIKI.'206766355']],
        ],
        'fachanwendungen-schulverwaltung' => [
            'title' => 'Fachanwendungen – Schulverwaltung',
            'bereich' => 'Plattformen',
            'icon' => 'FileStack',
            'description' => 'In der Schulverwaltung eingesetzte Fachanwendungen mit Anleitungen.',
            'features' => ['Liste freigegebener Anwendungen', 'Anleitungen im ICT-Wiki', 'Zugang beantragen'],
            'spalten' => ['bild', 'bezeichnung', 'beschreibung', 'preis', 'wiki', 'angaben'],
            'quellen' => [['titel' => 'Fachanwendungen – Schulverwaltung', 'url' => self::WIKI.'196608163']],
        ],
        'mobile-apps' => [
            'title' => 'Mobile Apps',
            'bereich' => 'Software – Apps',
            'icon' => 'MonitorSmartphone',
            'description' => 'Apps für iPad und Apple TV beantragen, die über Jamf verteilt werden.',
            'features' => ['Liste freigegebener Apps nach Kategorie', 'Lizenzanzahl und Verteilung', 'Verteilung auf Geräte über Jamf'],
            'spalten' => ['bild', 'bezeichnung', 'gruppe', 'preis', 'lizenzen', 'nutzerkreis', 'status'],
            'quellen' => [['titel' => 'Mobile Apps Übersicht: Lizenz, Version und Anwendungsbereich', 'url' => self::WIKI.'510722052']],
            'plugin' => 'jamf',
        ],
        'material-picts-ticts' => [
            'title' => 'Material – PICTS / TICTS',
            'bereich' => 'Material',
            'icon' => 'Boxes',
            'description' => 'Unterrichtsmaterial der PICTS und von der Fachstelle ICT betreute Geräte je Schulhaus, mit Zuständigkeit und Zugang.',
            'features' => ['Materialliste je Schulhaus', 'Zuständigkeit PICTS oder TICTS', 'Ausleihe über Booked'],
            'spalten' => ['bild', 'bezeichnung', 'verantwortung', 'menge', 'zugang', 'beschreibung', 'pruefen'],
            'quellen' => [['titel' => 'Material – PICTS / TICTS', 'url' => self::WIKI.'848625665']],
        ],
    ];

    public static function existiert(string $key): bool
    {
        return isset(self::ALLE[$key]);
    }

    /**
     * @return list<string>
     */
    public static function schluessel(): array
    {
        return array_keys(self::ALLE);
    }
}
