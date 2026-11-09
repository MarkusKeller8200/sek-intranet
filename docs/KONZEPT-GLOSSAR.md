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
- **Vollexport (#157)**, nur mit Recht «Glossar pflegen»: Menü Export → «Excel vollständig (alle Daten)» – alle
  Einträge (auch inaktive) mit allen Feldern und zusätzlich «Intern genutzt in (Schlüssel)» (`bereich:…`,
  `modul:…`), «Doppelte geprüft» (`dubletten_ok`), «Erstellt», «Geändert». Der Import ignoriert diese Spalten; ein
  Vollexport lässt sich unverändert wieder importieren. Rohdaten als JSON: `php artisan intranet:glossar-export
  --datei=glossar.json` (Einträge mit allen gespeicherten Feldern, Bereiche, beide Listen).
- **Listen Kategorien und Herkunft (#157):** Auf der Seite «Kategorien & Herkunft» je Liste «Excel-Export» mit den
  Spalten ID, Kürzel, Bezeichnung, Label, Farbe, Beschreibung, URL, Notiz, Reihenfolge und «Excel-Import» mit
  Vorschau (neu / geändert / Fehler, Regeln wie im Formular, Kürzel eindeutig, **nie löschen**). Fehlende Spalten
  behalten den bisherigen Wert.

## Sync mit dem EduKSX-Glossary (#156)

Das EduKSX-Glossary (glossary.eduksx.ch) ist das führende System für Begriffe und Abkürzungen. Das Plugin
**EduKSX-Glossary** (`plugins/EduksxGlossary`) übernimmt die veröffentlichten Begriffe über die API v1, nach dem
Referenzclient `App\Glossary\SyncClient` des Glossary (dort `docs/SYNC-INTRANET.md`).

- **Ablauf:** erster Lauf mit dem Vollstand (`/api/v1/exports?format=json`), danach nur Änderungen
  (`/api/v1/changes?since=<cursor>`, seitenweise; Cursor nach jeder Seite gespeichert). Vorher prüft
  `/api/v1/releases/latest`, ob sich der Cursor geändert hat – sonst kein weiterer Abruf. Kategorien und Herkunft
  über `/categories` und `/sources` (Schlüssel = ID oder Feld `schluessel` der Liste, sonst Kürzel, Label oder
  Bezeichnung; fehlende werden mit UUID und dem Schlüssel in `schluessel` angelegt, #159; Bezeichnung und URL aus dem Glossary, Farbe und Notiz bleiben lokal).
- **Ereignisse:** `published` übernimmt den Begriff in dieser Revision (inaktiv → ausgeblendet), `unpublished`
  blendet aus, `merged` blendet aus, überträgt «Intern genutzt in», Doppelte, eigene Formen und Notiz (wo das Ziel
  leer ist) auf den verbleibenden Begriff und vermerkt die alte ID unter «Frühere IDs». Unbekannte Arten werden
  übersprungen. **Es wird nie gelöscht.** Wiederholbar: ein zweiter Lauf ohne Änderungen ändert nichts.
- **Gleiche IDs:** Die UUIDs im Glossary sind die IDs des Intranets. Übernommene Einträge tragen das Feld
  `glossary` (Zustand, Revision, Slug, Adresse) und zeigen «aus EduKSX-Glossary».
- **Gemeinsame Felder** (`Glossary::SHARED`: Kategorien, offizielle Abkürzung/Aliase/Bezeichnung, Kurzbeschreibung,
  Beschreibung, URL, Herkunft, Status, Gültigkeit, Aktiv) kommen aus dem Sync und sind im Intranet gesperrt – in
  Formular, Tabelle und Excel-Import (Änderungen später über Vorschläge im Glossary). Zusammenführen von Doppelten
  ist für solche Einträge gesperrt. Neu: `aliase_alternativ` (alternative Schreibweisen).
- **Lokal und nie überschrieben:** eigene Abkürzung, Bezeichnung und Alias, «Intern genutzt in», Notiz,
  Suchbegriffe, Doppelte.
- **Entscheid (07.11.2026):** Lokal angelegte Begriffe, die es im Glossary nicht gibt, bleiben lokal und
  bearbeitbar (Vorschläge ans Glossary kommen mit dem Meilenstein «Vorschläge und Moderation»).
- **Offen (klären, sobald der Glossary-Server online erreichbar ist):** ob der Excel-Import für übernommene Begriffe
  gesperrt wird. Bis dahin bleibt er offen, aber nur für die lokalen Felder (gemeinsame Felder gesperrt).
- **Zeitplan:** `php artisan intranet:glossar-sync` läuft alle fünf Minuten und synchronisiert, wenn das Plugin
  eingeschaltet und der Abstand aus den Einstellungen (Vorgabe 10 Minuten, 5–60) erreicht ist; `--jetzt` sofort,
  `--neu` mit Vollstand. Von Hand: Plugin → Aktion «Jetzt synchronisieren».
- **Stand und Fehler:** Sammlung `glossar_sync` (Cursor, Datenstand, letzter Abruf und Erfolg, Fehler, Zuordnung der
  Listen, Umleitungen); Statustext auf der Plugin-Karte, Läufe im Protokoll, Fehler zusätzlich im Log. Ohne
  Verbindung (oder bei HTTP 429 mit Retry-After) bleibt der lokale Stand.
- **Lizenz:** Daten unter CC BY 4.0 – «Quelle: EduKSX-Glossary, glossary.eduksx.ch (CC BY 4.0)» in der
  Glossar-Ansicht, in der Einzelansicht übernommener Einträge und in den Exporten PDF und Markdown, sobald
  übernommene Einträge enthalten sind.

### Im Glossar bedienen (#161)

Seite **Glossar → EduKSX-Glossary** (`/glossar/sync`, Recht «Glossar pflegen»): Stand (Adresse, letzter Sync,
Datenstand, Fehler, Token ja/nein), **Probelauf** (läuft wie der Sync gegen ein Abbild im Speicher,
`Plugins\EduksxGlossary\DryRunRepository`, und listet neu/geändert/ausgeblendet/Listen sowie die lokalen Begriffe
ohne Gegenstück – speichert nichts) und **«Mit EduKSX-Glossary synchronisieren»**. Ohne eingeschaltetes Plugin nur
der Hinweis «Sync noch nicht eingerichtet». Der Zeitplan läuft erst mit der Einstellung «Automatisch im Zeitplan
synchronisieren».

**Checkliste Go-live:**

1. Glossary-Server erreichbar; Vollexport (#157) und Listen im Glossary übernommen.
2. Plugin «EduKSX-Glossary»: Adresse prüfen, einschalten, «Verbindung testen».
3. Glossar → EduKSX-Glossary → **Probelauf** prüfen (keine unerwarteten Änderungen an eigenen Begriffen).
4. «Mit EduKSX-Glossary synchronisieren»; Gegenprobe im Glossary `php artisan glossary:sync-check <Adresse>`.
5. «Automatisch im Zeitplan synchronisieren» einschalten (Abstand 5–60 Minuten).
6. Offenen Entscheid Excel-Import für übernommene Begriffe treffen (#156).
7. Für Vorschläge: Servicekonto mit Scope «propose» im Glossary, Token im Plugin, «Schreibende Aktionen erlauben».

### Vorschläge ans EduKSX-Glossary (#162)

- Am Eintrag (Recht «Glossar pflegen», Plugin eingeschaltet, Token hinterlegt): **«Dem EduKSX-Glossary
  vorschlagen»** für lokale Begriffe, **«Korrektur vorschlagen»** für übernommene. Formular mit Abkürzung,
  Bezeichnung, Kurzbeschreibung, Beschreibung, URL, alternativen Schreibweisen, Kategorien (nur solche mit Schlüssel im
  Glossary) und Begründung; Senden erst nach Bestätigung.
- `POST /api/v1/proposals` mit Token des Servicekontos (Scope `propose`); bei Korrekturen nur geänderte Felder,
  `term` = ID. Gesendet werden nur Fachdaten – eigene Formen, «Intern genutzt in», Notiz, Suchbegriffe nie. Keine
  Personendaten in der Begründung.
- Vorschläge landen **immer in der Moderation** des Glossary (DEC-018/DEC-024) und werden erst nach Annahme als neue
  Revision veröffentlicht.
- Am Eintrag steht `vorschlaege` (Nummer, Art, Stand, Felder, Zeitpunkte); nach jedem erfolgreichen Sync fragt das
  Plugin offene Vorschläge nach (`GET /api/v1/proposals/{id}`, höchstens 50 je Lauf).
- Nach Annahme eines neuen Begriffs kommt er mit eigener ID über den Sync; der Sync verbindet ihn mit dem lokalen
  Eintrag (lokale Daten übertragen, frühere ID vermerkt, lokaler Eintrag ausgeblendet) – keine Dublette, nichts
  gelöscht.

## Gemeinsame Felder nach DEC-020 (#155)

Im EduKSX-Glossary enthält ein Begriff nur gemeinsame Fachdaten (DEC-020); `own_abbreviation` und `own_name` gibt
es in der API nicht mehr.

- **Anzeige:** `Glossary::shortName` und `displayName` zeigen weiterhin die eigene Form vor der offiziellen. Die
  eigene Form ist lokale Konfiguration des Intranets und kommt nie aus dem Glossary; der Sync überschreibt sie nicht.
- **Abbildung der API:** `abbreviation` → Abkürzung offiziell, `name` → Bezeichnung offiziell, `official_aliases` →
  Aliase offiziell, `alternative_aliases` → `aliase_alternativ` (neu), `short_description`, `description`, `url`,
  `categories`/`sources` (Schlüssel), `status`, `is_active`, `valid_from`/`valid_until`. `aliases`, `display_label`
  und `is_full_term` ergeben sich daraus.
- **Mehrdeutigkeit:** Für übernommene Begriffe zählt das Intranet wie `App\Glossary\GlossaryDisplay`: je
  normalisierter offizieller Abkürzung (NFC, Leerraum zusammengefasst, klein) die verschiedenen normalisierten
  offiziellen Bezeichnungen der aktiven übernommenen Begriffe; Kategorien und lokale eigene Formen zählen nicht.
  `is_ambiguous`/`meaning_count` fehlen im Change-Feed und werden darum immer lokal berechnet. Für lokale Einträge
  gilt die bisherige Regel (offizielle und eigene Abkürzungen).
- **Alternative Schreibweisen** erscheinen in der Einzelansicht, werden in der Suche der Tabelle (zusammen mit
  Aliasen und Suchbegriffen, auch bei ausgeblendeten Spalten) und beim Erkennen von Begriffen im Freitext
  (Tooltip) berücksichtigt.
- **Excel Format Version 2 des Glossary – Entscheid:** Der Abgleich läuft nur noch über die API. Eine Datei im
  Format 2 lässt sich im Intranet trotzdem importieren: fehlende Spalten («Abkürzung eigene», «Alias eigene»,
  «Bezeichnung eigene», «Intern genutzt in») lassen die lokalen Werte unverändert, die Spalte «Alternative
  Schreibweisen» wird ignoriert (kommt über den Sync), und gemeinsame Felder übernommener Begriffe sind gesperrt
  (Fehler in der Vorschau, wenn sie abweichen). Der Excel-Export des Intranets bleibt im bisherigen Format.

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

## Anbindung an EduKSX-Glossary (07.11.2026)

Führendes System für den gemeinsamen Bestand ist künftig EduKSX-Glossary (glossary.eduksx.ch). Das Intranet
übernimmt die veröffentlichten Begriffe über die API v1; eigene Formen und «Intern genutzt in» bleiben lokal.
Aufgaben und Reihenfolge: [UEBERGABE-EDUKSX-GLOSSARY.md](UEBERGABE-EDUKSX-GLOSSARY.md), Meilenstein
«EduKSX-Glossary – Anbindung» (#155, #156, #157).
