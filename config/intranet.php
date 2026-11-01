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

/*
| GUI-Prototypen (Menü «GUI-Prototypen», App\Support\GuiPrototypes): Bausteine der Oberfläche mit Status
| (Entwicklung → Prüfung → Freigegeben) und Versionen. Eine freigegebene Version ist das verbindliche
| Template; alle Seiten, die den Baustein verwenden («imports»), müssen danach überarbeitet werden.
*/
$guiComponents = [
    'menue' => [
        'title' => 'Menü',
        'icon' => 'Menu',
        'description' => 'Seitenleiste mit Hauptmenüs und Untermenüs, Aktionsmenü (Dropdown-Menü) und Breadcrumbs.',
        'imports' => ['@/components/ui/dropdown-menu', '@/components/ui/sidebar', '@/components/ui/breadcrumb', '@/components/nav-modules'],
    ],
    'tabelle' => [
        'title' => 'Tabellen',
        'icon' => 'Table2',
        'description' => 'Bearbeitbare Tabelle (Grundlage: Personen) mit Suche, Filtern, Sortierung, Spaltenwahl, Auswahl, Export und einfache Tabelle.',
        'imports' => ['@/components/data-grid', '@/components/ui/table'],
    ],
    'dropdown' => [
        'title' => 'Dropdown',
        'icon' => 'ChevronsUpDown',
        'description' => 'Auswahlfeld ohne und mit Vorauswahl, gesperrt, mit Fehler sowie Mehrfachauswahl.',
        'imports' => ['@/components/select-field', '@/components/ui/select'],
    ],
    'button' => [
        'title' => 'Button',
        'icon' => 'MousePointerClick',
        'description' => 'Varianten (Haupt-, Neben-, Umriss-, Text-, Gefahr-Button), Grössen, mit Symbol, nur Symbol, lädt, gesperrt.',
        'imports' => ['@/components/ui/button', '@/components/delete-button'],
    ],
    'formular' => [
        'title' => 'Formularfelder',
        'icon' => 'TextCursorInput',
        'description' => 'Textfeld, Textbereich, Zahl, Datum, Kontrollkästchen, Beschriftung, Hinweis und Fehlermeldung.',
        'imports' => ['@/components/ui/input', '@/components/ui/textarea', '@/components/ui/checkbox', '@/components/form-field', '@/components/input-error', '@/components/ui/label'],
    ],
    'badge' => [
        'title' => 'Badges & Status',
        'icon' => 'Tag',
        'description' => 'Kennzeichen für Status, Auswahlwerte in den Farben des Farbkonzepts und Zähler.',
        'imports' => ['@/components/ui/badge', '@/components/option-badge'],
    ],
    'meldungen' => [
        'title' => 'Meldungen & Dialoge',
        'icon' => 'MessageSquareWarning',
        'description' => 'Hinweisboxen, Rückfrage-Dialog, kurze Meldungen (Toast) und Tooltips.',
        'imports' => ['@/components/ui/alert', '@/components/ui/dialog', '@/components/ui/tooltip', 'sonner', '@/components/alert-error'],
    ],
    'karten' => [
        'title' => 'Karten & Seitenkopf',
        'icon' => 'PanelsTopLeft',
        'description' => 'Seitenkopf mit Titel und Beschreibung, Karten mit Kopf, Inhalt und Fuss, Kennzahlen, leerer Zustand.',
        'imports' => ['@/components/ui/card', '@/components/page-header', '@/components/empty-state', '@/components/heading'],
    ],
    'umschalter' => [
        'title' => 'Reiter & Umschalter',
        'icon' => 'ToggleRight',
        'description' => 'Reiter (Umschaltgruppe), Ein/Aus-Schalter, aufklappbare Bereiche.',
        'imports' => ['@/components/ui/toggle-group', '@/components/ui/toggle', '@/components/ui/collapsible'],
    ],
    'laden' => [
        'title' => 'Ladezustände',
        'icon' => 'Loader',
        'description' => 'Ladeanzeige (Spinner) und Platzhalter (Skeleton), solange Daten geladen werden.',
        'imports' => ['@/components/ui/spinner', '@/components/ui/skeleton'],
    ],
];

return [

    // json = Dateien (Prototyp). Später: database (PostgreSQL, wie IDA ADR-003).
    /*
    | Version (Datei VERSION) und Build aus dem Deployment (Commit, Zeitpunkt).
    | Änderungen stehen in CHANGELOG.md und auf der Seite «Versionen».
    */
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',
    'build' => env('INTRANET_BUILD'),
    'build_date' => env('INTRANET_BUILD_DATE'),

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

    // Steht der Server in der Schweiz? Solange nicht, warnen die Plugins vor echten Personendaten
    // (IDA ADR-005: produktiv Infomaniak). Auf dem Hostinger-Prototyp: false.
    'server_in_switzerland' => (bool) env('INTRANET_SERVER_IN_SWITZERLAND', false),

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

    // Domain der Schüler-Konten (Geräteausgabe: Benutzername vorname.nachname@…)
    'student_mail_domain' => env('INTRANET_STUDENT_MAIL_DOMAIN', 'edu.sekbuelach.ch'),

    // Rollenkonzept (docs/KONZEPT-ROLLEN.md): eine Rolle je Person im Prototyp, später aus
    // Entra-ID-Gruppen (mehrere möglich, Rechte werden zusammengezählt).
    'roles' => [
        'admin' => 'Administration',
        'schulleitung' => 'Schulleitung',
        'verwaltung' => 'Schulverwaltung',
        'ict' => 'Fachstelle ICT',
        'redaktion' => 'Redaktion',
        'lehrperson' => 'Lehrperson',
        'mitarbeitende' => 'Mitarbeitende',
        'kein-zugang' => 'Kein Zugang',
    ],

    // Systeme für Datenherkunft und Synchronisation (Personen → Datenherkunft). Liste erweiterbar.
    'sync_systems' => [
        'escola' => 'Escola',
        'entra' => 'Microsoft 365 / Entra ID',
        'jamf' => 'Jamf Pro',
        'ida' => 'IDA',
        'inventory360' => 'Inventory360',
        'edulog' => 'Edulog',
        'datei' => 'Datei-Import (Excel/CSV)',
        'intranet' => 'Intranet (manuell erfasst)',
    ],

    // Art der Person (Kontakt), unabhängig von den Rechten im Intranet. Liste erweiterbar.
    'person_types' => [
        'schueler' => 'Schüler:in',
        'eltern' => 'Eltern / Erziehungsberechtigte',
        'lehrperson' => 'Lehrperson',
        'schulleitung' => 'Schulleitung',
        'mitarbeitende' => 'Mitarbeiter:in',
        'behoerde' => 'Schulbehörde',
        'extern' => 'Externe Fachperson',
    ],
    // Diese Arten können sich anmelden (Personal); alle anderen haben keinen Zugang zum Intranet
    'login_person_types' => ['lehrperson', 'schulleitung', 'mitarbeitende', 'behoerde'],
    // Diese Arten sehen nur Personen mit dem Recht «view-students»
    'protected_person_types' => ['schueler', 'eltern'],

    // Beschreibung je Rolle (Seite Prototyp → Rollenkonzept)
    'role_descriptions' => [
        'admin' => 'Betreibt das Intranet: Personen, Plugins, Einstellungen, Prototyp. Wenige Personen.',
        'schulleitung' => 'Leitet die Schule: Inhalte für alle, Verwaltungsabläufe, Sicht auf Unterricht und Personal.',
        'verwaltung' => 'Sekretariat und Schulverwaltung: Workflows, Bestellungen, Personalverzeichnis lesen.',
        'ict' => 'Fachstelle ICT: Geräte ausgeben und zurücknehmen, Lizenzen, ICT-Anwendungen.',
        'redaktion' => 'Pflegt Mitteilungen, Termine und Dokumente für alle.',
        'lehrperson' => 'Unterrichtet: Unterricht, Bestellungen für Klasse und Zimmer, Inhalte lesen.',
        'mitarbeitende' => 'Weitere Mitarbeitende (Hausdienst, Betreuung, Assistenz): Inhalte lesen.',
        'kein-zugang' => 'Ohne Anmeldung im Intranet, z. B. Schüler:innen und Eltern (nur als Kontakt erfasst).',
    ],

    // Rechte (Gates) → Rollen, die es haben. Menüs und Routen fragen nur Rechte ab, nie Rollen.
    'abilities' => [
        'manage-users' => ['label' => 'Personen und Intranet verwalten', 'roles' => ['admin']],
        'manage-content' => ['label' => 'Mitteilungen, Termine, Dokumente bearbeiten', 'roles' => ['admin', 'schulleitung', 'redaktion']],
        'use-ict' => ['label' => 'Fachstelle ICT (Geräte, Lizenzen, Zugangsdaten)', 'roles' => ['admin', 'ict']],
        'use-verwaltung' => ['label' => 'Verwaltung (Workflows)', 'roles' => ['admin', 'schulleitung', 'verwaltung']],
        'use-unterricht' => ['label' => 'Unterricht (Klassen, Stundenpläne, Absenzen)', 'roles' => ['admin', 'schulleitung', 'lehrperson', 'ict']],
        'order' => ['label' => 'Bestellen (Ausrüstung, Lehrmittel, Anträge)', 'roles' => ['admin', 'schulleitung', 'verwaltung', 'ict', 'lehrperson']],
        'view-students' => ['label' => 'Schüler:innen und Eltern sehen', 'roles' => ['admin', 'schulleitung', 'verwaltung', 'ict', 'lehrperson']],
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
    | Module und Navigation
    |--------------------------------------------------------------------------
    | Gruppen erscheinen in der Seitenleiste ein- und ausklappbar. Ein Modul mit
    | «href» ist umgesetzt; ohne «href» ist es ein Platzhalter (/module/<schlüssel>)
    | mit Beschreibung und geplanten Funktionen. «can» blendet Gruppe oder Modul
    | nur mit dieser Berechtigung ein (Gate). «color» = Farbe aus dem Farbkonzept
    | (Punkt und Symbole der Gruppe). «icon» = Name eines Lucide-Symbols,
    | muss in resources/js/lib/module-icons.ts eingetragen sein.
    | Bewusst nicht vorgesehen: Noten, Zeugnisse, Förderplanung – das bleibt im
    | Schulverwaltungssystem (besonders schützenswerte Personendaten).
    */

    'modules' => [
        'kommunikation' => [
            'title' => 'Kommunikation',
            'color' => 'sky',
            'icon' => 'MessagesSquare',
            'items' => [
                'start' => ['title' => 'Start', 'icon' => 'LayoutGrid', 'href' => '/start'],
                'mitteilungen' => ['title' => 'Mitteilungen', 'icon' => 'Megaphone', 'href' => '/mitteilungen'],
                'termine' => ['title' => 'Termine', 'icon' => 'CalendarDays', 'href' => '/termine'],
                'pinnwand' => [
                    'title' => 'Pinnwand',
                    'icon' => 'StickyNote',
                    'description' => 'Digitales schwarzes Brett für kurze Hinweise, Fundsachen und Suche/Biete im Kollegium.',
                    'features' => ['Beiträge mit Ablaufdatum', 'Kommentare', 'Filter nach Schulhaus'],
                ],
                'umfragen' => [
                    'title' => 'Umfragen',
                    'icon' => 'Vote',
                    'description' => 'Kurze Abfragen im Kollegium, zum Beispiel Terminfindung oder Anmeldung für Weiterbildungen.',
                    'features' => ['Einfach- und Mehrfachauswahl', 'Anonym oder namentlich', 'Auswertung als Liste'],
                ],
            ],
        ],
        // Fachanwendungen: eigenes Hauptmenü; beim Öffnen einer Anwendung klappt das Seitenmenü ein
        'fachanwendungen' => [
            'title' => 'Fachanwendungen',
            'color' => 'brand',
            'icon' => 'AppWindow',
            'collapse_sidebar' => true,
            'items' => [
                'cmi' => [
                    'title' => 'CMI',
                    'icon' => 'Building2',
                    'description' => 'CMI (Geschäfts- und Schulverwaltung). Adresse der eigenen Instanz in der Server-.env (INTRANET_APP_CMI_URL).',
                    'embed' => ['url' => env('INTRANET_APP_CMI_URL'), 'open' => 'both'],
                ],
                'escola' => [
                    'title' => 'Escola',
                    'icon' => 'School',
                    'description' => 'Schulverwaltung Escola. Escola erlaubt das Einbetten nur auf www.sekbuelach.ch selbst (frame-ancestors \'self\') – deshalb eigener Tab bzw. Browser-Erweiterung.',
                    // system: IDs in Adressen der Anwendung mit der Datenherkunft (PersonLinks) abgleichen
                    'embed' => ['url' => env('INTRANET_APP_ESCOLA_URL', 'https://www.sekbuelach.ch/escola/login'), 'open' => 'tab', 'system' => 'escola'],
                ],
                'apple-school-manager' => [
                    'title' => 'Apple School Manager',
                    'icon' => 'Apple',
                    'description' => 'Apple School Manager (Geräte, verwaltete Apple-Accounts, Apps und Bücher). Apple erlaubt kein Einbetten – öffnet in neuem Tab.',
                    'embed' => ['url' => 'https://school.apple.com', 'open' => 'tab'],
                ],
                'jamf-pro' => [
                    'title' => 'Jamf Pro',
                    'icon' => 'Tablet',
                    'description' => 'Jamf Pro (Geräteverwaltung iPads und Macs). Adresse der eigenen Instanz in der Server-.env (INTRANET_APP_JAMF_URL); Daten im Intranet über das Plugin «Jamf Pro».',
                    'embed' => ['url' => env('INTRANET_APP_JAMF_URL'), 'open' => 'both', 'system' => 'jamf'],
                ],
                'lmvz-digital' => [
                    'title' => 'LMVZ Digital',
                    'icon' => 'BookMarked',
                    'description' => 'Lehrmittel-Lizenzen des LMVZ (inoffiziell). Über die Browser-Erweiterung: Lizenzübersicht mit der Anmeldung im Tab abrufen – ohne Token im Intranet.',
                    'embed' => ['url' => 'https://digital.lmvz.ch', 'open' => 'extension'],
                ],
            ],
        ],
        'wissen' => [
            'title' => 'Wissen & Ablage',
            'color' => 'violet',
            'icon' => 'Library',
            'items' => [
                'dokumente' => ['title' => 'Dokumente & Links', 'icon' => 'FileText', 'href' => '/dokumente'],
                'handbuch' => [
                    'title' => 'Handbuch',
                    'icon' => 'BookOpen',
                    'description' => 'Schulhandbuch mit Abläufen, Zuständigkeiten und Weisungen, durchsuchbar und versioniert.',
                    'features' => ['Kapitel und Unterseiten', 'Volltextsuche', 'Änderungsverlauf', 'Verantwortliche je Kapitel'],
                ],
                'vorlagen' => [
                    'title' => 'Vorlagen',
                    'icon' => 'FileStack',
                    'description' => 'Briefe und Formulare mit Schullogo, die mit Daten aus dem Intranet befüllt werden (Serienbriefe).',
                    'features' => ['Word-Vorlagen mit Platzhaltern', 'PDF-Ausgabe', 'Vorlagen je Schulhaus'],
                ],
                'lehrplan21' => [
                    'title' => 'Lehrplan 21',
                    'icon' => 'GraduationCap',
                    'description' => 'Lehrplan 21 des Kantons Zürich: Fachbereiche, Kompetenzen und Lehrmittel.',
                    'embed' => ['url' => 'https://zh.lehrplan.ch/index.php', 'open' => 'both'],
                ],
            ],
        ],
        'organisation' => [
            'title' => 'Personen & Organisation',
            'color' => 'teal',
            'icon' => 'Building2',
            'items' => [
                'personen' => ['title' => 'Personen', 'icon' => 'Users', 'href' => '/personen'],
                'schueler' => ['title' => 'Schüler:innen', 'icon' => 'GraduationCap', 'href' => '/schueler', 'can' => 'view-students'],
                'klassen' => [
                    'title' => 'Klassen & Gruppen',
                    'icon' => 'UsersRound',
                    'description' => 'Klassen, Fachgruppen und Teams mit Zuständigkeiten (Klassenlehrperson, Fachlehrpersonen).',
                    'features' => ['Klassen je Schuljahr und Schulhaus', 'Arbeitsgruppen und Teams', 'Abgleich mit Microsoft 365 über das Entra-Plugin'],
                    'plugin' => 'entra',
                ],
                'abwesenheiten' => [
                    'title' => 'Abwesenheiten & Vertretungen',
                    'icon' => 'CalendarOff',
                    'description' => 'Abwesenheiten von Mitarbeitenden melden und Vertretungen planen.',
                    'features' => ['Meldung durch Mitarbeitende', 'Vertretungsplan je Tag', 'Übersicht für die Schulleitung'],
                ],
                'telefonliste' => [
                    'title' => 'Telefonliste',
                    'icon' => 'Phone',
                    'description' => 'Aktuelle Telefon- und Alarmliste des Personals, als PDF zum Ausdrucken.',
                    'features' => ['Gefiltert nach Schulhaus und Funktion', 'PDF- und Excel-Export', 'Notfallnummern'],
                ],
            ],
        ],
        'unterricht' => [
            'title' => 'Unterricht',
            'color' => 'amber',
            'icon' => 'School',
            'can' => 'use-unterricht',
            'items' => [
                'stundenplan' => [
                    'title' => 'Stundenpläne',
                    'icon' => 'CalendarClock',
                    'description' => 'Stundenpläne von Klassen, Lehrpersonen und Räumen anzeigen.',
                    'features' => ['Ansicht je Klasse, Person und Raum', 'Stundenplanänderungen', 'Import aus dem Stundenplanprogramm'],
                ],
                'absenzen' => [
                    'title' => 'Absenzen',
                    'icon' => 'UserX',
                    'description' => 'Absenzen von Schülerinnen und Schülern – im Intranet nur Verweis auf das Schulverwaltungssystem.',
                    'features' => ['Direktlink ins Schulverwaltungssystem', 'Keine Speicherung von Schülerdaten im Intranet'],
                ],
                'checklisten' => [
                    'title' => 'Checklisten',
                    'icon' => 'ListChecks',
                    'description' => 'Wiederkehrende Abläufe als Checkliste, etwa Schuljahresbeginn, Klassenlager oder Eintritt neuer Lehrpersonen.',
                    'features' => ['Vorlagen für Abläufe', 'Abhaken und teilen', 'Fälligkeiten'],
                ],
            ],
        ],
        'ressourcen' => [
            'title' => 'Räume & Geräte',
            'color' => 'indigo',
            'icon' => 'Boxes',
            'items' => [
                'reservationen' => [
                    'title' => 'Reservationen',
                    'icon' => 'CalendarCheck',
                    'description' => 'Räume, Geräte und Fahrzeuge reservieren (Aula, Turnhalle, iPad-Koffer, Beamer).',
                    'features' => ['Kalender je Ressource', 'Konflikte erkennen', 'Freigabe durch Hauswartung'],
                ],
                'geraete' => [
                    'title' => 'Geräte',
                    'icon' => 'Tablet',
                    'description' => 'Geräte je Person und Klasse, Daten aus Jamf und Inventory360 über die Plugins.',
                    'features' => ['Meine Geräte', 'Geräte je Klasse', 'Defekt melden'],
                    'plugin' => 'jamf',
                ],
                'lizenzen' => [
                    'title' => 'Lizenzen',
                    'icon' => 'KeyRound',
                    'description' => 'Software-Lizenzen und Bestellungen, später aus IDA.',
                    'features' => ['Übersicht je Person und Klasse', 'Bestellung beantragen', 'Anbindung über das IDA-Plugin'],
                    'plugin' => 'ida',
                ],
            ],
        ],
        'anlaesse' => [
            'title' => 'Anlässe & Angebote',
            'color' => 'rose',
            'icon' => 'PartyPopper',
            'items' => [
                'sporttag' => [
                    'title' => 'Sporttag',
                    'icon' => 'Trophy',
                    'description' => 'Sporttag und Projektwochen organisieren: Posten, Einteilung der Helferinnen und Helfer, Ranglisten.',
                    'features' => ['Posten und Zeitplan', 'Helfereinsatz', 'Ranglisten'],
                ],
                'kurse' => [
                    'title' => 'Kurse & Wahlfächer',
                    'icon' => 'GraduationCap',
                    'description' => 'Freifächer, Wahlfächer und interne Weiterbildungen mit Anmeldung.',
                    'features' => ['Kursangebot', 'Anmeldung und Wartelisten', 'Teilnehmerlisten'],
                ],
            ],
        ],
        'bestellen' => [
            'title' => 'Bestellen',
            'color' => 'orange',
            'icon' => 'ShoppingCart',
            'can' => 'order',
            'items' => [
                'persoenliche-ausruestung' => [
                    'title' => 'Persönliche Ausrüstung (HW)',
                    'icon' => 'Laptop',
                    'description' => 'Persönliche Geräte für Mitarbeitende bestellen: Notebook, iPad, Zubehör.',
                    'features' => ['Katalog mit Standardgeräten', 'Bestellung mit Begründung', 'Freigabe durch Schulleitung', 'Status bis zur Ausgabe'],
                    'plugin' => 'ida',
                ],
                'zimmer-ausruestung' => [
                    'title' => 'Zimmerausrüstung (HW)',
                    'icon' => 'Projector',
                    'description' => 'Ausrüstung für Schulzimmer bestellen oder ersetzen: Beamer, Dokumentenkamera, Lautsprecher.',
                    'features' => ['Auswahl nach Zimmer', 'Bestehende Ausrüstung aus dem Inventar', 'Defekt melden und Ersatz bestellen'],
                    'plugin' => 'inventory360',
                ],
                'lehrmittel' => [
                    'title' => 'Lehrmittel',
                    'icon' => 'BookMarked',
                    'description' => 'Lehrmittel für Klassen bestellen, gedruckt und digital.',
                    'features' => ['Bestellung je Klasse und Fach', 'Digitale Lizenzen für Schülerinnen und Schüler', 'Sammelbestellung zum Schuljahresbeginn'],
                    'plugin' => 'ida',
                ],
                'schulplattformen' => [
                    'title' => 'Schulplattformen',
                    'icon' => 'Globe',
                    'description' => 'Zugänge zu Lernplattformen für Klassen beantragen.',
                    'features' => ['Plattformen mit Datenschutz-Freigabe', 'Zugänge je Klasse', 'Automatische Benutzeranlage über IDA'],
                    'plugin' => 'ida',
                ],
                'fachanwendungen' => [
                    'title' => 'Fachanwendungen',
                    'icon' => 'AppWindow',
                    'description' => 'Software für einzelne Fächer oder Funktionen beantragen.',
                    'features' => ['Liste freigegebener Anwendungen', 'Lizenzanzahl und Laufzeit', 'Verteilung auf Geräte über Jamf'],
                    'plugin' => 'jamf',
                ],
                'antrag-hw-sw' => [
                    'title' => 'Antrag neue HW/SW',
                    'icon' => 'FilePlus2',
                    'description' => 'Neue Hardware oder Software beantragen, die noch nicht im Angebot ist.',
                    'features' => ['Antrag mit pädagogischem Nutzen', 'Prüfung Datenschutz und Kosten durch die Fachstelle ICT', 'Entscheid und Rückmeldung'],
                ],
            ],
        ],
        'verwaltung' => [
            'title' => 'Verwaltung',
            'color' => 'emerald',
            'icon' => 'Landmark',
            'items' => [
                'workflows' => [
                    'title' => 'Workflows',
                    'can' => 'use-verwaltung',
                    'icon' => 'Workflow',
                    'description' => 'SharePoint-Seite «Leitung und Verwaltung» mit Abläufen und Formularen.',
                    'embed' => ['url' => 'https://sekbuelach.sharepoint.com/sites/LeitungundVerwaltung', 'open' => 'both'],
                ],
                'tickets' => [
                    'title' => 'Tickets',
                    'icon' => 'Ticket',
                    'description' => 'Anliegen an Verwaltung und ICT über das Kundenportal melden.',
                    'embed' => ['url' => 'https://sekbuelach.atlassian.net/servicedesk/customer/portal/2', 'open' => 'both'],
                ],
                'elternportal' => [
                    'title' => 'Elternportal',
                    'icon' => 'UsersRound',
                    'description' => 'Portal für Anliegen von Eltern und Erziehungsberechtigten.',
                    'embed' => ['url' => 'https://sekbuelach.atlassian.net/servicedesk/customer/portal/3', 'open' => 'both'],
                ],
            ],
        ],
        'ict' => [
            'title' => 'Fachstelle ICT',
            'color' => 'cyan',
            'icon' => 'MonitorSmartphone',
            'can' => 'use-ict',
            'items' => [
                'geraeteausgabe' => [
                    'title' => 'Geräteausgabe',
                    'icon' => 'PackagePlus',
                    'href' => '/geraeteausgabe',
                    'description' => 'Geräte an Mitarbeitende oder Schülerinnen und Schüler ausgeben, mit Quittung.',
                    'features' => ['Gerät per Seriennummer oder Scan wählen', 'Zuweisung in Jamf (Benutzer und Standort)', 'Ausgabequittung als PDF', 'Offene Bestellungen übernehmen'],
                    'plugin' => 'jamf',
                ],
                'geraeteruecknahme' => [
                    'title' => 'Geräterücknahme',
                    'icon' => 'PackageMinus',
                    'description' => 'Geräte bei Austritt oder Ersatz zurücknehmen, Zustand erfassen und zurücksetzen.',
                    'features' => ['Liste der Austritte mit Geräten (Ereignis Person inaktiv)', 'Zustand und Zubehör prüfen', 'Gerät in Jamf zurücksetzen', 'Rücknahmequittung'],
                    'plugin' => 'jamf',
                ],
                'lizenzzuweisung' => [
                    'title' => 'Lizenzzuweisung',
                    'icon' => 'BadgeCheck',
                    'description' => 'Software-Lizenzen Personen, Klassen und Geräten zuweisen und freigeben.',
                    'features' => ['Freie und belegte Lizenzen', 'Zuweisung an Personen und Klassen', 'Automatisch beim Ein- und Austritt (IDA)', 'Ablaufende Lizenzen'],
                    'plugin' => 'ida',
                ],
                // Eingebundene Anwendungen (eigene Projekte auf GitHub, laufen als eigener Dienst)
                // und Atlassian-Seiten. «embed»: url, open (embed | tab | both), repo, stack.
                // Adresse der Anwendung über .env, solange sie nicht betrieben wird: null.
                'websnapshot' => [
                    'title' => 'Webseiten-Snapshot',
                    'icon' => 'Camera',
                    'description' => 'Webseiten erfassen, Versionen vergleichen und Änderungsberichte erstellen.',
                    'embed' => ['url' => env('INTRANET_APP_WEBSNAPSHOT_URL'), 'open' => 'both', 'repo' => 'MarkusKeller8200/websnapshot', 'stack' => 'Python, Streamlit, SQLite'],
                ],
                'rechnung' => [
                    'title' => 'Rechnung',
                    'icon' => 'Receipt',
                    'description' => 'Rechnungsablauf (Projekt abaflow).',
                    'embed' => ['url' => env('INTRANET_APP_ABAFLOW_URL'), 'open' => 'both', 'repo' => 'MaKeSekB/abaflow'],
                ],
                'ansagetexte' => [
                    'title' => 'Telefonie Ansagetexte',
                    'icon' => 'AudioLines',
                    'description' => 'Ansagetexte für die 3CX-Telefonanlage (Text-to-Speech).',
                    'embed' => ['url' => env('INTRANET_APP_TTS_URL'), 'open' => 'both', 'repo' => 'sekbuelach/3CX-SEKB-TTS'],
                ],
                'lmvz-shop' => [
                    'title' => 'LMVZ Shop (Prototyp)',
                    'icon' => 'Store',
                    'description' => 'Lizenzen für Lehrmittel bestellen (Projekt edu-license-portal).',
                    'embed' => ['url' => env('INTRANET_APP_LICENSE_PORTAL_URL'), 'open' => 'both', 'repo' => 'sekbuelach/edu-license-portal'],
                ],
                'servicedesk' => [
                    'title' => 'Jira Service Desk',
                    'icon' => 'LifeBuoy',
                    'description' => 'Warteschlange der ICT-Tickets in Jira Service Management.',
                    'embed' => ['url' => 'https://sekbuelach.atlassian.net/jira/servicedesk/projects/ICT/queues/custom/286', 'open' => 'both'],
                ],
                'confluence-ict' => [
                    'title' => 'Confluence',
                    'icon' => 'BookText',
                    'description' => 'Wissensbereich ICT in Confluence.',
                    'embed' => ['url' => 'https://sekbuelach.atlassian.net/wiki/spaces/ICT/overview', 'open' => 'both'],
                ],
            ],
        ],
        // Einstellungen der einzelnen Menüpunkte (nur Administration), gespeichert in module_settings
        'einstellungen' => [
            'title' => 'Einstellungen',
            'color' => 'slate',
            'icon' => 'Settings',
            'can' => 'manage-users',
            'items' => [
                'einstellungen-fachanwendungen' => ['title' => 'Fachanwendungen', 'icon' => 'AppWindow', 'href' => '/einstellungen/fachanwendungen'],
                'einstellungen-einbindungen' => ['title' => 'Weitere Einbindungen', 'icon' => 'Globe', 'href' => '/einstellungen/einbindungen'],
                'browser-erweiterung' => ['title' => 'Browser-Erweiterung', 'icon' => 'Puzzle', 'href' => '/einstellungen/browser-erweiterung'],
            ],
        ],
        'prototyp' => [
            'title' => 'Prototyp',
            'color' => 'slate',
            'icon' => 'FlaskConical',
            'can' => 'manage-users',
            'items' => [
                'plugins' => ['title' => 'Plugins', 'icon' => 'Plug', 'href' => '/plugins'],
                'datenstrukturen' => ['title' => 'Datenstrukturen', 'icon' => 'Database', 'href' => '/datenstrukturen'],
                'farbkonzept' => ['title' => 'Farbkonzept', 'icon' => 'Palette', 'href' => '/farbkonzept'],
                'api' => ['title' => 'API', 'icon' => 'Braces', 'href' => '/api-doku'],
                'api-recorder' => ['title' => 'API-Recorder', 'icon' => 'Radio', 'href' => '/api-recorder'],
                'mcp' => ['title' => 'MCP-Schnittstelle', 'icon' => 'Bot', 'href' => '/mcp-konzept'],
                'sprachkonzept' => ['title' => 'Sprachkonzept', 'icon' => 'Languages', 'href' => '/sprachkonzept'],
                'rollenkonzept' => ['title' => 'Rollenkonzept', 'icon' => 'ShieldCheck', 'href' => '/rollenkonzept'],
            ],
        ],
        'gui' => [
            'title' => 'GUI-Prototypen',
            'color' => 'indigo',
            'icon' => 'LayoutTemplate',
            'can' => 'manage-users',
            'items' => ['gui-uebersicht' => ['title' => 'Übersicht & Freigaben', 'icon' => 'LayoutList', 'href' => '/gui-prototypen']]
                + array_combine(
                    array_map(fn (string $k): string => 'gui-'.$k, array_keys($guiComponents)),
                    array_map(fn (string $k, array $c): array => ['title' => $c['title'], 'icon' => $c['icon'], 'href' => '/gui-prototypen/'.$k], array_keys($guiComponents), $guiComponents),
                ),
        ],
    ],

    'gui_components' => $guiComponents,

    // Status der GUI-Prototypen (Reihenfolge = Ablauf)
    'gui_statuses' => [
        'entwicklung' => 'Entwicklung',
        'pruefung' => 'Prüfung',
        'freigegeben' => 'Freigegeben',
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
            'schulleitung' => 'violet',
            'verwaltung' => 'emerald',
            'ict' => 'cyan',
            'redaktion' => 'indigo',
            'lehrperson' => 'amber',
            'mitarbeitende' => 'slate',
            'kein-zugang' => 'teal',
        ],
        'gui_statuses' => [
            'entwicklung' => 'amber',
            'pruefung' => 'sky',
            'freigegeben' => 'emerald',
        ],
        'person_types' => [
            'schueler' => 'sky',
            'eltern' => 'orange',
            'lehrperson' => 'amber',
            'schulleitung' => 'violet',
            'mitarbeitende' => 'slate',
            'behoerde' => 'indigo',
            'extern' => 'teal',
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

    // Demo-Personen auf das Rollenkonzept umstellen (nur @sekbuelach.example, nur wenn noch alte Rolle)
    'demo_roles' => [
        ['beat.beispiel@sekbuelach.example', 'redaktion', 'schulleitung'],
        ['gabriela.vorlage@sekbuelach.example', 'mitarbeitende', 'verwaltung'],
        ['daniel.probe@sekbuelach.example', 'mitarbeitende', 'lehrperson'],
        ['eva.fiktiv@sekbuelach.example', 'mitarbeitende', 'lehrperson'],
        ['fabian.platzhalter@sekbuelach.example', 'mitarbeitende', 'ict'],
    ],

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
    | Typen: uuid, string, text, email, url, bool, int, date, datetime, enum, ref, json.
    | Jede Sammlung erhält automatisch id (uuid v7), created_at, updated_at.
    | Wird ein Feld geändert, hier nachführen: Die Seite «Datenstrukturen»
    | vergleicht die gespeicherten Datensätze mit diesem Schema.
    */

    'schema' => [

        'users' => [
            'label' => 'Personen',
            'description' => 'Personen und Kontakte (Personal, Schüler:innen, Eltern …); Personal zugleich Benutzerkonten.',
            // Gruppen der Detailansicht und des Formulars (Reihenfolge = Anzeige).
            // «admin»: nur für die Administration sichtbar, wird anderen nie gesendet.
            'groups' => [
                'person' => ['label' => 'Person', 'description' => 'Name und Status'],
                'organisation' => ['label' => 'Organisation', 'description' => 'Schulhaus, Funktion, Klasse und Gerät'],
                'contact' => ['label' => 'Kontakt Schule', 'description' => 'Geschäftliche Erreichbarkeit'],
                'private' => ['label' => 'Privat & Notfall', 'description' => 'Nur für die Administration sichtbar', 'admin' => true],
                'system' => ['label' => 'Intranet & Systeme', 'description' => 'Berechtigung und Verknüpfung mit Escola', 'admin' => true],
            ],
            // Bewusst nicht vorgesehen (besonders schützenswert oder nicht nötig): AHV-Nummer,
            // Religion/Konfession, Nationalität, Heimatort, Aufenthaltsbewilligung, Zivilstand,
            // Gesundheit, Ernährung, IBAN/BIC, Noten, Förderangaben.
            // Schüler:innen und Eltern: nur Kontakt, Klasse und Gerät; echte Daten nur auf dem
            // Schweizer Server (ADR-005), im Prototyp nur erfundene.
            'fields' => [
                'title' => ['type' => 'string', 'required' => false, 'label' => 'Titel', 'group' => 'person'],
                'first_name' => ['type' => 'string', 'required' => false, 'label' => 'Vorname', 'group' => 'person'],
                'last_name' => ['type' => 'string', 'required' => true, 'label' => 'Nachname', 'group' => 'person'],
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name (Anzeige)', 'group' => 'person', 'computed' => true],
                'short' => ['type' => 'string', 'required' => false, 'label' => 'Kürzel', 'group' => 'person', 'max' => 20],
                'active' => ['type' => 'bool', 'required' => true, 'label' => 'Aktiv', 'group' => 'person'],
                'person_type' => ['type' => 'enum', 'values' => 'person_types', 'required' => true, 'label' => 'Personenart', 'group' => 'person'],

                'school' => ['type' => 'enum', 'values' => 'schools', 'required' => true, 'label' => 'Schulhaus', 'group' => 'organisation'],
                'function' => ['type' => 'string', 'required' => false, 'label' => 'Funktion', 'group' => 'organisation'],
                'department' => ['type' => 'string', 'required' => false, 'label' => 'Abteilung / Stufe', 'group' => 'organisation'],
                'class_name' => ['type' => 'string', 'required' => false, 'label' => 'Klasse', 'group' => 'organisation', 'max' => 30],
                'device_inventory' => ['type' => 'string', 'required' => false, 'label' => 'iPad-/Geräte-Nr.', 'group' => 'organisation', 'max' => 50],
                'escola_roles' => ['type' => 'string', 'required' => false, 'label' => 'Rollen (Escola)', 'group' => 'organisation'],
                'entry_date' => ['type' => 'date', 'required' => false, 'label' => 'Eintritt', 'group' => 'organisation'],
                'exit_date' => ['type' => 'date', 'required' => false, 'label' => 'Austritt', 'group' => 'organisation'],

                'email' => ['type' => 'email', 'required' => false, 'unique' => true, 'label' => 'E-Mail', 'group' => 'contact'],
                'phone' => ['type' => 'string', 'required' => false, 'label' => 'Telefon', 'group' => 'contact', 'input' => 'tel', 'max' => 50],
                'phone2' => ['type' => 'string', 'required' => false, 'label' => 'Telefon 2', 'group' => 'contact', 'input' => 'tel', 'max' => 50],
                'mobile' => ['type' => 'string', 'required' => false, 'label' => 'Mobiltelefon', 'group' => 'contact', 'input' => 'tel', 'max' => 50],
                'office_street' => ['type' => 'string', 'required' => false, 'label' => 'Strasse (Schule)', 'group' => 'contact'],
                'office_zip' => ['type' => 'string', 'required' => false, 'label' => 'PLZ (Schule)', 'group' => 'contact', 'max' => 10],
                'office_city' => ['type' => 'string', 'required' => false, 'label' => 'Ort (Schule)', 'group' => 'contact'],

                'private_email' => ['type' => 'email', 'required' => false, 'label' => 'E-Mail privat', 'group' => 'private'],
                'private_phone' => ['type' => 'string', 'required' => false, 'label' => 'Telefon privat', 'group' => 'private', 'input' => 'tel', 'max' => 50],
                'private_mobile' => ['type' => 'string', 'required' => false, 'label' => 'Mobiltelefon privat', 'group' => 'private', 'input' => 'tel', 'max' => 50],
                'private_street' => ['type' => 'string', 'required' => false, 'label' => 'Strasse privat', 'group' => 'private'],
                'private_zip' => ['type' => 'string', 'required' => false, 'label' => 'PLZ privat', 'group' => 'private', 'max' => 10],
                'private_city' => ['type' => 'string', 'required' => false, 'label' => 'Ort privat', 'group' => 'private'],
                'emergency_name' => ['type' => 'string', 'required' => false, 'label' => 'Notfallkontakt (Person)', 'group' => 'private'],
                'emergency_phone' => ['type' => 'string', 'required' => false, 'label' => 'Notfallkontakt (Nummer)', 'group' => 'private', 'input' => 'tel', 'max' => 50],

                'role' => ['type' => 'enum', 'values' => 'roles', 'required' => true, 'label' => 'Rolle im Intranet', 'group' => 'system'],
                'username' => ['type' => 'string', 'required' => false, 'label' => 'Benutzername (Escola)', 'group' => 'system'],
                'escola_id' => ['type' => 'string', 'required' => false, 'label' => 'Escola-ID', 'group' => 'system', 'max' => 50],
                'external_id' => ['type' => 'string', 'required' => false, 'label' => 'Fremd-ID', 'group' => 'system', 'max' => 100],
                'notes' => ['type' => 'text', 'required' => false, 'label' => 'Bemerkungen', 'group' => 'system'],
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

        'plugin_states' => [
            'label' => 'System: Plugin-Zustand',
            'description' => 'Ein Datensatz je Plugin (id = Plugin-ID). Zugangsdaten mit APP_KEY verschlüsselt.',
            'fields' => [
                'enabled' => ['type' => 'bool', 'required' => true, 'label' => 'Eingeschaltet'],
                'allow_write' => ['type' => 'bool', 'required' => true, 'label' => 'Schreibende Aktionen erlaubt'],
                'settings' => ['type' => 'json', 'required' => false, 'label' => 'Einstellungen'],
                'secrets' => ['type' => 'json', 'required' => false, 'label' => 'Zugangsdaten (verschlüsselt)'],
            ],
        ],

        'plugin_runs' => [
            'label' => 'System: Plugin-Protokoll',
            'description' => 'Letzte Läufe aller Plugins (höchstens 300), Zugangsdaten geschwärzt.',
            'fields' => [
                'plugin' => ['type' => 'string', 'required' => true, 'label' => 'Plugin'],
                'label' => ['type' => 'string', 'required' => true, 'label' => 'Vorgang'],
                'status' => ['type' => 'enum', 'values' => ['success' => 'Erfolg', 'warning' => 'Warnung', 'error' => 'Fehler'], 'required' => true, 'label' => 'Ergebnis'],
                'message' => ['type' => 'text', 'required' => false, 'label' => 'Meldung'],
                'log' => ['type' => 'json', 'required' => false, 'label' => 'Protokollzeilen'],
                'user_id' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Ausgelöst von'],
                'user_name' => ['type' => 'string', 'required' => false, 'label' => 'Name (Momentaufnahme)'],
                'ms' => ['type' => 'int', 'required' => true, 'label' => 'Dauer (ms)'],
            ],
        ],

        'gui_components' => [
            'label' => 'System: GUI-Prototypen',
            'description' => 'Menü «GUI-Prototypen»: Versionen je Baustein mit Status (Entwicklung, Prüfung, Freigegeben) und Datum je Status, Verbesserungswünsche und die nach einer Freigabe zu überarbeitenden Seiten.',
            'fields' => [
                'key' => ['type' => 'string', 'required' => true, 'label' => 'Baustein (Schlüssel aus gui_components)'],
                'versions' => ['type' => 'json', 'required' => true, 'label' => 'Versionen [{version, status, changes, created_at/by, review_at/by, released_at/by, log}]'],
                'wishes' => ['type' => 'json', 'required' => false, 'label' => 'Verbesserungswünsche [{id, text, by, at, version}]'],
                'rework' => ['type' => 'json', 'required' => false, 'label' => 'Überarbeitung nach Freigabe [{file, version, done_at, done_by}]'],
            ],
        ],
        'module_settings' => [
            'label' => 'System: Einstellungen der Menüpunkte',
            'description' => 'Menü «Einstellungen»: Adresse und Art des Öffnens je Fachanwendung bzw. Einbindung. Übersteuert config/intranet.php und die .env.',
            'fields' => [
                'key' => ['type' => 'string', 'required' => true, 'label' => 'Menüpunkt (Schlüssel)'],
                'url' => ['type' => 'string', 'required' => false, 'label' => 'Adresse (https)'],
                'open' => ['type' => 'string', 'required' => false, 'label' => 'Öffnen (embed, both, tab, extension)'],
                'updated_by' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Geändert von'],
            ],
        ],
        'api_docs' => [
            'label' => 'System: API-Dokumentationen (API-Recorder)',
            'description' => 'Mit dem API-Recorder erfasste Endpunkte fremder Websites: Methode, Pfad, abgeleitete Schemas, optional maskierte Beispiele. Keine Rohdaten.',
            'fields' => [
                'title' => ['type' => 'string', 'required' => true, 'label' => 'Titel'],
                'start_url' => ['type' => 'string', 'required' => false, 'label' => 'Start-URL (Aufzeichnung)'],
                'base_url' => ['type' => 'string', 'required' => false, 'label' => 'Basis-URL'],
                'description' => ['type' => 'text', 'required' => false, 'label' => 'Beschreibung'],
                'plugin_id' => ['type' => 'string', 'required' => false, 'label' => 'Zugewiesenes Plugin'],
                'operations' => ['type' => 'json', 'required' => true, 'label' => 'Endpunkte (Methode, Pfad, Schemas)'],
                'recorded_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Zuletzt neue Endpunkte'],
                'owner_id' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Erstellt von'],
            ],
        ],

        'person_links' => [
            'label' => 'Personen: Datenherkunft & Synchronisation',
            'description' => 'Quelle (aus welchem System, mit dessen ID/PK – verhindert Duplikate beim Import) und Ziele (in welche Systeme exportiert, ID/PK, letzter Abgleich). Geändert nach dem letzten Abgleich = Abgleich fällig.',
            'fields' => [
                'person_id' => ['type' => 'ref', 'references' => 'users', 'required' => true, 'label' => 'Person'],
                'direction' => ['type' => 'enum', 'values' => ['source' => 'Quelle', 'target' => 'Ziel'], 'required' => true, 'label' => 'Richtung'],
                'system' => ['type' => 'enum', 'values' => 'sync_systems', 'required' => true, 'label' => 'System'],
                'external_id' => ['type' => 'string', 'required' => false, 'label' => 'ID / PK im System'],
                'id_label' => ['type' => 'string', 'required' => false, 'label' => 'Art der ID (z. B. Escola-ID, objectId, UPN)'],
                'last_sync_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Letzter Abgleich'],
                'note' => ['type' => 'string', 'required' => false, 'label' => 'Bemerkung'],
            ],
        ],

        'audit_log' => [
            'label' => 'System: Änderungsverlauf',
            'description' => 'Wer hat wann welches Feld geändert (alter → neuer Wert). Höchstens 20 000 Einträge, älteste fallen weg.',
            'fields' => [
                'collection' => ['type' => 'string', 'required' => true, 'label' => 'Sammlung'],
                'record_id' => ['type' => 'uuid', 'required' => true, 'label' => 'Datensatz'],
                'record_label' => ['type' => 'string', 'required' => false, 'label' => 'Bezeichnung (Momentaufnahme)'],
                'action' => ['type' => 'enum', 'values' => ['created' => 'Angelegt', 'updated' => 'Geändert', 'deleted' => 'Gelöscht'], 'required' => true, 'label' => 'Vorgang'],
                'changes' => ['type' => 'json', 'required' => true, 'label' => 'Änderungen {feld: {old, new}}'],
                'source' => ['type' => 'string', 'required' => true, 'label' => 'Quelle (Formular, Tabelle, Import …)'],
                'user_id' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Geändert von'],
                'user_name' => ['type' => 'string', 'required' => false, 'label' => 'Name (Momentaufnahme)'],
            ],
        ],

        'api_tokens' => [
            'label' => 'System: API-Zugänge',
            'description' => 'Zugänge für die REST-API (/api/v1). Gespeichert wird nur der SHA-256-Hash des Tokens.',
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Bezeichnung'],
                'token_hash' => ['type' => 'string', 'required' => true, 'unique' => true, 'label' => 'Token (SHA-256)'],
                'prefix' => ['type' => 'string', 'required' => true, 'label' => 'Anfang des Tokens (zur Wiedererkennung)'],
                'abilities' => ['type' => 'json', 'required' => true, 'label' => 'Rechte (read, write)'],
                'owner_id' => ['type' => 'ref', 'references' => 'users', 'required' => true, 'label' => 'Handelt im Namen von'],
                'expires_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Gültig bis'],
                'last_used_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Zuletzt verwendet'],
            ],
        ],

        'grid_views' => [
            'label' => 'System: Tabellenansichten',
            'description' => 'Gespeicherte Ansichten (Filter, Sortierung, Spalten, Gruppierung) je Tabelle; persönlich oder für alle.',
            'fields' => [
                'grid' => ['type' => 'string', 'required' => true, 'label' => 'Tabelle'],
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
                'owner_id' => ['type' => 'ref', 'references' => 'users', 'required' => true, 'label' => 'Erstellt von'],
                'shared' => ['type' => 'bool', 'required' => true, 'label' => 'Für alle sichtbar'],
                'state' => ['type' => 'json', 'required' => true, 'label' => 'Ansicht'],
            ],
        ],

    ],

];
