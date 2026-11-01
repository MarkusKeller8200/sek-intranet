# Konzept GUI-Prototypen (ab 0.27.0, erweitert 0.28.0)

Ziel: Das Intranet sieht am Ende einheitlich aus. Jeder Baustein der Oberfläche (Menü, Tabelle, Dropdown,
Button …) hat **eine verbindliche Vorlage**. Verbesserungen werden am Baustein entwickelt, geprüft und
freigegeben – danach werden alle Seiten, die ihn verwenden, angepasst.

## Menü

Hauptmenü «GUI-Prototypen» (letzter Menüpunkt, Recht `use-gui`: Administration, Schulleitung, Fachstelle ICT,
Redaktion):

- **Inhaltsverzeichnis** (`/gui-prototypen`): alle Bausteine nach Bereich nummeriert, mit Erklärung, Bestandteilen,
  Status und Link zum Prototyp, dazu die Regeln «Für alle Bausteine». Gleicher Inhalt als Datei:
  [GUI-KOMPONENTEN.md](GUI-KOMPONENTEN.md).
- **Freigaben & Status** (`/gui-prototypen/freigaben`): Tabelle mit verbindlicher Version, Version in Arbeit, offener
  Überarbeitung und Wünschen.
- Untermenüs **Navigation & Aufbau**, **Eingabe**, **Daten anzeigen**, **Rückmeldung** mit je einer Seite pro Baustein
  und den Reitern **Beispiele**, **Versionen & Status**, **Überarbeitung**, **Wünsche**. Über den Beispielen die Leiste
  **Vorschau** mit **Hell / Dunkel** und **Theme** (ab 0.29.0, siehe [KONZEPT-THEMES.md](KONZEPT-THEMES.md)).

Bausteine (ab 0.30.0, 40, Abgleich mit der Komponentenliste von shadcn/ui): Menü (inkl. Navigationsmenü),
Seitenpanel (inkl. Drawer), Reiter, Seitenweise Anzeige, Schrittanzeige, Befehlspalette, Umschalter & Aufklappen,
Kontextmenü, Geteilte Ansicht · Button, Knopfgruppe, Formularfelder (inkl. Feldgruppen), Eingabegruppe, Dropdown (inkl.
Native Select), Optionsfelder, Schalter, Auswahl mit Suche, Mehrfachauswahl, Datumsauswahl, Datei hochladen (inkl.
Anhang), Schieberegler, Drag & Drop, Fragebogen · Tabellen, Typografie (inkl. Kbd, Trennlinie), Eintrag, Karten &
Seitenkopf, Badges/Status/Avatar, Akkordeon, Fortschritt, Diagramme (Balken, Linien, Ring), Zeitleiste, Scrollbereich,
Codeblock · Meldungen & Dialoge, Rückfrage (Alert Dialog), Popover, Vorschaukarte, Banner, Ladezustände.
Bewusst später (`gui_later`, Abschnitt «Später» im Inhaltsverzeichnis): Aspect Ratio, Carousel, Menubar, Input OTP,
Direction sowie die Chat-/KI-Bausteine Bubble, Message, Message Scroller, Marker, Trace.

Neuer Baustein: Eintrag in `config/intranet.php` → `gui_components` (Bereich `category`, Titel, Symbol,
Beschreibung, `parts`, `imports`), Komponente unter `resources/js/components/ui/`, Beispiele in
`resources/js/components/gui-demos.tsx`, `gui-demos-extra.tsx` bzw. `gui-demos-more.tsx` (Ergänzungen bestehender
Bausteine über `DEMO_EXTENSIONS`). `GuiPrototypesTest` prüft Bereich, Symbol,
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

## Wer darf was, und wer erfährt es (ab 0.31.0)

| Schritt               | Wer darf                        | Was passiert                                                           | Benachrichtigt (Glocke)                              |
| --------------------- | ------------------------------- | ---------------------------------------------------------------------- | ---------------------------------------------------- |
| Neue Version anlegen  | Administration (`manage-users`) | Version x.y.z in «Entwicklung»; gewählte Wünsche werden ihr zugeordnet | Autor:innen der übernommenen Wünsche                 |
| Zur Prüfung geben     | Administration                  | Status «Prüfung», Datum und Person, optional Bemerkung                 | alle mit `review-gui` (Administration, Schulleitung) |
| Zurück an Entwicklung | `review-gui`                    | Status «Entwicklung», Bemerkung im Verlauf                             | wer die Version angelegt hat und die Administration  |
| Freigeben             | `review-gui`                    | Version verbindlich, verwendende Seiten zur Überarbeitung vorgemerkt   | alle mit `use-gui`                                   |
| Wunsch erfassen       | alle mit `use-gui`              | Wunsch beim Baustein, «offen»                                          | Administration (Entwicklung)                         |
| Überarbeitung abhaken | Administration                  | Seite mit Datum und Person erledigt                                    | –                                                    |

Die auslösende Person erhält keine Meldung. Ein Klick auf die Benachrichtigung öffnet den Baustein direkt im
passenden Reiter (`?reiter=versionen|ueberarbeitung|wuensche`). Neue Meldungen erscheinen ohne Neuladen
(Abfrage alle 45 Sekunden) zusätzlich als kurze Meldung mit «Öffnen». Siehe
[KONZEPT-BENACHRICHTIGUNGEN.md](KONZEPT-BENACHRICHTIGUNGEN.md).
