# GUI-Bausteine – Inhaltsverzeichnis

Stand 0.30.0. Jeder Baustein hat im Intranet unter **GUI-Prototypen** eine eigene Seite mit Beispielen, Status und
Versionen (Link = Pfad im Intranet, z. B. https://intranet.sekbuelach.ch/gui-prototypen/button). Ablauf, Status und
Freigabe: [KONZEPT-GUI-PROTOTYPEN.md](KONZEPT-GUI-PROTOTYPEN.md). Diese Liste entspricht `config/intranet.php` →
`gui_components` und dem Inhaltsverzeichnis im Intranet (`/gui-prototypen`).

- [1. Navigation & Aufbau](#navigation)
- [2. Eingabe](#eingabe)
- [3. Daten anzeigen](#daten)
- [4. Rückmeldung](#rueckmeldung)
- [5. Für alle Bausteine](#querschnitt)
- [6. Später](#spaeter)
- [Drag & Drop](#drag-und-drop)

<a id="navigation"></a>

## 1. Navigation & Aufbau

Wie man sich im Intranet bewegt und wie eine Seite aufgebaut ist.

### 1.1 [Menü](https://intranet.sekbuelach.ch/gui-prototypen/menue)

Seitenleiste mit Hauptmenüs und Untermenüs, Aktionsmenü (Dropdown-Menü) und Breadcrumbs.

- Seitenleiste: Hauptmenüs mit Farbe, Untermenüs, aktiver Eintrag
- Aktionsmenü: Aktionen zu einer Zeile oder Seite, Gefährliches zuletzt
- Breadcrumbs: wo bin ich; der erste Eintrag öffnet das Menü wieder
- Navigationsmenü oben (Navigation Menu)

Code: `@/components/ui/dropdown-menu`, `@/components/ui/sidebar`, `@/components/ui/breadcrumb`, `@/components/nav-modules`, `@/components/ui/navigation-menu`

### 1.2 [Seitenpanel](https://intranet.sekbuelach.ch/gui-prototypen/seitenpanel)

Fährt von der Seite oder von unten ein (Sheet, Drawer): Details, Filter oder Bearbeiten, ohne die Seite zu verlassen; auf dem Handy auch Menü und Aktionen.

- Von rechts: Details und Bearbeiten
- Von links: Menü auf dem Handy
- Von unten (Drawer): Aktionen auf dem Handy
- Schliesst mit Esc, Klick daneben oder «Schliessen»

Code: `@/components/ui/sheet`

### 1.3 [Reiter (Tabs)](https://intranet.sekbuelach.ch/gui-prototypen/tabs)

Teilt eine Seite in Bereiche, von denen immer einer sichtbar ist. Pfeiltasten wechseln den Reiter.

- Reiter mit und ohne Symbol
- Gesperrter Reiter
- Tastatur: Pfeiltasten, Anfang und Ende

Code: `@/components/ui/tabs`

### 1.4 [Seitenweise Anzeige](https://intranet.sekbuelach.ch/gui-prototypen/pagination)

Blättern in langen Listen ausserhalb der Tabelle (z. B. Mitteilungen, Verlauf).

- Zurück / Weiter
- Seitenzahlen mit «…»
- Aktuelle Seite für Bildschirmleser markiert

Code: `@/components/ui/pagination`

### 1.5 [Schrittanzeige](https://intranet.sekbuelach.ch/gui-prototypen/stepper)

Zeigt Abläufe in Schritten (Geräteausgabe, Import, Versand): erledigt, aktuell, offen.

- Schritte mit Titel und Beschreibung
- Zurück / Weiter
- Auf dem Handy untereinander

Code: `@/components/ui/stepper`

### 1.6 [Befehlspalette](https://intranet.sekbuelach.ch/gui-prototypen/befehlspalette)

Mit Ctrl+K schnell zu Seiten, Personen und Aktionen springen – ohne Maus.

- Öffnen mit Ctrl+K (Mac: Cmd+K) oder Knopf
- Tippen filtert, Gruppen nach Art
- Pfeiltasten und Enter

Code: `@/components/ui/command-palette`

### 1.7 [Umschalter & Aufklappen](https://intranet.sekbuelach.ch/gui-prototypen/umschalter)

Umschaltgruppe für Ansichten, Ein/Aus-Knopf und ein aufklappbarer Bereich.

- Umschaltgruppe (z. B. Ansicht wählen)
- Ein/Aus-Knopf (Toggle)
- Aufklappbarer Bereich (Collapsible)

Code: `@/components/ui/toggle-group`, `@/components/ui/toggle`, `@/components/ui/collapsible`

### 1.8 [Kontextmenü](https://intranet.sekbuelach.ch/gui-prototypen/kontextmenue)

Aktionen mit Rechtsklick, langem Drücken (iPad) oder Shift+F10 – immer zusätzlich als sichtbarer Knopf «…».

- Rechtsklick, langes Drücken, Shift+F10, Menütaste
- Pfeiltasten, Esc
- Gleiche Aktionen im Knopf «…»

Code: `@/components/ui/context-menu`

### 1.9 [Geteilte Ansicht](https://intranet.sekbuelach.ch/gui-prototypen/geteilt)

Zwei Bereiche nebeneinander mit verschiebbarer Trennlinie, z. B. Liste links und Details rechts.

- Ziehen mit Maus und Touch
- Pfeiltasten, Pos1/Ende, Doppelklick zurück
- Grenzen für kleinste und grösste Breite

Code: `@/components/ui/resizable`

<a id="eingabe"></a>

## 2. Eingabe

Wie Angaben erfasst, gewählt und Dateien übergeben werden.

### 2.1 [Button](https://intranet.sekbuelach.ch/gui-prototypen/button)

Varianten (Haupt-, Neben-, Umriss-, Text-, Gefahr-Button), Grössen, mit Symbol, nur Symbol, lädt, gesperrt.

- Varianten und Grössen
- Mit Symbol, nur Symbol (mit Titel)
- Lädt, gesperrt

Code: `@/components/ui/button`, `@/components/delete-button`

### 2.2 [Knopfgruppe](https://intranet.sekbuelach.ch/gui-prototypen/knopfgruppe)

Zusammengehörende Knöpfe ohne Abstand, z. B. Export CSV / Excel, Ansicht Liste / Kacheln, Textformat.

- Aktionen mit Menü «Weitere»
- Auswahl der Ansicht (aria-pressed)
- Ein/Aus-Knöpfe (Fett, Kursiv)

Code: `@/components/ui/button-group`

### 2.3 [Formularfelder](https://intranet.sekbuelach.ch/gui-prototypen/formular)

Textfeld, Textbereich, Zahl, Datum, Kontrollkästchen, Beschriftung, Hinweis und Fehlermeldung.

- Text, Zahl, Textbereich
- Beschriftung, Hinweis, Fehler
- Kontrollkästchen, gesperrtes Feld
- Feldgruppen mit Überschrift, Einstellung als Zeile (Field)

Code: `@/components/ui/input`, `@/components/ui/textarea`, `@/components/ui/checkbox`, `@/components/form-field`, `@/components/input-error`, `@/components/ui/label`, `@/components/ui/field`

### 2.4 [Eingabegruppe](https://intranet.sekbuelach.ch/gui-prototypen/eingabegruppe)

Feld mit Symbol, Text davor/danach oder Knopf in einem Rahmen: Suche, «@sekbuelach.ch», «CHF», Kopieren.

- Suche mit Symbol und Leeren
- Text davor und danach
- Knopf im Feld (Kopieren)

Code: `@/components/ui/input-group`

### 2.5 [Dropdown](https://intranet.sekbuelach.ch/gui-prototypen/dropdown)

Auswahlfeld ohne und mit Vorauswahl, gesperrt, mit Fehler sowie Mehrfachauswahl.

- Ohne Vorauswahl («Bitte wählen»)
- Mit Vorauswahl
- Mit Fehler, kurze Mehrfachauswahl
- Einfaches Auswahlfeld des Browsers (Native Select)

Code: `@/components/select-field`, `@/components/ui/select`, `@/components/ui/native-select`

### 2.6 [Optionsfelder](https://intranet.sekbuelach.ch/gui-prototypen/radio)

Genau eine Wahl aus wenigen (2–5) sichtbaren Möglichkeiten, z. B. Zielgruppe oder Art der Abgabe.

- Untereinander und nebeneinander
- Mit Hinweis je Möglichkeit
- Gesperrte Möglichkeit, Fehler

Code: `@/components/ui/radio-group`

### 2.7 [Schalter](https://intranet.sekbuelach.ch/gui-prototypen/switch)

Ein/Aus für Einstellungen, die sofort gelten (z. B. Benachrichtigungen). In Formularen mit «Speichern» Kontrollkästchen verwenden.

- Schalter mit Beschriftung und Hinweis
- Gesperrt
- Wann Schalter, wann Kontrollkästchen

Code: `@/components/ui/switch`

### 2.8 [Auswahl mit Suche](https://intranet.sekbuelach.ch/gui-prototypen/combobox)

Auswahl aus langen Listen mit Suchfeld (Combobox), z. B. eine Person aus 500 oder ein Gerät.

- Tippen filtert (ohne Akzente, Gross/Klein)
- Zusatzangabe je Eintrag
- Pfeiltasten, Enter, Esc

Code: `@/components/ui/combobox`

### 2.9 [Mehrfachauswahl](https://intranet.sekbuelach.ch/gui-prototypen/mehrfachauswahl)

Mehrere Werte wählen, z. B. Klassen oder Zielgruppen – mit Suche, «Alle» / «Keine» und entfernbaren Kennzeichen.

- Suche in der Liste
- Alle / Keine
- Gewählte Werte als Kennzeichen, einzeln entfernbar

Code: `@/components/ui/multi-select`

### 2.10 [Datumsauswahl](https://intranet.sekbuelach.ch/gui-prototypen/datum)

Datum eintippen (TT.MM.JJJJ) oder im Kalender wählen; Woche beginnt am Montag, Bereich einschränkbar.

- Eintippen oder Kalender
- Frühestes / spätestes Datum
- Tastatur: Pfeile, Bild auf/ab

Code: `@/components/ui/calendar`

### 2.11 [Datei hochladen](https://intranet.sekbuelach.ch/gui-prototypen/upload)

Ablagefläche zum Hineinziehen oder Anklicken (Klassenliste, Escola-Export, Dokument, PDF für ePost) mit Prüfung von Typ und Grösse.

- Eine oder mehrere Dateien
- Erlaubte Dateitypen, Höchstgrösse
- Liste mit Entfernen; Dateien bleiben im Browser bis zum Senden
- Anhang mit Vorschaubild, Fortschritt und Fehler (Attachment)

Code: `@/components/ui/file-drop`, `@/components/ui/attachment`

### 2.12 [Schieberegler](https://intranet.sekbuelach.ch/gui-prototypen/slider)

Wert in einem Bereich einstellen (z. B. Anzahl Geräte, Prozent); der Wert steht immer daneben.

- Mit Einheit
- Schritte
- Tastatur: Pfeile

Code: `@/components/ui/slider`

### 2.13 [Drag & Drop](https://intranet.sekbuelach.ch/gui-prototypen/dragdrop)

Ziehen und Ablegen mit dnd-kit: Liste umsortieren, Zuordnen (Schüler:innen → Klassen) und Kanban-Tafel. Alles geht auch ohne Ziehen (Tastatur, Knöpfe, «Nach …»).

- Liste umsortieren: Griff, Tastatur (Leertaste, Pfeile), Nach oben/unten
- Zuordnen: Karten in Spalten ziehen oder «Nach …» wählen
- Kanban: Status von Tickets/Bestellungen
- Dateien: siehe «Datei hochladen»
- Tabelle: Spalten ziehen oder im Spaltenmenü verschieben

Code: `@/components/ui/sortable-list`, `@/components/ui/drag-board`, `@/components/ui/file-drop`

### 2.14 [Fragebogen](https://intranet.sekbuelach.ch/gui-prototypen/fragebogen)

Umfrage in Schritten mit Einfach-, Mehrfachauswahl und Freitext, überspringbaren Fragen und Übersicht vor dem Absenden (Modul «Umfragen»).

- Eine Frage pro Schritt mit Fortschritt
- Pflichtfragen und Überspringen
- Übersicht mit «Ändern»

Code: `@/components/ui/questionnaire`

<a id="daten"></a>

## 3. Daten anzeigen

Wie Listen, Kennzahlen und Verläufe dargestellt werden.

### 3.1 [Tabellen](https://intranet.sekbuelach.ch/gui-prototypen/tabelle)

Bearbeitbare Tabelle (Grundlage: Personen) mit Suche, Filtern, Sortierung, Spaltenwahl, Auswahl, Export und einfache Tabelle.

- Bearbeitbare Tabelle wie in Excel
- Filter je Spalte, Sortierung, Spalten, Ansichten
- Einfache Tabelle für kurze Listen
- Optional: «Neue Zeile» (`onAddRow`), eigene Knöpfe in der Werkzeugleiste (`toolbar`), weitere Exporte
  (`extraExports`, z. B. PDF/Markdown im Glossar), Gruppieren nach Mehrfachwerten (`separator` je Spalte)
- Spaltentyp `multi` (Mehrfachauswahl, z. B. Kategorie im Glossar): Filter «enthält eine/keine von», Bearbeiten in
  der Zelle mit Häkchen-Liste (Pfeile, Leertaste, Enter übernimmt, Esc bricht ab); Wert als Text «A; B»
- Farbige Werte (`colors` je Spalte) auch in Auswahlliste und Filter; Datumszellen mit Kalender (Leeren, Heute);
  Sortieren in bis zu drei Stufen (Knopf «Sortieren» oder Umschalt+Klick im Spaltenkopf)
- Spalten ziehen mit farbigem Einrastpunkt; Dropdowns und Kalender erscheinen oberhalb, wenn unten kein Platz ist;
  Auswahllisten mit Ziehecke rechts unten; Kalender mit Monatsauswahl (Klick auf den Monat) und Jahres-Spinner

Code: `@/components/data-grid`, `@/components/ui/table`

### 3.2 [Typografie](https://intranet.sekbuelach.ch/gui-prototypen/typografie)

Einheitliche Überschriften, Absätze, Listen, Zitate, Code im Text, Tasten (Kbd) und Trennlinien – Grundlage für Mitteilungen und Handbuch.

- Überschriften H1–H4, Einleitung, Text
- Listen, Zitat, leiser und kleiner Text
- Code im Text, Tasten, Trennlinie

Code: `@/components/ui/typography`, `@/components/ui/kbd`, `@/components/ui/separator`

### 3.3 [Eintrag](https://intranet.sekbuelach.ch/gui-prototypen/eintrag)

Zeile mit Symbol oder Bild, Titel, Beschreibung, Zusatzangabe und Aktionen – für Listen von Personen, Dokumenten, Plugins.

- Liste mit Trennlinien
- Mit Kennzeichen und Aktionen
- Gedämpfte Variante

Code: `@/components/ui/item`

### 3.4 [Karten & Seitenkopf](https://intranet.sekbuelach.ch/gui-prototypen/karten)

Seitenkopf mit Titel und Beschreibung, Karten mit Kopf, Inhalt und Fuss, Kennzahlen, leerer Zustand.

- Seitenkopf mit Aktion
- Karte, Kennzahl
- Leerer Zustand

Code: `@/components/ui/card`, `@/components/page-header`, `@/components/empty-state`, `@/components/heading`

### 3.5 [Badges, Status & Avatar](https://intranet.sekbuelach.ch/gui-prototypen/badge)

Kennzeichen für Status, Auswahlwerte in den Farben des Farbkonzepts, Zähler und Avatar (Initialen).

- Grundformen und Zähler
- Auswahlwerte im Farbkonzept
- Avatar mit Initialen

Code: `@/components/ui/badge`, `@/components/option-badge`, `@/components/ui/avatar`

### 3.6 [Akkordeon](https://intranet.sekbuelach.ch/gui-prototypen/akkordeon)

Mehrere aufklappbare Abschnitte, z. B. Fragen & Antworten im Handbuch; einer oder mehrere offen.

- Nur einer offen
- Mehrere offen
- Überschrift als Knopf (Bildschirmleser)

Code: `@/components/ui/accordion`

### 3.7 [Fortschritt](https://intranet.sekbuelach.ch/gui-prototypen/fortschritt)

Fortschrittsbalken für Import, Versand oder Abgabe; bestimmt (Prozent) oder unbestimmt.

- Mit Prozent
- Unbestimmt (Dauer unbekannt)
- Kennzahl mit Balken (z. B. Lizenzen)

Code: `@/components/ui/progress`

### 3.8 [Diagramme](https://intranet.sekbuelach.ch/gui-prototypen/diagramm)

Balken-, Linien- und Ringdiagramme für Kennzahlen (Lizenzen, Geräte, Tickets) in den Farben des Farbkonzepts, immer auch als Tabelle.

- Einfache Balken
- Zwei Werte je Zeile (z. B. gekauft / zugewiesen)
- Verlauf als Linien
- Anteile als Ring
- Umschalter «Als Tabelle»

Code: `@/components/ui/bar-chart`, `@/components/ui/line-chart`, `@/components/ui/donut-chart`

### 3.9 [Zeitleiste](https://intranet.sekbuelach.ch/gui-prototypen/zeitleiste)

Verlauf mit Datum, z. B. Versionen, Änderungen, Status einer Bestellung (neueste oben).

- Eintrag mit Datum, Titel, Beschreibung
- Farbe nach Art (Erfolg, Warnung, Hinweis)

Code: `@/components/ui/timeline`

### 3.10 [Scrollbereich](https://intranet.sekbuelach.ch/gui-prototypen/scrollbereich)

Bereich mit fester Höhe oder Breite und schmaler, einheitlicher Bildlaufleiste; mit Tastatur erreichbar.

- Senkrecht (lange Liste)
- Waagrecht (Klassen, Kacheln)
- Fokus und Pfeiltasten

Code: `@/components/ui/scroll-area`

### 3.11 [Codeblock](https://intranet.sekbuelach.ch/gui-prototypen/codeblock)

Code, Konsolenausgabe und Vergleiche mit Kopfzeile, Zeilennummern und Kopieren – für API-Tester, Plugins und das Skript der Geräteausgabe.

- Mit Zeilennummern
- Konsolenausgabe
- Vergleich (+/−), Kopieren

Code: `@/components/ui/code-block`

<a id="rueckmeldung"></a>

## 4. Rückmeldung

Wie das Intranet antwortet: Meldungen, Rückfragen, Laden, Hinweise.

### 4.1 [Meldungen & Dialoge](https://intranet.sekbuelach.ch/gui-prototypen/meldungen)

Hinweisboxen, Rückfrage-Dialog, kurze Meldungen (Toast) und Tooltips.

- Hinweisbox (Info, Fehler)
- Rückfrage vor Löschen
- Toast und Tooltip

Code: `@/components/ui/alert`, `@/components/ui/dialog`, `@/components/ui/tooltip`, `sonner`, `@/components/alert-error`

### 4.2 [Rückfrage](https://intranet.sekbuelach.ch/gui-prototypen/rueckfrage)

Dialog, der bewusst beantwortet werden muss (Alert Dialog): schliesst nicht mit Klick daneben, Fokus zuerst auf «Abbrechen». Für Löschen und unumkehrbare Schritte.

- Gefährlich (Löschen)
- Wichtig (Freigeben)
- Fokus auf «Abbrechen», kein Schliessen daneben

Code: `@/components/ui/alert-dialog`

### 4.3 [Popover](https://intranet.sekbuelach.ch/gui-prototypen/popover)

Kleines Fenster an einem Knopf für Hilfe, Filter oder eine kurze Auswahl; schliesst mit Esc oder Klick daneben.

- Hilfe zu einem Feld
- Kleiner Filter mit Übernehmen
- Fokus zurück zum Knopf

Code: `@/components/ui/popover`, `@/components/grid/popover`

### 4.4 [Vorschaukarte](https://intranet.sekbuelach.ch/gui-prototypen/vorschaukarte)

Kleine Vorschau beim Überfahren oder Fokussieren eines Links (Hover Card), z. B. einer Person – nur Zusatzinformation.

- Öffnet mit Maus und Tastatur (Fokus)
- Kurze Verzögerung, Esc schliesst
- Link führt immer zur ganzen Seite

Code: `@/components/ui/hover-card`

### 4.5 [Banner](https://intranet.sekbuelach.ch/gui-prototypen/banner)

Hinweis über die ganze Breite oben auf der Seite, z. B. Wartung, Prototyp, neue Version.

- Info, Warnung, Erfolg
- Mit Aktion
- Schliessbar

Code: `@/components/ui/banner`

### 4.6 [Ladezustände](https://intranet.sekbuelach.ch/gui-prototypen/laden)

Ladeanzeige (Spinner) und Platzhalter (Skeleton), solange Daten geladen werden.

- Spinner
- Platzhalter in der Form des Inhalts

Code: `@/components/ui/spinner`, `@/components/ui/skeleton`

<a id="querschnitt"></a>

## 5. Für alle Bausteine

- **[Hell- und Dunkelmodus, Farben](https://intranet.sekbuelach.ch/farbkonzept):** Farben nur über die Farbwerte in resources/css/app.css und config/intranet.php → colors; jeder Baustein funktioniert hell und dunkel.
- **[Tastatur und Bildschirmleser](https://intranet.sekbuelach.ch/gui-prototypen/dragdrop):** Alles ohne Maus bedienbar, sichtbarer Fokus, Beschriftungen für Knöpfe mit Symbol; Ziehen immer mit Alternative (WCAG 2.2, 2.5.7).
- **Handy und Tablet:** Jeder Baustein passt sich der Breite an; Touch-Ziele mindestens 24 × 24 Pixel; Ziehen auf dem iPad mit kurzem Halten.
- **[Mehrsprachigkeit](https://intranet.sekbuelach.ch/sprachkonzept):** Texte im Code auf Deutsch, Übersetzungen in den Katalogen; Namen und Daten mit translate="no" schützen.

<a id="spaeter"></a>

## 6. Später

Bausteine aus shadcn/ui, die wir bewusst noch nicht umsetzen.

| Baustein                              | Wofür                                                            | Warum später                                 |
| ------------------------------------- | ---------------------------------------------------------------- | -------------------------------------------- |
| Seitenverhältnis (Aspect Ratio)       | Bilder und Videos in festem Format.                              | niedrige Priorität                           |
| Karussell (Carousel)                  | Bilder zum Durchblättern, z. B. Pinnwand oder Startseite.        | niedrige Priorität                           |
| Menüleiste (Menubar)                  | Menüleiste wie in Programmen.                                    | niedrige Priorität                           |
| Einmal-Code (Input OTP)               | Eingabe von Codes in Feldern pro Ziffer.                         | niedrige Priorität – Anmeldung über Entra ID |
| Schreibrichtung (Direction)           | Schrift von rechts nach links.                                   | nicht nötig (Deutsch, Englisch)              |
| Chat-Blase (Bubble)                   | Nachricht in einer Unterhaltung mit Reaktionen.                  | Chat / KI – später                           |
| Nachricht (Message)                   | Nachricht mit Avatar, Kopf und Fuss.                             | Chat / KI – später                           |
| Nachrichtenverlauf (Message Scroller) | Scrollbereich für Unterhaltungen.                                | Chat / KI – später                           |
| Markierung (Marker)                   | Status oder Trenner in einer Unterhaltung.                       | Chat / KI – später                           |
| Ablauf (Trace)                        | Schritte eines KI-Assistenten: Überlegungen, Werkzeuge, Quellen. | Chat / KI – später                           |

<a id="drag-und-drop"></a>

## Drag & Drop

**Bibliothek:** [dnd-kit](https://dndkit.com) (`@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities`, MIT) für
Listen, Zuordnen und Kanban. Gewählt, weil es Maus, Touch (iPad) **und Tastatur** unterstützt und Bildschirmlesern
meldet, was verschoben wird. Für Dateien genügt das Drag & Drop des Browsers (Baustein «Datei hochladen»).

**Bausteine**

| Baustein                     | Datei                             | Wofür                                               |
| ---------------------------- | --------------------------------- | --------------------------------------------------- |
| `SortableList`               | `components/ui/sortable-list.tsx` | Reihenfolge ändern (Links, Menüpunkte, Checklisten) |
| `DragBoard`                  | `components/ui/drag-board.tsx`    | Zuordnen (Schüler:innen → Klassen), Kanban (Status) |
| `FileDrop`                   | `components/ui/file-drop.tsx`     | Dateien ablegen (Klassenliste, Dokumente, PDF)      |
| Tabelle: Spalten verschieben | `components/data-grid.tsx`        | Spaltenkopf ziehen oder Spaltenmenü                 |

**Regeln (verbindlich)**

1. **Alles geht auch ohne Ziehen** (WCAG 2.2, Kriterium 2.5.7 «Ziehbewegungen»):
    - Liste: Griff fokussieren, Leertaste, Pfeiltasten, Leertaste (Esc bricht ab) – und Knöpfe «Nach oben / Nach unten»
    - Tafel: je Karte die Auswahl «Nach …»
    - Dateien: Ablagefläche ist anklickbar und mit Tab/Enter erreichbar
    - Tabelle: im Spaltenmenü «Nach links / Nach rechts»
2. **Ziehen nur am Griff** (Symbol ⋮⋮), damit Klicken, Markieren und Scrollen normal funktionieren. Auf dem iPad
   startet das Ziehen nach kurzem Halten (150 ms), damit Wischen die Seite scrollt.
3. **Ansagen auf Deutsch** für Bildschirmleser («… aufgenommen», «… abgelegt bei Platz 3», «abgebrochen»).
4. **Sichtbare Rückmeldung:** gezogene Karte hervorgehoben, Ziel-Spalte eingefärbt (Farben aus dem Farbkonzept).
5. **Speichern erst nach dem Ablegen** und mit Rückmeldung (Toast); bei Fehlern zurück an den alten Platz.
6. **Dateien bleiben im Browser**, bis die Seite sie bewusst sendet; Typ und Grösse werden vorher geprüft.

**Einsatz im Intranet (geplant):** Reihenfolge von Links und Menüpunkten, Zuordnen von Schüler:innen zu Klassen und
Gruppen, iPads zu Personen, Kanban für Tickets, Bestellungen und Geräterücknahme, Reservationen und Sporttag.
