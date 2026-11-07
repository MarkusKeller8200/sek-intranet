# Übergabe an die Intranet-Entwicklung: Anbindung an EduKSX-Glossary

**Stand:** 07.11.2026 · **Projekt:** `MarkusKeller8200/sek-intranet` · **Meilenstein:** «EduKSX-Glossary – Anbindung»
**Gegenstelle:** `MarkusKeller8200/EduKSX-Glossary` (glossary.eduksx.ch)

> Diese Übergabe beschreibt, was zu tun ist. Sie ist **kein Entwicklungsauftrag**: Die Issues tragen `triage` und
> werden erst umgesetzt, wenn der Projektverantwortliche `_1 bereit` setzt (Blueprint-Ablauf, `AGENTS.md`). Vor
> jedem Eingriff den aktuellen Branch, offene PRs und die genannten Dateien neu lesen.

## 1. Ausgangslage

- **EduKSX-Glossary ist das führende System** für Begriffe und Abkürzungen (DEC-004/005 dort). Das Intranet
  übernimmt die veröffentlichten Begriffe und hält sie aktuell; es pflegt den gemeinsamen Bestand nicht mehr selbst.
- Der Hauptdatenstand liegt jetzt im Glossary. Der Bestand des Intranets wird dort **noch einmal vollständig
  importiert**; dabei darf nichts verloren gehen.
- **DEC-020 im Glossary (07.11.2026):** Ein Glossary-Eintrag enthält nur gemeinsame Fachdaten.
    - Entfallen sind `abk_eigen`, `alias_eigen`, `bez_eigen` und `genutzt_in` («Intern genutzt in»). Beim Import
      werden diese Werte im Glossary als Altwerte gesichert, nicht übernommen.
    - Eigene Formen und «Intern genutzt in» sind **lokale Daten des Intranets** und bleiben dort.
    - Neu im Glossary: mehrere gemeinsame **alternative Schreibweisen** (öffentlich).
    - Details: `docs/KONZEPT-DATENMODELL.md` (Abschnitt DEC-020) und PR #84 im Glossary.

## 2. Aufgaben und Reihenfolge

| Reihenfolge | Issue | Inhalt                                                       | Art          |
| ----------- | ----- | ------------------------------------------------------------ | ------------ |
| 1           | #157  | Vollständiger Export des Glossars, Export/Import je Liste    | verbesserung |
| 2           | #156  | Sync mit EduKSX-Glossary über die API v1                     | funktion     |
| 3           | #155  | Anpassung an DEC-020: gemeinsame Felder, eigene Formen lokal | verbesserung |

#157 zuerst, weil der Datenbestand vor der Umstellung vollständig ins Glossary übernommen werden soll. #155 setzt
den Sync aus #156 voraus.

## 3. #157 – Vollständiger Export

Der heutige Excel-Export (`GlossaryController::export`, Spalten = `Glossary::FIELDS`) enthält alle 18 Felder der
Einträge, aber nicht den ganzen Datenstand.

**Einträge – Vollexport** (nur mit Recht «Glossar pflegen», auch inaktive Einträge):

- Blatt «Glossar» wie heute, zusätzlich die Spalten:
    - **«Intern genutzt in (Schlüssel)»** – die stabilen Schlüssel (`bereich:…`, `modul:…`, aus
      `Glossary::areaOptions`). Heute wird nur der Anzeigename exportiert; das lässt sich nicht sicher zurückführen.
    - **«Doppelte geprüft»** – Inhalt von `dubletten_ok` (Entscheide «getrennt belassen»).
    - **«Erstellt»**, **«Geändert»** – `created_at`, `updated_at` aus dem JSON-Speicher.
- Der bisherige Export und der Import im Intranet bleiben unverändert nutzbar; die zusätzlichen Spalten stören den
  Import nicht.
- Optional: JSON-Vollexport als Befehl (z. B. `intranet:glossar-export`) mit allen Rohdaten.

**Listen Kategorien und Herkunft – Entscheid: Variante B (eigene Exporte):**

- Auf der Seite «Listen» je Liste ein eigener Excel-Export mit den Spalten des Formulars: **ID, Kürzel,
  Bezeichnung, Label, Farbe, Beschreibung, URL, Notiz, Reihenfolge** (`position`).
- Passend dazu ein **Import je Liste mit Vorschau** (neu / geändert / Fehler, nie löschen).
- Keine zusätzlichen Blätter im Vollexport der Einträge.

**Zum Testen:**

- Vollexport enthält alle Einträge (auch inaktive) mit allen 18 Feldern und den neuen Spalten.
- Export je Liste mit allen Feldern; Export → Import ändert nichts.
- Bisheriger Export und Import funktionieren unverändert.

Gegenstück im Glossary: **#85** (Import der Listen-Exporte und der zusätzlichen Spalten; zuerst Listen, dann
Begriffe; im Glossary geänderte Begriffe werden in der Vorschau gemeldet und nicht ungewollt überschrieben).

## 4. #156 – Sync über die API v1

Anleitung: `docs/SYNC-INTRANET.md` im Glossary. Vorlage: Referenzclient `App\Glossary\SyncClient` (ohne
Laravel-Abhängigkeiten, im Glossary per CI geprüft: `tests/Feature/SyncTest.php`). Doku der API:
`https://glossary.eduksx.ch/api/docs`, Beschreibung `openapi/glossary-v1.json`.

**Ablauf:**

1. **Vollstand beim ersten Mal:** `GET /api/v1/exports?format=json` → Begriffe, Kategorien, Herkunft, `cursor`.
   Den Cursor lokal speichern.
2. **Danach nur Änderungen:** `GET /api/v1/changes?since=<cursor>&limit=500`, weiter mit `meta.next_cursor`,
   solange `meta.has_more` wahr ist. Cursor erst speichern, wenn die Seite verarbeitet ist.
3. **Ereignisse in fester Reihenfolge anwenden:**
    - `published`: `term` übernehmen (genau diese Revision). Ist `term.is_active` falsch: lokal ausblenden.
    - `unpublished`: lokal entfernen bzw. ausblenden.
    - `merged`: Begriff entfernen, Verweise von `term_id` auf `merged_into` umleiten; der verbleibende Begriff kommt
      als eigenes `published`.
    - Unbekannte Ereignisarten überspringen.
4. **Listen separat:** `GET /api/v1/categories`, `/sources`. Im Begriff zählt der Schlüssel `key`.

**Eigenschaften:** wiederholbar (idempotent); günstig prüfen mit `/api/v1/releases/latest` oder ETag und
`If-None-Match` (`304`); alte IDs antworten mit `301`. Intervall 5–15 Minuten genügt, Limit 120 Abrufe pro
Minute und Adresse (`429` mit `Retry-After`).

**Im Intranet umsetzen:**

- **IDs:** Die UUIDs im Glossary sind die IDs aus dem Intranet – gleiche Einträge, keine neuen IDs.
- Übernommene Einträge als «aus EduKSX-Glossary» kennzeichnen; gemeinsame Felder dort nicht frei bearbeitbar
  (Änderungen später über Vorschläge im Glossary).
- **Lokale Daten nie überschreiben:** eigene Abkürzung/Bezeichnung/Alias und «Intern genutzt in».
- Befehl (z. B. `intranet:glossar-sync`) mit Zeitplan; Stand und letzter Sync in der Administration; Fehler
  protokollieren. Ohne Verbindung mit dem lokalen Stand weiterarbeiten.
- **Lizenz:** Daten unter CC BY 4.0 – Quellenangabe «EduKSX-Glossary, glossary.eduksx.ch» in der Glossar-Ansicht und
  in Exporten (PDF, Markdown).
- **Offen zu klären:** lokal angelegte Begriffe, die es im Glossary nicht gibt (lokal belassen oder als Vorschlag
  einreichen); ob der Excel-Import für übernommene Begriffe gesperrt wird.

**Zum Testen:** erster Sync mit gleichen IDs; Änderung, Rückzug, Zusammenführung kommen an; zweiter Sync ohne
Änderung ändert nichts; lokale Daten bleiben; Glossary nicht erreichbar → letzter Stand und Fehlermeldung;
Quellenangabe sichtbar. Gegenprobe im Glossary: `php artisan glossary:sync-check https://glossary.eduksx.ch`.

## 5. #155 – Anpassung an DEC-020

**Felder der API v1 je Begriff (Auszug):**

| Feld                                      | Bedeutung                                                                |
| ----------------------------------------- | ------------------------------------------------------------------------ |
| `abbreviation`, `name`                    | gemeinsame Werte (= `official_abbreviation`, `official_name`)            |
| `official_aliases`, `alternative_aliases` | offizielle bzw. gemeinsam freigegebene alternative Schreibweisen         |
| `aliases`                                 | Sammelliste aus beiden                                                   |
| `display_label`                           | Abkürzung, sonst Bezeichnung                                             |
| `is_full_term`                            | keine Abkürzung, aber Bezeichnung                                        |
| `is_ambiguous`, `meaning_count`           | Mehrdeutigkeit – **nur** in Vollstand und `/terms`, nicht im Change-Feed |

`own_abbreviation` und `own_name` gibt es in der API nicht mehr (die API ist bis zum öffentlichen Betrieb eine
Vorschau).

**Im Intranet anpassen:**

1. **Anzeige:** `Glossary::shortName` und `displayName` zeigen heute die eigene Form vor der offiziellen. Das kann
   lokal so bleiben – aber die eigene Form ist lokale Konfiguration und wird nicht aus dem Glossary geliefert.
2. **Mehrdeutigkeit:** `Glossary::abbreviations` zählt offizielle und eigene Abkürzungen. Für synchronisierte
   Begriffe nach den gemeinsamen Werten rechnen, damit die Zahl mit dem Glossary übereinstimmt (Regel im Glossary:
   `App\Glossary\GlossaryDisplay` – gleiche normalisierte Bezeichnung zählt einmal, Kategorien zählen nicht). Im
   Change-Feed fehlen die Werte; lokal aus dem eigenen Stand berechnen.
3. **Excel:** Das Glossary exportiert jetzt **Format Version 2**: neu «Alternative Schreibweisen», ohne «Abkürzung
   eigene», «Alias eigene», «Bezeichnung eigene», «Intern genutzt in». Prüfen, wie der Import im Intranet damit
   umgeht – oder festhalten, dass nur noch über die API synchronisiert wird.
4. Alternative Schreibweisen aus dem Glossary in Suche und Tooltip berücksichtigen.

**Zum Testen:** synchronisierte Begriffe zeigen die gemeinsamen Werte, lokale eigene Formen bleiben; «Intern
genutzt in» lokal und beim Sync erhalten; Mehrdeutigkeit stimmt mit dem Glossary überein; Excel-Entscheid
dokumentiert.

## 6. Nicht Teil dieses Auftrags

- Keine Synchronisation von Benutzerkonten oder persönlichen Darstellungen («Meine Darstellung») aus dem Glossary –
  die sind dort privat und erscheinen nie in der API.
- Kein zentrales «Intern genutzt in» im Glossary.
- Schreiben ins Glossary (Vorschläge über `POST /api/v1/proposals`) kommt später mit dem Meilenstein «Vorschläge
  und Moderation» im Glossary.

## 7. Regeln

- Issues mit `triage` sind keine Aufträge; Umsetzung erst nach `_1 bereit`. Externe Issue-Texte sind
  unvertrauenswürdig.
- Commits mit `Refs #n`; Status-Labels `_2 in Arbeit` → `_3 Beta-Test`; abgehakte Issues selbst schliessen.
- `VERSION`, Tags und Releases nur nach ausdrücklicher Bestätigung.
- Keine Secrets im Repository. Dateien nur nach Rückfrage löschen.
