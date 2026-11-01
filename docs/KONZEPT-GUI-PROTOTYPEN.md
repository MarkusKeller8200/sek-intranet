# Konzept GUI-Prototypen (ab 0.27.0)

Ziel: Das Intranet sieht am Ende einheitlich aus. Jeder Baustein der Oberfläche (Menü, Tabelle, Dropdown,
Button …) hat **eine verbindliche Vorlage**. Verbesserungen werden am Baustein entwickelt, geprüft und
freigegeben – danach werden alle Seiten, die ihn verwenden, angepasst.

## Menü

Hauptmenü «GUI-Prototypen» (letzter Menüpunkt, nur Administration): «Übersicht & Freigaben» und je Baustein ein
Untermenü mit den Reitern **Beispiele** (Live-Beispiele mit den echten Komponenten, erfundene Daten),
**Versionen & Status**, **Überarbeitung** und **Wünsche**.

Bausteine (`config/intranet.php` → `gui_components`): Menü, Tabellen (Grundlage: Tabelle «Personen»), Dropdown
(ohne/mit Vorauswahl, Fehler, Mehrfachauswahl), Button, Formularfelder, Badges & Status, Meldungen & Dialoge,
Karten & Seitenkopf, Reiter & Umschalter, Ladezustände. Neuer Baustein: Eintrag in `gui_components` (Titel,
Symbol, Beschreibung, `imports`) und Beispiele in `resources/js/components/gui-demos.tsx`.

## Status und Versionen

| Status        | Bedeutung                                                    | Datum           |
| ------------- | ------------------------------------------------------------ | --------------- |
| Entwicklung   | Baustein wird gebaut bzw. geändert                           | `created_at`    |
| Prüfung       | Beispiele, Barrierefreiheit, Farbkonzept werden kontrolliert | `review_at`     |
| Freigegeben   | verbindliches Template                                       | `released_at`   |

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
