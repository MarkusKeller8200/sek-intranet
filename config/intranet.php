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
$guiCategories = [
    'navigation' => ['title' => 'Navigation & Aufbau', 'icon' => 'Compass', 'description' => 'Wie man sich im Intranet bewegt und wie eine Seite aufgebaut ist.'],
    'eingabe' => ['title' => 'Eingabe', 'icon' => 'TextCursorInput', 'description' => 'Wie Angaben erfasst, gewählt und Dateien übergeben werden.'],
    'daten' => ['title' => 'Daten anzeigen', 'icon' => 'Table2', 'description' => 'Wie Listen, Kennzahlen und Verläufe dargestellt werden.'],
    'rueckmeldung' => ['title' => 'Rückmeldung', 'icon' => 'MessageSquareWarning', 'description' => 'Wie das Intranet antwortet: Meldungen, Rückfragen, Laden, Hinweise.'],
];

$guiComponents = [
    // Navigation & Aufbau
    'menue' => [
        'category' => 'navigation', 'title' => 'Menü', 'icon' => 'Menu',
        'description' => 'Seitenleiste mit Hauptmenüs und Untermenüs, Aktionsmenü (Dropdown-Menü) und Breadcrumbs.',
        'parts' => ['Seitenleiste: Hauptmenüs mit Farbe, Untermenüs, aktiver Eintrag', 'Aktionsmenü: Aktionen zu einer Zeile oder Seite, Gefährliches zuletzt', 'Breadcrumbs: wo bin ich; der erste Eintrag öffnet das Menü wieder', 'Navigationsmenü oben (Navigation Menu)'],
        'imports' => ['@/components/ui/dropdown-menu', '@/components/ui/sidebar', '@/components/ui/breadcrumb', '@/components/nav-modules', '@/components/ui/navigation-menu'],
    ],
    'seitenpanel' => [
        'category' => 'navigation', 'title' => 'Seitenpanel', 'icon' => 'PanelRight',
        'description' => 'Fährt von der Seite oder von unten ein (Sheet, Drawer): Details, Filter oder Bearbeiten, ohne die Seite zu verlassen; auf dem Handy auch Menü und Aktionen.',
        'parts' => ['Von rechts: Details und Bearbeiten', 'Von links: Menü auf dem Handy', 'Von unten (Drawer): Aktionen auf dem Handy', 'Schliesst mit Esc, Klick daneben oder «Schliessen»'],
        'imports' => ['@/components/ui/sheet'],
    ],
    'tabs' => [
        'category' => 'navigation', 'title' => 'Reiter (Tabs)', 'icon' => 'Folders',
        'description' => 'Teilt eine Seite in Bereiche, von denen immer einer sichtbar ist. Pfeiltasten wechseln den Reiter.',
        'parts' => ['Reiter mit und ohne Symbol', 'Gesperrter Reiter', 'Tastatur: Pfeiltasten, Anfang und Ende'],
        'imports' => ['@/components/ui/tabs'],
    ],
    'pagination' => [
        'category' => 'navigation', 'title' => 'Seitenweise Anzeige', 'icon' => 'ListOrdered',
        'description' => 'Blättern in langen Listen ausserhalb der Tabelle (z. B. Mitteilungen, Verlauf).',
        'parts' => ['Zurück / Weiter', 'Seitenzahlen mit «…»', 'Aktuelle Seite für Bildschirmleser markiert'],
        'imports' => ['@/components/ui/pagination'],
    ],
    'stepper' => [
        'category' => 'navigation', 'title' => 'Schrittanzeige', 'icon' => 'Footprints',
        'description' => 'Zeigt Abläufe in Schritten (Geräteausgabe, Import, Versand): erledigt, aktuell, offen.',
        'parts' => ['Schritte mit Titel und Beschreibung', 'Zurück / Weiter', 'Auf dem Handy untereinander'],
        'imports' => ['@/components/ui/stepper'],
    ],
    'befehlspalette' => [
        'category' => 'navigation', 'title' => 'Befehlspalette', 'icon' => 'Command',
        'description' => 'Mit Ctrl+K schnell zu Seiten, Personen und Aktionen springen – ohne Maus.',
        'parts' => ['Öffnen mit Ctrl+K (Mac: Cmd+K) oder Knopf', 'Tippen filtert, Gruppen nach Art', 'Pfeiltasten und Enter'],
        'imports' => ['@/components/ui/command-palette'],
    ],
    'umschalter' => [
        'category' => 'navigation', 'title' => 'Umschalter & Aufklappen', 'icon' => 'ToggleRight',
        'description' => 'Umschaltgruppe für Ansichten, Ein/Aus-Knopf und ein aufklappbarer Bereich.',
        'parts' => ['Umschaltgruppe (z. B. Ansicht wählen)', 'Ein/Aus-Knopf (Toggle)', 'Aufklappbarer Bereich (Collapsible)'],
        'imports' => ['@/components/ui/toggle-group', '@/components/ui/toggle', '@/components/ui/collapsible'],
    ],
    'kontextmenue' => [
        'category' => 'navigation', 'title' => 'Kontextmenü', 'icon' => 'SquareMousePointer',
        'description' => 'Aktionen mit Rechtsklick, langem Drücken (iPad) oder Shift+F10 – immer zusätzlich als sichtbarer Knopf «…».',
        'parts' => ['Rechtsklick, langes Drücken, Shift+F10, Menütaste', 'Pfeiltasten, Esc', 'Gleiche Aktionen im Knopf «…»'],
        'imports' => ['@/components/ui/context-menu'],
    ],
    'geteilt' => [
        'category' => 'navigation', 'title' => 'Geteilte Ansicht', 'icon' => 'Columns2',
        'description' => 'Zwei Bereiche nebeneinander mit verschiebbarer Trennlinie, z. B. Liste links und Details rechts.',
        'parts' => ['Ziehen mit Maus und Touch', 'Pfeiltasten, Pos1/Ende, Doppelklick zurück', 'Grenzen für kleinste und grösste Breite'],
        'imports' => ['@/components/ui/resizable'],
    ],
    // Eingabe
    'button' => [
        'category' => 'eingabe', 'title' => 'Button', 'icon' => 'MousePointerClick',
        'description' => 'Varianten (Haupt-, Neben-, Umriss-, Text-, Gefahr-Button), Grössen, mit Symbol, nur Symbol, lädt, gesperrt.',
        'parts' => ['Varianten und Grössen', 'Mit Symbol, nur Symbol (mit Titel)', 'Lädt, gesperrt'],
        'imports' => ['@/components/ui/button', '@/components/delete-button'],
    ],
    'knopfgruppe' => [
        'category' => 'eingabe', 'title' => 'Knopfgruppe', 'icon' => 'Group',
        'description' => 'Zusammengehörende Knöpfe ohne Abstand, z. B. Export CSV / Excel, Ansicht Liste / Kacheln, Textformat.',
        'parts' => ['Aktionen mit Menü «Weitere»', 'Auswahl der Ansicht (aria-pressed)', 'Ein/Aus-Knöpfe (Fett, Kursiv)'],
        'imports' => ['@/components/ui/button-group'],
    ],
    'formular' => [
        'category' => 'eingabe', 'title' => 'Formularfelder', 'icon' => 'TextCursorInput',
        'description' => 'Textfeld, Textbereich, Zahl, Datum, Kontrollkästchen, Beschriftung, Hinweis und Fehlermeldung.',
        'parts' => ['Text, Zahl, Textbereich', 'Beschriftung, Hinweis, Fehler', 'Kontrollkästchen, gesperrtes Feld', 'Feldgruppen mit Überschrift, Einstellung als Zeile (Field)'],
        'imports' => ['@/components/ui/input', '@/components/ui/textarea', '@/components/ui/checkbox', '@/components/form-field', '@/components/input-error', '@/components/ui/label', '@/components/ui/field'],
    ],
    'eingabegruppe' => [
        'category' => 'eingabe', 'title' => 'Eingabegruppe', 'icon' => 'RectangleEllipsis',
        'description' => 'Feld mit Symbol, Text davor/danach oder Knopf in einem Rahmen: Suche, «@sekbuelach.ch», «CHF», Kopieren.',
        'parts' => ['Suche mit Symbol und Leeren', 'Text davor und danach', 'Knopf im Feld (Kopieren)'],
        'imports' => ['@/components/ui/input-group'],
    ],
    'dropdown' => [
        'category' => 'eingabe', 'title' => 'Dropdown', 'icon' => 'ChevronsUpDown',
        'description' => 'Auswahlfeld ohne und mit Vorauswahl, gesperrt, mit Fehler sowie Mehrfachauswahl.',
        'parts' => ['Ohne Vorauswahl («Bitte wählen»)', 'Mit Vorauswahl', 'Mit Fehler, kurze Mehrfachauswahl', 'Einfaches Auswahlfeld des Browsers (Native Select)'],
        'imports' => ['@/components/select-field', '@/components/ui/select', '@/components/ui/native-select'],
    ],
    'radio' => [
        'category' => 'eingabe', 'title' => 'Optionsfelder', 'icon' => 'CircleDot',
        'description' => 'Genau eine Wahl aus wenigen (2–5) sichtbaren Möglichkeiten, z. B. Zielgruppe oder Art der Abgabe.',
        'parts' => ['Untereinander und nebeneinander', 'Mit Hinweis je Möglichkeit', 'Gesperrte Möglichkeit, Fehler'],
        'imports' => ['@/components/ui/radio-group'],
    ],
    'switch' => [
        'category' => 'eingabe', 'title' => 'Schalter', 'icon' => 'ToggleLeft',
        'description' => 'Ein/Aus für Einstellungen, die sofort gelten (z. B. Benachrichtigungen). In Formularen mit «Speichern» Kontrollkästchen verwenden.',
        'parts' => ['Schalter mit Beschriftung und Hinweis', 'Gesperrt', 'Wann Schalter, wann Kontrollkästchen'],
        'imports' => ['@/components/ui/switch'],
    ],
    'combobox' => [
        'category' => 'eingabe', 'title' => 'Auswahl mit Suche', 'icon' => 'SearchCheck',
        'description' => 'Auswahl aus langen Listen mit Suchfeld (Combobox), z. B. eine Person aus 500 oder ein Gerät.',
        'parts' => ['Tippen filtert (ohne Akzente, Gross/Klein)', 'Zusatzangabe je Eintrag', 'Pfeiltasten, Enter, Esc'],
        'imports' => ['@/components/ui/combobox'],
    ],
    'mehrfachauswahl' => [
        'category' => 'eingabe', 'title' => 'Mehrfachauswahl', 'icon' => 'ListChecks',
        'description' => 'Mehrere Werte wählen, z. B. Klassen oder Zielgruppen – mit Suche, «Alle» / «Keine» und entfernbaren Kennzeichen.',
        'parts' => ['Suche in der Liste', 'Alle / Keine', 'Gewählte Werte als Kennzeichen, einzeln entfernbar'],
        'imports' => ['@/components/ui/multi-select'],
    ],
    'datum' => [
        'category' => 'eingabe', 'title' => 'Datumsauswahl', 'icon' => 'CalendarDays',
        'description' => 'Datum eintippen (TT.MM.JJJJ) oder im Kalender wählen; Woche beginnt am Montag, Bereich einschränkbar.',
        'parts' => ['Eintippen oder Kalender', 'Frühestes / spätestes Datum', 'Tastatur: Pfeile, Bild auf/ab'],
        'imports' => ['@/components/ui/calendar'],
    ],
    'upload' => [
        'category' => 'eingabe', 'title' => 'Datei hochladen', 'icon' => 'FileUp',
        'description' => 'Ablagefläche zum Hineinziehen oder Anklicken (Klassenliste, Escola-Export, Dokument, PDF für ePost) mit Prüfung von Typ und Grösse.',
        'parts' => ['Eine oder mehrere Dateien', 'Erlaubte Dateitypen, Höchstgrösse', 'Liste mit Entfernen; Dateien bleiben im Browser bis zum Senden', 'Anhang mit Vorschaubild, Fortschritt und Fehler (Attachment)'],
        'imports' => ['@/components/ui/file-drop', '@/components/ui/attachment'],
    ],
    'slider' => [
        'category' => 'eingabe', 'title' => 'Schieberegler', 'icon' => 'SlidersHorizontal',
        'description' => 'Wert in einem Bereich einstellen (z. B. Anzahl Geräte, Prozent); der Wert steht immer daneben.',
        'parts' => ['Mit Einheit', 'Schritte', 'Tastatur: Pfeile'],
        'imports' => ['@/components/ui/slider'],
    ],
    'dragdrop' => [
        'category' => 'eingabe', 'title' => 'Drag & Drop', 'icon' => 'Move',
        'description' => 'Ziehen und Ablegen mit dnd-kit: Liste umsortieren, Zuordnen (Schüler:innen → Klassen) und Kanban-Tafel. Alles geht auch ohne Ziehen (Tastatur, Knöpfe, «Nach …»).',
        'parts' => ['Liste umsortieren: Griff, Tastatur (Leertaste, Pfeile), Nach oben/unten', 'Zuordnen: Karten in Spalten ziehen oder «Nach …» wählen', 'Kanban: Status von Tickets/Bestellungen', 'Dateien: siehe «Datei hochladen»', 'Tabelle: Spalten ziehen oder im Spaltenmenü verschieben'],
        'imports' => ['@/components/ui/sortable-list', '@/components/ui/drag-board', '@/components/ui/file-drop'],
    ],
    'fragebogen' => [
        'category' => 'eingabe', 'title' => 'Fragebogen', 'icon' => 'ClipboardList',
        'description' => 'Umfrage in Schritten mit Einfach-, Mehrfachauswahl und Freitext, überspringbaren Fragen und Übersicht vor dem Absenden (Modul «Umfragen»).',
        'parts' => ['Eine Frage pro Schritt mit Fortschritt', 'Pflichtfragen und Überspringen', 'Übersicht mit «Ändern»'],
        'imports' => ['@/components/ui/questionnaire'],
    ],
    // Daten anzeigen
    'tabelle' => [
        'category' => 'daten', 'title' => 'Tabellen', 'icon' => 'Table2',
        'description' => 'Bearbeitbare Tabelle (Grundlage: Personen) mit Suche, Filtern, Sortierung, Spaltenwahl, Auswahl, Export und einfache Tabelle.',
        'parts' => ['Bearbeitbare Tabelle wie in Excel', 'Filter je Spalte, Sortierung, Spalten, Ansichten', 'Einfache Tabelle für kurze Listen'],
        'imports' => ['@/components/data-grid', '@/components/ui/table'],
    ],
    'typografie' => [
        'category' => 'daten', 'title' => 'Typografie', 'icon' => 'Type',
        'description' => 'Einheitliche Überschriften, Absätze, Listen, Zitate, Code im Text, Tasten (Kbd) und Trennlinien – Grundlage für Mitteilungen und Handbuch.',
        'parts' => ['Überschriften H1–H4, Einleitung, Text', 'Listen, Zitat, leiser und kleiner Text', 'Code im Text, Tasten, Trennlinie'],
        'imports' => ['@/components/ui/typography', '@/components/ui/kbd', '@/components/ui/separator'],
    ],
    'eintrag' => [
        'category' => 'daten', 'title' => 'Eintrag', 'icon' => 'Rows3',
        'description' => 'Zeile mit Symbol oder Bild, Titel, Beschreibung, Zusatzangabe und Aktionen – für Listen von Personen, Dokumenten, Plugins.',
        'parts' => ['Liste mit Trennlinien', 'Mit Kennzeichen und Aktionen', 'Gedämpfte Variante'],
        'imports' => ['@/components/ui/item'],
    ],
    'karten' => [
        'category' => 'daten', 'title' => 'Karten & Seitenkopf', 'icon' => 'PanelsTopLeft',
        'description' => 'Seitenkopf mit Titel und Beschreibung, Karten mit Kopf, Inhalt und Fuss, Kennzahlen, leerer Zustand.',
        'parts' => ['Seitenkopf mit Aktion', 'Karte, Kennzahl', 'Leerer Zustand'],
        'imports' => ['@/components/ui/card', '@/components/page-header', '@/components/empty-state', '@/components/heading'],
    ],
    'badge' => [
        'category' => 'daten', 'title' => 'Badges, Status & Avatar', 'icon' => 'Tag',
        'description' => 'Kennzeichen für Status, Auswahlwerte in den Farben des Farbkonzepts, Zähler und Avatar (Initialen).',
        'parts' => ['Grundformen und Zähler', 'Auswahlwerte im Farbkonzept', 'Avatar mit Initialen'],
        'imports' => ['@/components/ui/badge', '@/components/option-badge', '@/components/ui/avatar'],
    ],
    'akkordeon' => [
        'category' => 'daten', 'title' => 'Akkordeon', 'icon' => 'ListCollapse',
        'description' => 'Mehrere aufklappbare Abschnitte, z. B. Fragen & Antworten im Handbuch; einer oder mehrere offen.',
        'parts' => ['Nur einer offen', 'Mehrere offen', 'Überschrift als Knopf (Bildschirmleser)'],
        'imports' => ['@/components/ui/accordion'],
    ],
    'fortschritt' => [
        'category' => 'daten', 'title' => 'Fortschritt', 'icon' => 'Gauge',
        'description' => 'Fortschrittsbalken für Import, Versand oder Abgabe; bestimmt (Prozent) oder unbestimmt.',
        'parts' => ['Mit Prozent', 'Unbestimmt (Dauer unbekannt)', 'Kennzahl mit Balken (z. B. Lizenzen)'],
        'imports' => ['@/components/ui/progress'],
    ],
    'diagramm' => [
        'category' => 'daten', 'title' => 'Diagramme', 'icon' => 'ChartColumn',
        'description' => 'Balken-, Linien- und Ringdiagramme für Kennzahlen (Lizenzen, Geräte, Tickets) in den Farben des Farbkonzepts, immer auch als Tabelle.',
        'parts' => ['Einfache Balken', 'Zwei Werte je Zeile (z. B. gekauft / zugewiesen)', 'Verlauf als Linien', 'Anteile als Ring', 'Umschalter «Als Tabelle»'],
        'imports' => ['@/components/ui/bar-chart', '@/components/ui/line-chart', '@/components/ui/donut-chart'],
    ],
    'zeitleiste' => [
        'category' => 'daten', 'title' => 'Zeitleiste', 'icon' => 'History',
        'description' => 'Verlauf mit Datum, z. B. Versionen, Änderungen, Status einer Bestellung (neueste oben).',
        'parts' => ['Eintrag mit Datum, Titel, Beschreibung', 'Farbe nach Art (Erfolg, Warnung, Hinweis)'],
        'imports' => ['@/components/ui/timeline'],
    ],
    'scrollbereich' => [
        'category' => 'daten', 'title' => 'Scrollbereich', 'icon' => 'ScrollText',
        'description' => 'Bereich mit fester Höhe oder Breite und schmaler, einheitlicher Bildlaufleiste; mit Tastatur erreichbar.',
        'parts' => ['Senkrecht (lange Liste)', 'Waagrecht (Klassen, Kacheln)', 'Fokus und Pfeiltasten'],
        'imports' => ['@/components/ui/scroll-area'],
    ],
    'codeblock' => [
        'category' => 'daten', 'title' => 'Codeblock', 'icon' => 'Code',
        'description' => 'Code, Konsolenausgabe und Vergleiche mit Kopfzeile, Zeilennummern und Kopieren – für API-Tester, Plugins und das Skript der Geräteausgabe.',
        'parts' => ['Mit Zeilennummern', 'Konsolenausgabe', 'Vergleich (+/−), Kopieren'],
        'imports' => ['@/components/ui/code-block'],
    ],
    'begriff' => [
        'category' => 'daten', 'title' => 'Begriff', 'icon' => 'BookA',
        'description' => 'Begriff aus dem Glossar, fein gepunktet unterstrichen: beim Darüberfahren, Fokussieren oder Antippen ein Tooltip mit Abkürzung, Bezeichnung und Kurzbeschreibung; Klick öffnet den Eintrag. Im Code als Baustein, im Freitext automatisch.',
        'parts' => ['Begriff im Code', 'Mehrdeutige Abkürzung (alle Bedeutungen)', 'Begriffe im Freitext', 'Farbe der Punkte im Theme, abschaltbar je Person'],
        'imports' => ['@/components/ui/term', '@/components/term-text'],
    ],
    // Rückmeldung
    'meldungen' => [
        'category' => 'rueckmeldung', 'title' => 'Meldungen & Dialoge', 'icon' => 'MessageSquareWarning',
        'description' => 'Hinweisboxen, Rückfrage-Dialog, kurze Meldungen (Toast) und Tooltips.',
        'parts' => ['Hinweisbox (Info, Fehler)', 'Rückfrage vor Löschen', 'Toast und Tooltip'],
        'imports' => ['@/components/ui/alert', '@/components/ui/dialog', '@/components/ui/tooltip', 'sonner', '@/components/alert-error'],
    ],
    'rueckfrage' => [
        'category' => 'rueckmeldung', 'title' => 'Rückfrage', 'icon' => 'ShieldAlert',
        'description' => 'Dialog, der bewusst beantwortet werden muss (Alert Dialog): schliesst nicht mit Klick daneben, Fokus zuerst auf «Abbrechen». Für Löschen und unumkehrbare Schritte.',
        'parts' => ['Gefährlich (Löschen)', 'Wichtig (Freigeben)', 'Fokus auf «Abbrechen», kein Schliessen daneben'],
        'imports' => ['@/components/ui/alert-dialog'],
    ],
    'popover' => [
        'category' => 'rueckmeldung', 'title' => 'Popover', 'icon' => 'MessageSquareMore',
        'description' => 'Kleines Fenster an einem Knopf für Hilfe, Filter oder eine kurze Auswahl; schliesst mit Esc oder Klick daneben.',
        'parts' => ['Hilfe zu einem Feld', 'Kleiner Filter mit Übernehmen', 'Fokus zurück zum Knopf'],
        'imports' => ['@/components/ui/popover', '@/components/grid/popover'],
    ],
    'vorschaukarte' => [
        'category' => 'rueckmeldung', 'title' => 'Vorschaukarte', 'icon' => 'IdCard',
        'description' => 'Kleine Vorschau beim Überfahren oder Fokussieren eines Links (Hover Card), z. B. einer Person – nur Zusatzinformation.',
        'parts' => ['Öffnet mit Maus und Tastatur (Fokus)', 'Kurze Verzögerung, Esc schliesst', 'Link führt immer zur ganzen Seite'],
        'imports' => ['@/components/ui/hover-card'],
    ],
    'banner' => [
        'category' => 'rueckmeldung', 'title' => 'Banner', 'icon' => 'Flag',
        'description' => 'Hinweis über die ganze Breite oben auf der Seite, z. B. Wartung, Prototyp, neue Version.',
        'parts' => ['Info, Warnung, Erfolg', 'Mit Aktion', 'Schliessbar'],
        'imports' => ['@/components/ui/banner'],
    ],
    'laden' => [
        'category' => 'rueckmeldung', 'title' => 'Ladezustände', 'icon' => 'Loader',
        'description' => 'Ladeanzeige (Spinner) und Platzhalter (Skeleton), solange Daten geladen werden.',
        'parts' => ['Spinner', 'Platzhalter in der Form des Inhalts'],
        'imports' => ['@/components/ui/spinner', '@/components/ui/skeleton'],
    ],
];

// Menü «GUI-Prototypen»: Untermenü je Bereich
$guiMenu = [];
foreach ($guiCategories as $categoryKey => $category) {
    $children = [];
    foreach ($guiComponents as $componentKey => $component) {
        if ($component['category'] === $categoryKey) {
            $children['gui-'.$componentKey] = ['title' => $component['title'], 'icon' => $component['icon'], 'href' => '/gui-prototypen/'.$componentKey];
        }
    }
    $guiMenu['gui-'.$categoryKey] = ['title' => $category['title'], 'icon' => $category['icon'], 'items' => $children];
}

return [

    // json = Dateien (Prototyp). Später: database (PostgreSQL, wie IDA ADR-003).
    /*
    | Version (Datei VERSION) und Build aus dem Deployment (Commit, Zeitpunkt).
    | Änderungen stehen in CHANGELOG.md und auf der Seite «Versionen».
    */
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',
    'build' => env('INTRANET_BUILD'),
    'build_date' => env('INTRANET_BUILD_DATE'),
    // git describe --tags --long (Developer Release: Commits nach dem Tag v<VERSION>)
    'build_describe' => env('INTRANET_BUILD_DESCRIBE'),
    // GitHub-Repository für «Als GitHub-Issue vorbereiten» (leer = Knopf ausblenden)
    'github_repo' => env('INTRANET_GITHUB_REPO', 'MarkusKeller8200/sek-intranet'),

    'store' => env('INTRANET_STORE', 'json'),

    // Ordner für die JSON-Dateien, relativ zum Projektordner oder absolut.
    'data_path' => env('INTRANET_DATA_PATH', 'storage/app/data'),

    // Module (Fachthemen mit eigener Datenhaltung, docs/KONZEPT-MODULE.md): modules/<Ordner>/module.json
    'modules_path' => env('INTRANET_MODULES_PATH', 'modules'),

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

    // Unterschriften (#38–#43): Webhook nur, wenn der Server von aussen ohne Seitenpasswort erreichbar ist
    'signatures' => [
        'webhook' => (bool) env('INTRANET_SIGNATURE_WEBHOOK', env('INTRANET_SERVER_IN_SWITZERLAND', false)),
        'poll_minutes' => 15,
        'fetch_warning_hours' => 24,
    ],

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
        'schueler' => 'Schüler:in',
        'eltern' => 'Eltern',
        'extern' => 'Externe',
        'kein-zugang' => 'Kein Zugang',
    ],

    // Anmeldung mit PIN je Rolle (Prototyp, #69; später OIDC). Reihenfolge = Anzeige auf der Anmeldeseite.
    // Die PINs selbst stehen nur als Hash in den Daten (access_pins), gesetzt unter Einstellungen → Zugang.
    'pin_roles' => [
        'admin' => 'Administrator',
        'verwaltung' => 'Verwaltung',
        'schulleitung' => 'Schulleitung',
        'lehrperson' => 'Lehrperson',
        'schueler' => 'Schüler:in',
        'eltern' => 'Eltern',
        'extern' => 'Externe',
    ],

    // Rollen, die nur bestimmte Menügruppen sehen und öffnen dürfen (Schlüssel aus «modules»).
    'restricted_roles' => [
        'extern' => ['kommunikation', 'wissen'],
        'schueler' => ['kommunikation', 'wissen'],
        'eltern' => ['kommunikation', 'wissen'],
    ],

    // Systeme für Datenherkunft und Synchronisation (Personen → Datenherkunft). Liste erweiterbar.
    'sync_systems' => [
        'escola' => 'Escola',
        'entra' => 'Microsoft 365 / Entra ID',
        'jamf' => 'Jamf Pro',
        'ida' => 'IDA',
        'inventory360' => 'Inventory360',
        'clicktime' => 'ClickTime',
        'cmi' => 'CMI',
        'edulog' => 'Edulog',
        'datei' => 'Datei-Import (Excel/CSV)',
        'intranet' => 'Intranet (manuell erfasst)',
    ],

    // Direkt aus der Datenherkunft ins fremde System springen (#149): Adresse mit {id} (ID aus der Datenherkunft) und
    // {bereich} je Personenart ('*' = alle übrigen). Nur gültige IDs (Muster), nur https. Es werden keine Daten gelesen.
    'record_links' => [
        'cmi' => [
            'label' => 'In CMI öffnen',
            'url' => env('INTRANET_CMI_DOSSIER_URL', 'https://sekbuelach.cmicloud.ch/#/{bereich}/{bereich}/{id}'),
            'id_pattern' => '/^[0-9a-f]{32}$/i',
            'bereiche' => [
                // Schülerdossier bzw. Personaldossier (GUID des Bereichs in der CMI-Adresse)
                'schueler' => env('INTRANET_CMI_BEREICH_SCHUELER', '360f3d3459a745e5b2f35918edb69434'),
                '*' => env('INTRANET_CMI_BEREICH_PERSONAL', 'a6918005296e46f8a31242fc7861f653'),
            ],
        ],
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
    'login_person_types' => ['lehrperson', 'schulleitung', 'mitarbeitende', 'behoerde', 'schueler', 'eltern', 'extern'],
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
        'schueler' => 'Schüler:in (Prototyp): vorerst nur Kommunikation und Wissen & Ablage.',
        'eltern' => 'Eltern (Prototyp): vorerst nur Kommunikation und Wissen & Ablage.',
        'extern' => 'Externe Fachperson (Prototyp): nur Kommunikation und Wissen & Ablage.',
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
        'view-glossary' => ['label' => 'Glossar ansehen', 'roles' => ['admin', 'schulleitung', 'verwaltung', 'ict', 'redaktion', 'lehrperson', 'mitarbeitende', 'schueler', 'eltern', 'extern']],
        'edit-glossary' => ['label' => 'Glossar pflegen (eigene Abkürzungen und Bezeichnungen, Listen, Import)', 'roles' => ['admin', 'schulleitung', 'verwaltung']],
        'edit-glossary-official' => ['label' => 'Offizielle Begriffe im Glossar ändern', 'roles' => ['admin']],
        'manage-signatures' => ['label' => 'Unterschriften: Übersicht, erinnern, zurückziehen (Personalabteilung)', 'roles' => ['admin', 'verwaltung']],
        'view-students' => ['label' => 'Schüler:innen und Eltern sehen', 'roles' => ['admin', 'schulleitung', 'verwaltung', 'ict', 'lehrperson']],
        'use-gui' => ['label' => 'GUI-Prototypen ansehen und Wünsche erfassen', 'roles' => ['admin', 'schulleitung', 'ict', 'redaktion']],
        'review-gui' => ['label' => 'GUI-Prototypen prüfen und freigeben', 'roles' => ['admin', 'schulleitung']],
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
        'neuerungen' => 'Neuerungen',
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
        'unterschriften' => 'Unterschriebene Dokumente',
        'monatsblaetter' => 'Monatsblätter',
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
                'sl-planer' => [
                    'title' => 'SL-Planer',
                    'icon' => 'CalendarClock',
                    'description' => 'SL-Planer (Planung für Schulleitungen). Der SL-Planer verbietet das Einbetten (X-Frame-Options: SAMEORIGIN, geprüft 06.11.2026) – öffnet in neuem Tab.',
                    // Adresse wie bei CMI/Escola über die Server-.env übersteuerbar (z. B. eigene Instanz oder Login-Seite)
                    'embed' => ['url' => env('INTRANET_APP_SLPLANER_URL', 'https://slplaner.ch/my/users/sign_in?open_modal=true'), 'open' => 'tab'],
                ],
            ],
        ],
        'wissen' => [
            'title' => 'Wissen & Ablage',
            'color' => 'violet',
            'icon' => 'Library',
            'items' => [
                'dokumente' => ['title' => 'Dokumente & Links', 'icon' => 'FileText', 'href' => '/dokumente'],
                'glossar' => ['title' => 'Glossar', 'icon' => 'BookA', 'href' => '/glossar', 'can' => 'view-glossary'],
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
                'unterschriften' => [
                    'title' => 'Unterschriften',
                    'icon' => 'Signature',
                    'href' => '/unterschriften',
                    'can' => 'manage-signatures',
                    'description' => 'Elektronische Unterschriften (DeepSign): offene und abgeschlossene Aufträge, erinnern, zurückziehen.',
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
                'modulverwaltung' => ['title' => 'Module', 'icon' => 'Boxes', 'href' => '/einstellungen/module'],
                'zugang' => ['title' => 'Zugang (PIN)', 'icon' => 'KeyRound', 'href' => '/einstellungen/zugang'],
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
            'can' => 'use-gui',
            'items' => [
                'gui-inhalt' => ['title' => 'Inhaltsverzeichnis', 'icon' => 'BookOpen', 'href' => '/gui-prototypen'],
                'gui-freigaben' => ['title' => 'Freigaben & Status', 'icon' => 'LayoutList', 'href' => '/gui-prototypen/freigaben'],
            ] + $guiMenu,
        ],
    ],

    'gui_components' => $guiComponents,

    // Vorlagen für neue Themes (Farbkonzept → Themes); nur abweichende Farben, Rest aus app.css
    'theme_presets' => [
        'kontrast' => [
            'name' => 'Hoher Kontrast',
            'description' => 'Dunklere Schrift, kräftigere Linien und Fokus – für schwierige Lichtverhältnisse und Sehschwäche.',
            'light' => ['primary' => '#00507a', 'foreground' => '#000000', 'card-foreground' => '#000000', 'muted-foreground' => '#3a3d40', 'border' => '#8a9096', 'input' => '#6b7177', 'ring' => '#00507a', 'sidebar-foreground' => '#000000', 'sidebar-border' => '#8a9096'],
            'dark' => ['background' => '#000000', 'card' => '#0d0d0d', 'popover' => '#0d0d0d', 'foreground' => '#ffffff', 'card-foreground' => '#ffffff', 'muted-foreground' => '#d0d4d8', 'border' => '#6b7177', 'input' => '#8a9096', 'primary' => '#7fd4ff', 'ring' => '#7fd4ff', 'sidebar' => '#000000', 'sidebar-foreground' => '#ffffff', 'sidebar-border' => '#6b7177'],
        ],
        'petrol' => [
            'name' => 'Petrol',
            'description' => 'Grün-blaue Hauptfarbe (z. B. für das Schulhaus Hinterbirch).',
            'light' => ['brand' => '#0d9488', 'primary' => '#0f766e', 'ring' => '#0d9488', 'accent' => '#e6f6f4', 'accent-foreground' => '#134e4a', 'secondary' => '#eef8f7', 'secondary-foreground' => '#134e4a', 'sidebar-primary' => '#0f766e', 'sidebar-accent' => '#e6f6f4', 'sidebar-accent-foreground' => '#134e4a', 'sidebar-ring' => '#0d9488', 'info' => '#0f766e', 'info-soft' => '#e6f6f4', 'chart-1' => '#0d9488'],
            'dark' => ['brand' => '#2dd4bf', 'primary' => '#2dd4bf', 'ring' => '#2dd4bf', 'accent' => '#123c38', 'accent-foreground' => '#ccfbf1', 'secondary' => '#10302d', 'secondary-foreground' => '#ccfbf1', 'sidebar-primary' => '#2dd4bf', 'sidebar-accent' => '#123c38', 'sidebar-accent-foreground' => '#ccfbf1', 'info' => '#2dd4bf', 'info-soft' => '#10302d', 'chart-1' => '#2dd4bf'],
        ],
        'violett' => [
            'name' => 'Violett',
            'description' => 'Violette Hauptfarbe (z. B. für das Schulhaus Mettmenriet).',
            'light' => ['brand' => '#7c3aed', 'primary' => '#6d28d9', 'ring' => '#7c3aed', 'accent' => '#f3eefe', 'accent-foreground' => '#3b1a78', 'secondary' => '#f6f2fe', 'secondary-foreground' => '#3b1a78', 'sidebar-primary' => '#6d28d9', 'sidebar-accent' => '#f3eefe', 'sidebar-accent-foreground' => '#3b1a78', 'info' => '#6d28d9', 'info-soft' => '#f3eefe', 'chart-1' => '#7c3aed'],
            'dark' => ['brand' => '#a78bfa', 'primary' => '#a78bfa', 'primary-foreground' => '#191718', 'ring' => '#a78bfa', 'accent' => '#2e2250', 'accent-foreground' => '#ede9fe', 'secondary' => '#261c42', 'secondary-foreground' => '#ede9fe', 'sidebar-primary' => '#a78bfa', 'sidebar-accent' => '#2e2250', 'sidebar-accent-foreground' => '#ede9fe', 'info' => '#a78bfa', 'info-soft' => '#261c42', 'chart-1' => '#a78bfa'],
        ],
    ],
    'gui_categories' => $guiCategories,

    // Inhaltsverzeichnis: Bausteine für später (kein Prototyp; shadcn/ui-Namen in Klammern)
    'gui_later' => [
        ['title' => 'Seitenverhältnis (Aspect Ratio)', 'description' => 'Bilder und Videos in festem Format.', 'reason' => 'niedrige Priorität'],
        ['title' => 'Karussell (Carousel)', 'description' => 'Bilder zum Durchblättern, z. B. Pinnwand oder Startseite.', 'reason' => 'niedrige Priorität'],
        ['title' => 'Menüleiste (Menubar)', 'description' => 'Menüleiste wie in Programmen.', 'reason' => 'niedrige Priorität'],
        ['title' => 'Einmal-Code (Input OTP)', 'description' => 'Eingabe von Codes in Feldern pro Ziffer.', 'reason' => 'niedrige Priorität – Anmeldung über Entra ID'],
        ['title' => 'Schreibrichtung (Direction)', 'description' => 'Schrift von rechts nach links.', 'reason' => 'nicht nötig (Deutsch, Englisch)'],
        ['title' => 'Chat-Blase (Bubble)', 'description' => 'Nachricht in einer Unterhaltung mit Reaktionen.', 'reason' => 'Chat / KI – später'],
        ['title' => 'Nachricht (Message)', 'description' => 'Nachricht mit Avatar, Kopf und Fuss.', 'reason' => 'Chat / KI – später'],
        ['title' => 'Nachrichtenverlauf (Message Scroller)', 'description' => 'Scrollbereich für Unterhaltungen.', 'reason' => 'Chat / KI – später'],
        ['title' => 'Markierung (Marker)', 'description' => 'Status oder Trenner in einer Unterhaltung.', 'reason' => 'Chat / KI – später'],
        ['title' => 'Ablauf (Trace)', 'description' => 'Schritte eines KI-Assistenten: Überlegungen, Werkzeuge, Quellen.', 'reason' => 'Chat / KI – später'],
    ],

    // Inhaltsverzeichnis: Themen für alle Bausteine (kein eigener Prototyp)
    'gui_crosscutting' => [
        ['title' => 'Hell- und Dunkelmodus, Farben', 'description' => 'Farben nur über die Farbwerte in resources/css/app.css und config/intranet.php → colors; jeder Baustein funktioniert hell und dunkel.', 'href' => '/farbkonzept'],
        ['title' => 'Tastatur und Bildschirmleser', 'description' => 'Alles ohne Maus bedienbar, sichtbarer Fokus, Beschriftungen für Knöpfe mit Symbol; Ziehen immer mit Alternative (WCAG 2.2, 2.5.7).', 'href' => '/gui-prototypen/dragdrop'],
        ['title' => 'Handy und Tablet', 'description' => 'Jeder Baustein passt sich der Breite an; Touch-Ziele mindestens 24 × 24 Pixel; Ziehen auf dem iPad mit kurzem Halten.', 'href' => null],
        ['title' => 'Mehrsprachigkeit', 'description' => 'Texte im Code auf Deutsch, Übersetzungen in den Katalogen; Namen und Daten mit translate="no" schützen.', 'href' => '/sprachkonzept'],
    ],

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
            'schueler' => 'sky',
            'eltern' => 'orange',
            'extern' => 'brand',
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
            'neuerungen' => 'emerald',
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
            'unterschriften' => 'violet',
            'monatsblaetter' => 'teal',
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
                'author_id' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Autor/in'],
                'author_label' => ['type' => 'string', 'required' => false, 'label' => 'Autor/in (ohne Person, z. B. «Intranet»)'],
                'release_version' => ['type' => 'string', 'required' => false, 'label' => 'Neuerungen der Version'],
                'release_items' => ['type' => 'json', 'required' => false, 'label' => 'Punkte mit Recht (Neuerungen, bis zur Bearbeitung)'],
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
                'ability' => ['type' => 'string', 'required' => false, 'label' => 'Nur sichtbar mit Recht (leer = alle)'],
                'source' => ['type' => 'string', 'required' => false, 'label' => 'Herkunft (z. B. deepsign:<Dokument-ID>)'],
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

        'notifications' => [
            'label' => 'System: Benachrichtigungen',
            'description' => 'Glocke oben rechts: Meldungen an einzelne Personen, z. B. «Button v1.1.0 wartet auf Prüfung» oder «Neuer Wunsch». Je Person höchstens 200 gelesene.',
            'fields' => [
                'user_id' => ['type' => 'ref', 'references' => 'users', 'required' => true, 'label' => 'Für'],
                'kind' => ['type' => 'string', 'required' => true, 'label' => 'Art (z. B. gui.review, gui.wish)'],
                'title' => ['type' => 'string', 'required' => true, 'label' => 'Titel'],
                'body' => ['type' => 'string', 'required' => false, 'label' => 'Text'],
                'href' => ['type' => 'string', 'required' => true, 'label' => 'Link im Intranet'],
                'created_by' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Ausgelöst von'],
                'read_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Gelesen am'],
                'sent_at' => ['type' => 'datetime', 'required' => true, 'label' => 'Gesendet am'],
            ],
        ],
        'signature_requests' => [
            'label' => 'System: Unterschriften',
            'description' => 'Aufträge zur elektronischen Unterschrift (Schnittstelle «Unterschriften», Anbieter z. B. DeepSign): auslösendes Modul, Bezug, Unterzeichnende mit Status. Das PDF liegt nicht hier, sondern beim Modul.',
            'fields' => [
                'module' => ['type' => 'string', 'required' => true, 'label' => 'Auslösendes Modul (Schlüssel)'],
                'reference' => ['type' => 'string', 'required' => true, 'label' => 'Bezug im Modul (z. B. person:<id>:2026-10)'],
                'title' => ['type' => 'string', 'required' => true, 'label' => 'Titel'],
                'file_name' => ['type' => 'string', 'required' => true, 'label' => 'Dateiname'],
                'provider' => ['type' => 'string', 'required' => true, 'label' => 'Anbieter (Plugin)'],
                'provider_id' => ['type' => 'string', 'required' => false, 'label' => 'ID beim Anbieter'],
                'status' => ['type' => 'enum', 'values' => ['entwurf' => 'Entwurf', 'versendet' => 'versendet', 'teilweise' => 'teilweise unterschrieben', 'unterschrieben' => 'unterschrieben', 'abgelehnt' => 'abgelehnt', 'zurueckgezogen' => 'zurückgezogen', 'abgelaufen' => 'abgelaufen', 'fehler' => 'Fehler'], 'required' => true, 'label' => 'Status'],
                'signees' => ['type' => 'json', 'required' => true, 'label' => 'Unterzeichnende [{person_id, name, email, order, status, signed_at, provider_id, position}]'],
                'observers' => ['type' => 'json', 'required' => false, 'label' => 'Beobachtende (E-Mail)'],
                'comment' => ['type' => 'text', 'required' => false, 'label' => 'Nachricht an die Unterzeichnenden'],
                'callback_secret' => ['type' => 'string', 'required' => true, 'label' => 'Geheimnis für den Webhook (verschlüsselt)'],
                'created_by' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Ausgelöst von'],
                'sent_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Versendet am'],
                'completed_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Abgeschlossen am'],
                'fetched_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Unterschriebenes PDF abgeholt am'],
                'last_checked_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Status zuletzt abgefragt'],
                'warned_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Warnung «nicht abgeholt» gesendet'],
                'withdrawn_by' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Zurückgezogen von'],
                'error' => ['type' => 'text', 'required' => false, 'label' => 'Fehler beim Senden'],
                'pdf_path' => ['type' => 'string', 'required' => false, 'label' => 'Unterschriebenes PDF (nur von Hand gesendete Aufträge)'],
            ],
        ],
        'themes' => [
            'label' => 'System: Themes (Farbkonzepte)',
            'description' => 'Farbkonzept → Themes: gespeicherte Farbsätze für hell und dunkel, die die Farbwerte aus resources/css/app.css übersteuern. Höchstens eines ist aktiv (für alle).',
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
                'description' => ['type' => 'string', 'required' => false, 'label' => 'Beschreibung'],
                'light' => ['type' => 'json', 'required' => true, 'label' => 'Farben hell {farbwert: #rrggbb} (nur Abweichungen)'],
                'dark' => ['type' => 'json', 'required' => true, 'label' => 'Farben dunkel {farbwert: #rrggbb} (nur Abweichungen)'],
                'active' => ['type' => 'bool', 'required' => true, 'label' => 'Aktiv für alle'],
                'updated_by' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Geändert von'],
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
        'module_states' => [
            'label' => 'System: Module ein/aus',
            'description' => 'Ein Datensatz je Modul (id = Schlüssel aus module.json). Ohne Datensatz gilt «aktiv» aus dem Manifest.',
            'fields' => [
                'enabled' => ['type' => 'bool', 'required' => true, 'label' => 'Eingeschaltet'],
                'changed_by' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Geändert von'],
            ],
        ],
        'module_rights' => [
            'label' => 'System: Rechte der Module je Rolle',
            'description' => 'Übersteuerte Rollen eines Modulrechts (id = Recht), gesetzt unter Einstellungen → Module. Ohne Datensatz gelten die Rollen aus module.json.',
            'fields' => [
                'module' => ['type' => 'string', 'required' => true, 'label' => 'Modul'],
                'roles' => ['type' => 'json', 'required' => true, 'label' => 'Rollen'],
                'changed_by' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Geändert von'],
            ],
        ],
        'module_settings' => [
            'label' => 'System: Einstellungen der Menüpunkte',
            'description' => 'Menü «Einstellungen»: Adresse und Art des Öffnens je Fachanwendung bzw. Einbindung. Übersteuert config/intranet.php und die .env.',
            'fields' => [
                'key' => ['type' => 'string', 'required' => true, 'label' => 'Menüpunkt (Schlüssel)'],
                'url' => ['type' => 'string', 'required' => false, 'label' => 'Adresse (https)'],
                'open' => ['type' => 'string', 'required' => false, 'label' => 'Öffnen über (embed, both, tab, window, extension, split)'],
                'width' => ['type' => 'int', 'required' => false, 'label' => 'Neues Fenster: Breite in % des Anzeigebereichs'],
                'height' => ['type' => 'int', 'required' => false, 'label' => 'Neues Fenster: Höhe in % des Anzeigebereichs'],
                'split' => ['type' => 'int', 'required' => false, 'label' => 'Nebeneinander: Trennlinie in % von links'],
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
        'glossar' => [
            'label' => 'Glossar',
            'description' => 'Begriffe und Abkürzungen (docs/KONZEPT-GLOSSAR.md): offizielle und eigene Werte, Kategorien, Herkunft, genutzt in.',
            'fields' => [
                'kategorien' => ['type' => 'json', 'required' => true, 'label' => 'Kategorien (IDs)'],
                'abk_offiziell' => ['type' => 'string', 'required' => false, 'label' => 'Abkürzung offiziell'],
                'aliase_offiziell' => ['type' => 'json', 'required' => false, 'label' => 'Aliase offiziell'],
                'abk_eigen' => ['type' => 'string', 'required' => false, 'label' => 'Abkürzung eigene'],
                'alias_eigen' => ['type' => 'string', 'required' => false, 'label' => 'Alias eigene'],
                'bez_offiziell' => ['type' => 'string', 'required' => false, 'label' => 'Bezeichnung offiziell'],
                'bez_eigen' => ['type' => 'string', 'required' => false, 'label' => 'Bezeichnung eigene'],
                'kurzbeschreibung' => ['type' => 'string', 'required' => false, 'label' => 'Kurzbeschreibung (Tooltip)'],
                'beschreibung' => ['type' => 'text', 'required' => false, 'label' => 'Beschreibung'],
                'url' => ['type' => 'string', 'required' => false, 'label' => 'URL'],
                'notiz' => ['type' => 'text', 'required' => false, 'label' => 'Notiz (Markdown)'],
                'herkunft' => ['type' => 'json', 'required' => false, 'label' => 'Herkunft (IDs)'],
                'genutzt_in' => ['type' => 'json', 'required' => false, 'label' => 'Intern genutzt in'],
                'status' => ['type' => 'enum', 'values' => ['aktuell' => 'aktuell', 'historisch' => 'historisch', 'zu_pruefen' => 'zu prüfen'], 'required' => true, 'label' => 'Status'],
                'suchbegriffe' => ['type' => 'string', 'required' => false, 'label' => 'Suchbegriffe'],
                'gueltig_ab' => ['type' => 'date', 'required' => false, 'label' => 'Gültig ab'],
                'gueltig_bis' => ['type' => 'date', 'required' => false, 'label' => 'Gültig bis'],
                'aktiv' => ['type' => 'bool', 'required' => false, 'label' => 'Aktiv (fehlt = aktiv)'],
                'dubletten_ok' => ['type' => 'json', 'required' => false, 'label' => 'Als mehrdeutig belassen (Gruppen)'],
                'fruehere_ids' => ['type' => 'json', 'required' => false, 'label' => 'Frühere IDs (zusammengeführt)'],
            ],
        ],
        'glossar_kategorien' => [
            'label' => 'Glossar: Kategorien',
            'description' => 'Kategorien des Glossars (Abkürzung, Bezeichnung, Beschreibung, URL, Notiz).',
            'fields' => [
                'kuerzel' => ['type' => 'string', 'required' => true, 'label' => 'Abkürzung'],
                'bezeichnung' => ['type' => 'string', 'required' => true, 'label' => 'Bezeichnung'],
                'beschreibung' => ['type' => 'text', 'required' => false, 'label' => 'Beschreibung'],
                'url' => ['type' => 'string', 'required' => false, 'label' => 'URL'],
                'notiz' => ['type' => 'text', 'required' => false, 'label' => 'Notiz'],
                'position' => ['type' => 'int', 'required' => true, 'label' => 'Reihenfolge'],
            ],
        ],
        'glossar_herkunft' => [
            'label' => 'Glossar: Herkunft',
            'description' => 'Herkunft der Begriffe, z. B. VSA, Kanton, Bund (Abkürzung, Bezeichnung, Beschreibung, URL, Notiz).',
            'fields' => [
                'kuerzel' => ['type' => 'string', 'required' => true, 'label' => 'Abkürzung'],
                'bezeichnung' => ['type' => 'string', 'required' => true, 'label' => 'Bezeichnung'],
                'beschreibung' => ['type' => 'text', 'required' => false, 'label' => 'Beschreibung'],
                'url' => ['type' => 'string', 'required' => false, 'label' => 'URL'],
                'notiz' => ['type' => 'text', 'required' => false, 'label' => 'Notiz'],
                'position' => ['type' => 'int', 'required' => true, 'label' => 'Reihenfolge'],
            ],
        ],
        'release_announcements' => [
            'label' => 'System: Neuerungen als Mitteilung',
            'description' => 'Für welche Version die Mitteilung «Neu im Intranet» schon angelegt wurde (id = Version).',
            'fields' => [
                'announcement_id' => ['type' => 'ref', 'references' => 'announcements', 'required' => true, 'label' => 'Mitteilung'],
            ],
        ],
        'access_pins' => [
            'label' => 'System: PIN je Rolle',
            'description' => 'Anmeldung im Prototyp (id = Rolle). Nur der Hash des PINs, nie der PIN selbst.',
            'fields' => [
                'hash' => ['type' => 'string', 'required' => true, 'label' => 'Hash des PINs'],
                'updated_by' => ['type' => 'ref', 'references' => 'users', 'required' => false, 'label' => 'Gesetzt von'],
            ],
        ],
        'app_settings' => [
            'label' => 'System: Einstellungen',
            'description' => 'Allgemeine Schalter (id = Einstellung), z. B. «release_announcements» (Neuerungen als Mitteilung).',
            'fields' => [
                'enabled' => ['type' => 'bool', 'required' => true, 'label' => 'Eingeschaltet'],
            ],
        ],

    ],

];
