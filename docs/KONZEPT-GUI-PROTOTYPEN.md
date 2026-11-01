# Konzept GUI-Prototypen (ab 0.27.0, erweitert 0.28.0)

Ziel: Das Intranet sieht am Ende einheitlich aus. Jeder Baustein der Oberfläche (Menü, Tabelle, Dropdown,
Button …) hat **eine verbindliche Vorlage**. Verbesserungen werden am Baustein entwickelt, geprüft und
freigegeben – danach werden alle Seiten, die ihn verwenden, angepasst.

## Menü

Hauptmenü «GUI-Prototypen» (letzter Menüpunkt, nur Administration):

- **Inhaltsverzeichnis** (`/gui-prototypen`): alle Bausteine nach Bereich nummeriert, mit Erklärung, Bestandteilen,
  Status und Link zum Prototyp, dazu die Regeln «Für alle Bausteine». Gleicher Inhalt als Datei:
  [GUI-KOMPONENTEN.md](GUI-KOMPONENTEN.md).
- **Freigaben & Status** (`/gui-prototypen/freigaben`): Tabelle mit verbindlicher Version, Version in Arbeit, offener
  Überarbeitung und Wünschen.
- Untermenüs **Navigation & Aufbau**, **Eingabe**, **Daten anzeigen**, **Rückmeldung** mit je einer Seite pro Baustein
  und den Reitern **Beispiele**, **Versionen & Status**, **Überarbeitung**, **Wünsche**.

Bausteine (ab 0.28.0, 29): Menü, Seitenpanel, Reiter (Tabs), Seitenweise Anzeige, Schrittanzeige, Befehlspalette,
Umschalter & Aufklappen · Button, Formularfelder, Dropdown, Optionsfelder, Schalter, Auswahl mit Suche,
Mehrfachauswahl, Datumsauswahl, Datei hochladen, Schieberegler, Drag & Drop · Tabellen, Karten & Seitenkopf, Badges,
Status & Avatar, Akkordeon, Fortschritt, Diagramme, Zeitleiste · Meldungen & Dialoge, Popover, Banner, Ladezustände.

Neuer Baustein: Eintrag in `config/intranet.php` → `gui_components` (Bereich `category`, Titel, Symbol,
Beschreibung, `parts`, `imports`), Komponente unter `resources/js/components/ui/`, Beispiele in
`resources/js/components/gui-demos.tsx` bzw. `gui-demos-extra.tsx`. `GuiPrototypesTest` prüft Bereich, Symbol,
Beispiele und Imports. Drag & Drop: Regeln in [GUI-KOMPONENTEN.md](GUI-KOMPONENTEN.md#drag-und-drop).

## Status und Versionen

| Status      | Bedeutung                                                    | Datum         |
| ----------- | ------------------------------------------------------------ | ------------- |
| Entwicklung | Baustein wird gebaut bzw. geändert                           | `created_at`  |
| Prüfung     | Beispiele, Barrierefreiheit, Farbkonzept werden kontrolliert | `review_at`   |
| Freigegeben | verbindliches Template                                       | `released_at` |

- Versionsnummer SemVer je Baustein: Korrektur (x.x.+1), Erweiterung (x.+1.0), grundlegend neu (+1.0.0).
- Es ist immer höchstens **eine Version offen**. Prüfung kann mit Bemerkung an die Entwicklung zurückgehen.
- Jeder Schritt wird mit Datum, Person und Bemerkung im Verlauf (`log`) und im Änderungsverlauf festgehalten.
- Bis zur Freigabe einer neuen Version bleibt die zuletzt freigegebene verbindlich; ältere gelten als «abgelöst».
- Ausgangslage: Version 1.0.0 in Entwicklung (der bestehende Baustein).

## Nach einer Freigabe

Das Intranet sucht alle Seiten unter `resources/js/pages`, die den Baustein importieren (`imports`), und legt sie
als **«zu überarbeiten»** an. Jede Seite wird geprüft, an die Vorlage angepasst und abgehakt. Die Übersicht zeigt
offene Überarbeitungen je Baustein. Eine neue Freigabe ersetzt die Liste der vorherigen.

## Verbesserungswünsche

Wünsche werden beim Baustein erfasst (Text, Person, Datum) und beim Anlegen der nächsten Version übernommen
(«in v1.1.0»). So bleibt nachvollziehbar, woher eine Änderung kommt.

## Daten

Sammlung `gui_components` (Schema in `config/intranet.php`), Logik in `App\Support\GuiPrototypes`, Seiten
`resources/js/pages/gui-prototypes/*`, Status als Auswahlliste `gui_statuses` mit Farben aus dem Farbkonzept.
