# Konzept: Glossar

Stand 03.11.2026, abgestimmt im Chat; Umsetzung im Meilenstein «Glossar». Grundlage für den Startinhalt: Recherche
«Abkürzungs- und Begriffsverzeichnis der Zürcher Volksschule» vom 03.11.2026 (VSA, Bildungsdirektion).

## Ziel

Ein zentrales Begriffsverzeichnis für das ganze Intranet, damit alle mit denselben Begriffen arbeiten. Das Glossar
gehört zum **Core** (Module nutzen es über eine Core-Schnittstelle). Menü: «Wissen & Ablage» → «Glossar».

## Spalten

| #   | Spalte                       | Regel                                                                                                                       |
| --- | ---------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| 1   | Kategorie                    | eine oder mehrere aus der erweiterbaren Kategorienliste (mehrere nach dem Zusammenführen gleicher Begriffe)                 |
| 2   | Abkürzung offiziell + Aliase | gesperrt (siehe unten); Aliase = andere Schreibweisen                                                                       |
| 3   | Abkürzung eigene + Alias     | frei                                                                                                                        |
| 4   | Bezeichnung offiziell        | gesperrt                                                                                                                    |
| 5   | Bezeichnung eigene           | wird im Intranet überall angezeigt, wo auf den Eintrag verwiesen wird; leer = offizielle                                    |
| 6   | Kurzbeschreibung             | ein Satz (max. 160 Zeichen) für den Tooltip                                                                                 |
| 6a  | Beschreibung                 | ausführlicher Text                                                                                                          |
| 7   | URL                          | Quelle, http(s)                                                                                                             |
| 8   | Notiz                        | Markdown; **gemeinsam für alle**, später je Mandant                                                                         |
| 9   | Herkunft                     | Mehrfachwerte aus der erweiterbaren Herkunftsliste (VSA, BI, Kanton, Bund, DSB, Gemeindeamt, Schule intern, gebräuchlich …) |
| 10  | Intern genutzt in            | Module/Bereiche: automatisch erkannte Bezüge plus Tags von Hand, als Links                                                  |

Zusätzlich: Status (aktuell / historisch / zu prüfen), Suchbegriffe, optional gültig ab/bis. Kategorien- und
Herkunftsliste haben Abkürzung, Bezeichnung, Beschreibung, URL, Notiz (wie die Einstellungen der Abläufe, #52).

## Regeln

- **Offiziell nicht änderbar:** Spalten 2 und 4 sind in der normalen Bearbeitung gesperrt. Ändern nur mit Recht
  «offizielle Begriffe pflegen» (Vorgabe Administration) oder per Import mit diesem Recht; immer im Änderungsverlauf.
- **Mehrdeutige Abkürzungen** (SHP, BFS, DSB, SV, SL …) sind erlaubt: eigene Einträge, Kennzeichen «mehrdeutig».
- **Doppelte zusammenführen:** nur wenn Abkürzung **und** Bedeutung gleich sind; der zusammengeführte Eintrag erhält
  **alle Kategorien** der Einzeleinträge (z. B. SHP: Behörden & Organisation, Sonderpädagogik, Personal). Gleiche
  Abkürzung mit anderer Bedeutung bleibt getrennt (mehrdeutig).
- **Ablauf:** Der Startinhalt wird **ohne Zusammenführen** übernommen (jede Zeile der Recherche ein Eintrag). Danach
  hilft der Knopf **«Doppelte Abkürzungen oder Begriffe»**: zeigt Gruppen gleicher Abkürzung bzw. gleicher
  Bezeichnung nebeneinander; je Gruppe «zusammenführen» (Kategorien, Aliase, Herkunft, genutzt in werden vereinigt,
  Texte zur Auswahl) oder «als mehrdeutig belassen». Nichts geschieht automatisch.
- **Ersetzen:** Die eigene Bezeichnung erscheint dort, wo das Intranet auf den Eintrag verweist (Baustein «Begriff»,
  Verknüpfungen aus Modulen). Freitext wird nicht verändert. Welche Stellen eines Moduls verweisen, wird im Einzelfall
  bei der Überarbeitung des Moduls festgelegt.
- Rollen/Fachbereiche (#52) und Personengruppen: Verknüpfung mit dem Glossar wird **später** entschieden.

## Begriffe im Intranet erkennen (Tooltip)

Wo im Intranet eine Abkürzung oder ein Begriff aus dem Glossar vorkommt, ist er **fein gepunktet unterstrichen**.
Beim Darüberfahren (bzw. Fokus mit der Tastatur, Antippen auf dem Handy) zeigt ein Tooltip **Abkürzung, Bezeichnung
und Kurzbeschreibung**; ein Link führt direkt zum Eintrag im Glossar.

- **Im Code (Oberfläche):** Baustein «Begriff» (`<Term>`), z. B. `<Term id="vsa" />` oder `<Term match="SL">SL</Term>`.
  Neuer GUI-Baustein in den GUI-Prototypen; Regel in `AGENTS.md`: Begriffe aus dem Glossar in Oberflächentexten mit
  dem Baustein auszeichnen.
- **Im Inhalt (Freitext):** Mitteilungen, Abläufe, Beschreibungen – Begriffe werden beim Anzeigen erkannt und
  ausgezeichnet, der gespeicherte Text bleibt unverändert. Mehrdeutige Abkürzungen zeigen alle Bedeutungen.
- **Farb- und Themenkonzept:** neue Tokens `--term-underline` (Farbe der Punkte) und `--term-underline-hover` in
  `resources/css/app.css` (hell und dunkel), in `App\Support\Themes::GROUPS` und `lib/themes.ts`, damit Themes sie
  übersteuern. Darstellung: `text-decoration: underline dotted` mit dünner Linie und Abstand, Kontrast AA; Tooltip
  mit dem bestehenden Baustein (Hover-Karte).
- Abschaltbar je Person (Einstellung «Begriffe hervorheben») und nicht in Eingabefeldern, Code-Blöcken und Links.

**Umsetzung (#60, #61):**

- Daten: `GET /glossar/begriffe` (Recht `view-glossary`, `App\Support\Glossary::terms()`): je Eintrag angezeigte
  Abkürzung und Bezeichnung, Kurzbeschreibung und alle Schreibweisen (Abkürzungen, Aliase). Mit ETag; der Browser
  fragt nach jedem Seitenwechsel nach (`lib/glossary-terms.ts`), damit eine geänderte eigene Bezeichnung sofort
  überall erscheint.
- Baustein: `components/ui/term.tsx`. Ein Eintrag = Link zum Glossar (erstes Antippen zeigt den Tooltip, das zweite
  öffnet den Eintrag); mehrere = Knopf mit Liste der Bedeutungen. Esc schliesst, Tooltip auch mit der Tastatur.
- Freitext: `components/term-text.tsx`, eingebaut in `Markdown`, Mitteilungen, Termine und Abläufe (Schritte,
  Bemerkungen, Personengruppen). Erkannt werden ganze Wörter mit gleicher Gross-/Kleinschreibung, nur Schreibweisen
  ab zwei Zeichen mit Grossbuchstabe, Ziffer oder Satzzeichen (gewöhnliche Wörter bleiben unberührt). Begriffe ohne
  Abkürzung werden im Freitext nicht ausgezeichnet.
- Einstellung «Begriffe hervorheben» unter Darstellung, gilt für den Browser (wie Schriftart und Sprache).
- «Intern genutzt in»: Links zum ersten sichtbaren Menüpunkt des Bereichs bzw. zum Modul; ohne Link, wenn die Person
  den Bereich nicht sieht. Automatisch erkannte Bezüge folgen, sobald Module mit dem Glossar verknüpft sind.
- Später: Notiz und eigene Bezeichnungen je Mandant.

## Darstellung und Bearbeitung

- **Tabelle** nach dem GUI-Baustein «Tabellen» (`@/components/data-grid`): Suche, Filter je Spalte, Sortierung,
  Spaltenwahl, gespeicherte Ansichten, **Gruppierung nach Kategorie** (Einträge mit mehreren Kategorien erscheinen in
  jeder ihrer Gruppen). **Bearbeiten direkt in den Zellen** und **neue Zeilen direkt in der Tabelle**
  (Erweiterung des Bausteins) – gesperrte Felder sind in der Tabelle nicht bearbeitbar.
- **Einzeldatensatz:** Detailansicht (offiziell und eigen nebeneinander, Herkunft als Tags, «genutzt in» als Links,
  Notiz als Markdown) und Formular mit Markdown-Vorschau.
- Index A–Z.

## Export und Import

- **Export** der ganzen oder gefilterten Liste als **PDF** (Druckansicht) und als **Markdown**.
- **Excel (XLSX) Export und Import** für die Pflege ausserhalb des Intranets: Export mit ID-Spalte; Import legt
  Zeilen ohne ID neu an und aktualisiert Zeilen mit ID; Mehrfachwerte (Aliase, Herkunft, genutzt in) mit `;`
  getrennt; unbekannte Herkunft/Kategorie werden als Fehler gemeldet oder auf Wunsch neu angelegt. **Vorschau** vor
  dem Übernehmen (neu / geändert / Fehler), gesperrte Felder nur mit Recht, **nie löschen**. Leere Vorlage zum
  Herunterladen.

## Rechte (je Rolle einstellbar)

- Glossar ansehen: alle Mitarbeitenden
- Glossar pflegen: Administration, Schulleitung, Schulverwaltung
- Offizielle Begriffe pflegen: Administration

## Startinhalt

Quelle der Liste: **Volksschulamt (VSA)**, https://www.zh.ch/de/bildung/schulen/volksschule.html (Recherche vom
03.11.2026). Alle Einträge erhalten die Herkunft **VSA** mit dieser URL; die Herkunftsliste enthält «VSA –
Volksschulamt des Kantons Zürich» mit derselben URL.

10 Kategorien und alle Zeilen der Recherche als einzelne Einträge (rund 140, **ohne Zusammenführen**); das Bereinigen
der Doppelten folgt danach mit dem Knopf «Doppelte Abkürzungen oder Begriffe» (#62). Was die Recherche selbst als
gebräuchliche Kurzform (SV, KLP, QM, QS, QE …) oder als fachliche Ergänzung (ICT-Begriffe) kennzeichnet, erhält
zusätzlich den Status «zu prüfen», damit es beim Bereinigen auffällt.
