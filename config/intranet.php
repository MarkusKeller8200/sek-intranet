<?php

/*
|--------------------------------------------------------------------------
| SekBülach Intranet – Prototyp-Konfiguration
|--------------------------------------------------------------------------
|
| Der Prototyp speichert alle Fachdaten als JSON-Dateien (eine Datei pro
| Sammlung). Controller und Oberfläche sprechen nur mit Repository-
| Schnittstellen (app/Data/Contracts). Sobald Funktionen und Datenstrukturen
| feststehen, wird eine Datenbank-Implementierung derselben Schnittstellen
| ergänzt und hier über INTRANET_STORE umgeschaltet.
|
*/

return [

    // json = Dateien (Prototyp). Später: database (PostgreSQL, wie IDA ADR-003).
    'store' => env('INTRANET_STORE', 'json'),

    // Ordner für die JSON-Dateien, relativ zum Projektordner oder absolut.
    'data_path' => env('INTRANET_DATA_PATH', 'storage/app/data'),

    // Ordner für hochgeladene Dokumente (Disk "local", also storage/app/private/...).
    'upload_directory' => 'intranet/documents',

    // Maximale Uploadgrösse in Kilobyte.
    'upload_max_kb' => 20480,

    // Anmeldung über Auswahl eines Demo-Benutzers. Nur für den Prototyp.
    // Produktiv: OIDC mit Entra ID / Edulog (IDA ADR-004).
    'demo_login' => (bool) env('INTRANET_DEMO_LOGIN', false),

    // Gemeinsames Passwort vor der ganzen Seite (HTTP Basic Auth).
    // Leer = keine Sperre. Benutzername leer = beliebiger Benutzername.
    'access' => [
        'password' => env('INTRANET_ACCESS_PASSWORD', ''),
        'user' => env('INTRANET_ACCESS_USER', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stammwerte (Auswahllisten)
    |--------------------------------------------------------------------------
    | Schlüssel (links) werden gespeichert, Bezeichnungen (rechts) angezeigt.
    | Schlüssel nur ändern, wenn unter «renames» eine Umstellung eingetragen ist.
    */

    'roles' => [
        'admin' => 'Administration',
        'redaktion' => 'Redaktion',
        'mitarbeitende' => 'Mitarbeitende',
    ],

    'schools' => [
        'gesamt' => 'Gesamte Schule',
        'hinterbirch' => 'Schulhaus Hinterbirch',
        'mettmenriet' => 'Schulhaus Mettmenriet',
        'verwaltung' => 'Schulverwaltung',
    ],

    'audiences' => [
        'alle' => 'Alle Mitarbeitenden',
        'lehrpersonen' => 'Lehrpersonen',
        'verwaltung' => 'Verwaltung',
        'schulleitung' => 'Schulleitung',
    ],

    'announcement_categories' => [
        'allgemein' => 'Allgemein',
        'schulleitung' => 'Schulleitung',
        'it' => 'IT & Geräte',
        'personal' => 'Personal',
        'anlass' => 'Anlass',
    ],

    'event_categories' => [
        'schulanlass' => 'Schulanlass',
        'konferenz' => 'Konferenz',
        'weiterbildung' => 'Weiterbildung',
        'ferien' => 'Ferien / schulfrei',
        'termin' => 'Termin',
    ],

    'document_categories' => [
        'reglemente' => 'Reglemente & Weisungen',
        'formulare' => 'Formulare',
        'it' => 'IT-Anleitungen',
        'unterricht' => 'Unterricht',
        'links' => 'Wichtige Links',
    ],

    /*
    |--------------------------------------------------------------------------
    | Farben je Auswahlwert (Farbkonzept, siehe /farbkonzept)
    |--------------------------------------------------------------------------
    | Erlaubte Farben: brand, slate, sky, cyan, teal, emerald, amber, orange,
    | rose, violet, indigo. Fehlt ein Wert, wird «slate» verwendet.
    | Innerhalb einer Liste jede Farbe nur einmal verwenden.
    */

    'colors' => [
        'schools' => [
            'gesamt' => 'brand',
            'hinterbirch' => 'teal',
            'mettmenriet' => 'violet',
            'verwaltung' => 'slate',
        ],
        'roles' => [
            'admin' => 'rose',
            'redaktion' => 'indigo',
            'mitarbeitende' => 'slate',
        ],
        'audiences' => [
            'alle' => 'brand',
            'lehrpersonen' => 'emerald',
            'verwaltung' => 'slate',
            'schulleitung' => 'indigo',
        ],
        'announcement_categories' => [
            'allgemein' => 'slate',
            'schulleitung' => 'indigo',
            'it' => 'cyan',
            'personal' => 'rose',
            'anlass' => 'amber',
        ],
        'event_categories' => [
            'schulanlass' => 'sky',
            'konferenz' => 'indigo',
            'weiterbildung' => 'emerald',
            'ferien' => 'amber',
            'termin' => 'slate',
        ],
        'document_categories' => [
            'reglemente' => 'indigo',
            'formulare' => 'amber',
            'it' => 'cyan',
            'unterricht' => 'emerald',
            'links' => 'sky',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Umstellungen gespeicherter Werte (läuft bei jedem Start, idempotent)
    |--------------------------------------------------------------------------
    | Wird ein Schlüssel einer Auswahlliste umbenannt, stellt
    | «php artisan intranet:upgrade» vorhandene Datensätze um.
    | Format: [Sammlung, Feld, alter Wert, neuer Wert].
    | Später werden daraus Datenbank-Migrationen.
    */

    'renames' => [
        ['users', 'school', 'schulhaus-a', 'hinterbirch'],
        ['users', 'school', 'schulhaus-b', 'mettmenriet'],
        ['events', 'school', 'schulhaus-a', 'hinterbirch'],
        ['events', 'school', 'schulhaus-b', 'mettmenriet'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Datenschema (Quelle für Doku, Datenstruktur-Seite und spätere Migrationen)
    |--------------------------------------------------------------------------
    | Typen: uuid, string, text, email, url, bool, date, datetime, enum, ref.
    | Jede Sammlung erhält automatisch id (uuid v7), created_at, updated_at.
    | Wird ein Feld geändert, hier nachführen: Die Seite «Datenstrukturen»
    | vergleicht die gespeicherten Datensätze mit diesem Schema.
    */

    'schema' => [

        'users' => [
            'label' => 'Personen',
            'description' => 'Mitarbeitende, zugleich Benutzerkonten des Intranets.',
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
                'email' => ['type' => 'email', 'required' => true, 'unique' => true, 'label' => 'E-Mail'],
                'role' => ['type' => 'enum', 'values' => 'roles', 'required' => true, 'label' => 'Rolle im Intranet'],
                'function' => ['type' => 'string', 'required' => false, 'label' => 'Funktion'],
                'school' => ['type' => 'enum', 'values' => 'schools', 'required' => true, 'label' => 'Schulhaus'],
                'phone' => ['type' => 'string', 'required' => false, 'label' => 'Telefon'],
                'active' => ['type' => 'bool', 'required' => true, 'label' => 'Aktiv'],
            ],
        ],

        'announcements' => [
            'label' => 'Mitteilungen',
            'description' => 'News und Informationen an die Mitarbeitenden.',
            'fields' => [
                'title' => ['type' => 'string', 'required' => true, 'label' => 'Titel'],
                'body' => ['type' => 'text', 'required' => true, 'label' => 'Text'],
                'category' => ['type' => 'enum', 'values' => 'announcement_categories', 'required' => true, 'label' => 'Kategorie'],
                'audience' => ['type' => 'enum', 'values' => 'audiences', 'required' => true, 'label' => 'Zielgruppe'],
                'pinned' => ['type' => 'bool', 'required' => true, 'label' => 'Angeheftet'],
                'published_at' => ['type' => 'datetime', 'required' => true, 'label' => 'Veröffentlicht ab'],
                'author_id' => ['type' => 'ref', 'references' => 'users', 'required' => true, 'label' => 'Autor/in'],
            ],
        ],

        'events' => [
            'label' => 'Termine',
            'description' => 'Schulanlässe, Konferenzen, Weiterbildungen und Ferien.',
            'fields' => [
                'title' => ['type' => 'string', 'required' => true, 'label' => 'Titel'],
                'description' => ['type' => 'text', 'required' => false, 'label' => 'Beschreibung'],
                'category' => ['type' => 'enum', 'values' => 'event_categories', 'required' => true, 'label' => 'Kategorie'],
                'school' => ['type' => 'enum', 'values' => 'schools', 'required' => true, 'label' => 'Schulhaus'],
                'location' => ['type' => 'string', 'required' => false, 'label' => 'Ort'],
                'starts_at' => ['type' => 'datetime', 'required' => true, 'label' => 'Beginn'],
                'ends_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Ende'],
                'all_day' => ['type' => 'bool', 'required' => true, 'label' => 'Ganztägig'],
                'created_by' => ['type' => 'ref', 'references' => 'users', 'required' => true, 'label' => 'Erfasst von'],
            ],
        ],

        'documents' => [
            'label' => 'Dokumente & Links',
            'description' => 'Hochgeladene Dateien oder Verweise auf externe Seiten.',
            'fields' => [
                'title' => ['type' => 'string', 'required' => true, 'label' => 'Titel'],
                'description' => ['type' => 'text', 'required' => false, 'label' => 'Beschreibung'],
                'category' => ['type' => 'enum', 'values' => 'document_categories', 'required' => true, 'label' => 'Kategorie'],
                'kind' => ['type' => 'enum', 'values' => ['file' => 'Datei', 'link' => 'Link'], 'required' => true, 'label' => 'Art'],
                'url' => ['type' => 'url', 'required' => false, 'label' => 'Link (bei Art Link)'],
                'file_path' => ['type' => 'string', 'required' => false, 'label' => 'Dateipfad (bei Art Datei)'],
                'file_name' => ['type' => 'string', 'required' => false, 'label' => 'Originaler Dateiname'],
                'file_size' => ['type' => 'int', 'required' => false, 'label' => 'Dateigrösse (Bytes)'],
                'created_by' => ['type' => 'ref', 'references' => 'users', 'required' => true, 'label' => 'Erfasst von'],
            ],
        ],

    ],

];
