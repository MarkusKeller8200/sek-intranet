# Konzept: Module, Plugins und eigenständige Projekte

Entscheid des Projektverantwortlichen vom 01.11.2026. Umsetzung erst nach Freigabe der einzelnen Issues
(`_1 bereit`).

## Entscheid

1. **Ein Intranet, ein Repository, ein Deployment.** Installation, Updates, Überwachung, Backup und Patches bleiben
   für ein einziges System. Einzeln ausgerollte Module (eigene Container und Repositories) gibt es nicht auf Vorrat.
2. **Module sind eine Ordnerstruktur im Intranet** und trennen die Themen fachlich:
   `modules/<Name>/` mit `module.json`, eigenen Rechten, eigenem Menü, eigenen Tests und eigenem CHANGELOG.
3. **Jedes Modul hat seine eigene Datenhaltung.**
   - Mit Datenbank: ein PostgreSQL-Schema und ein DB-Benutzer pro Modul. Der Benutzer darf nur im eigenen Schema
     schreiben («eigener DB-Writer»).
   - Jedes Modul hat eigene Migrationen und eigene Repositories (Contracts mit JSON- und Database-Umsetzung).
     Die Umstellung auf die Datenbank erfolgt pro Modul.
4. **Keine Zugriffe quer zwischen Modulen.** Erlaubt sind nur Core-Schnittstellen (z. B. Personen), Ereignisse
   und die REST-API. Fremdschlüssel nur auf Core-Stammdaten (UUID). Eine Architekturprüfung in der CI soll das
   sicherstellen.
5. **Plugins bleiben Adapter zu fremden Systemen** (Entra, Jamf, Escola, ePost …). Module nutzen sie über die
   Plugin-Schnittstelle.
6. **Eigenständige Projekte nur mit konkretem Grund**, zum Beispiel öffentlicher Zugang ohne Login, eine
   andere verantwortliche Stelle oder die Weitergabe an andere Schulen. Sie werden über eine API als Plugin
   und/oder als Einbindung (`embed`) angeschlossen.

## Abgrenzung

| | Core | Modul | Plugin | Eigenständiges Projekt |
|---|---|---|---|---|
| Ort | Intranet | `modules/<Name>/` im Intranet | `plugins/<Ordner>/` | eigenes Repository, eigenes Deployment |
| Daten | Stammdaten (Personen, Organisation, Rechte) | eigenes Schema | keine eigenen Fachdaten (nur Einstellungen, Läufe) | eigene Datenbank |
| Login | OIDC (später) | über den Core | – | eigenes, falls nötig |
| Beispiele | Anmeldung, Personen, Rollen, Navigation, Benachrichtigungen, GUI-Bausteine, Plugin-Laufzeit, API | Kommunikation, Wissen, Zeiterfassung, Beschaffung (HW/SW bestellen), ICT/Lizenzen verteilen, Unterricht, Synchronisation | Entra, Jamf, Escola, ePost, Inventory360, LMVZ | Systematische Rechtssammlung |

## Systematische Rechtssammlung (eigenes Projekt)

Begründung: Die Inhalte sind öffentlich, es gibt kein Login für Endnutzer:innen und kaum Stammdaten. Sie hat also
ein anderes Publikum und einen anderen Schutzbedarf als das Intranet.

- **Eigenes Repository** (z. B. `sek-rechtssammlung`). Öffentliche Website mit Suche, Gliederung nach
  Systematik, Erlass mit Fassungen («gültig ab/bis»), Änderungshistorie und Druck-/PDF-Ansicht.
- **Redaktion** mit Anmeldung nur für Bearbeitende (OIDC über Entra ID); das ist das einzige Login.
- **Öffentliche, lesende REST-API** mit OpenAPI-Beschreibung (z. B. `/api/v1/erlasse`, `/erlasse/{id}`,
  `/systematik`, `/suche?q=`), ohne Token, mit Rate-Limit.
- **Anbindung ans Intranet:**
  - Plugin `Rechtssammlung` mit den Aktionen «Erlass suchen» und «Erlass anzeigen», einer Kachel «Neu/geändert»
    und gegebenenfalls einer Benachrichtigung bei neuen Fassungen.
  - Menüpunkt unter «Wissen & Ablage» als Einbindung bzw. Link.
- **Stack** wie IDA und Intranet (Laravel, React/Inertia, GUI-Bausteine und Farbkonzept des Intranets), damit das
  Aussehen einheitlich ist. Der GitHub-Ablauf (Vorlagen, Labels, Releases) wird aus diesem Repository übernommen.
- **Hosting:** eigenes kleines Deployment, z. B. `recht.sekbuelach.ch`. Die Daten sind öffentlich, trotzdem gilt
  Infomaniak als Ziel.
- **Offen:**
  - Datenquelle und Import bestehender Erlasse (Word/PDF?)
  - Verbindliche Fassung (Publikationsorgan)
  - Benötigte Sprachen
  - Ob das Projekt auch Erlasse anderer Stellen (Gemeinde, Kanton) verlinkt

## Planung (Issues und Meilensteine)

**Meilenstein «0.33.0 – Modulgerüst & Pilot Zeiterfassung»**

- #7 Modulgerüst mit den Teilen:
  - #10 Manifest und Laden der Module
  - #11 Seiten und Übersetzungen
  - #12 Datenhaltung pro Modul
  - #13 Architekturprüfung
  - #14 Generator und Doku
- #8 Pilotmodul Zeiterfassung (Umfang und Felder vorher abstimmen)

**Meilenstein «Rechtssammlung – Projektstart»**

- #9 Rechtssammlung als eigenes Projekt mit den Teilen:
  - #16 Offene Fragen klären
  - #17 Repository und Grundgerüst
  - #18 Website und Redaktion
  - #19 Öffentliche API
  - #20 Plugin und Menüpunkt im Intranet
- #18 und #19 werden im neuen Repository umgesetzt und dort weitergeführt.

**Später (Sammlung Verbesserungen)**

- #15 Bestehende Bereiche schrittweise in Module verschieben

Alle Issues tragen `triage` (Status-Labels siehe `docs/KONZEPT-VERSIONIERUNG.md`). Umgesetzt wird erst nach Freigabe mit `_1 bereit`; das neue Repository wird erst nach
ausdrücklicher Bestätigung angelegt.
