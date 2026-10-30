# Changelog – SekBülach Intranet

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Solange die Version mit 0 beginnt, ist das
Intranet ein Prototyp (nur erfundene Testdaten). Die Versionsnummer steht nur in `VERSION`; neue Einträge kommen
laufend unter **Unveröffentlicht** und werden beim Erhöhen der Version zur neuen Version.

## [Unveröffentlicht]

## [0.13.0] – 2026-10-30

### Neu

- Fachstelle ICT → Geräteausgabe: Zugangsdaten-Generator (Passwörter und PIN für Schüler-Logins, CSV-Export und
  -Import, Zugangskärtchen, PDF je Schüler:in mit Klassenübersicht, PowerShell-Skript für Microsoft 365). Läuft nur
  im Browser, speichert nichts im Intranet

### Geändert

- Alte Links `/module/<modul>` leiten auf umgesetzte Seiten weiter

## [0.12.1] – 2026-10-30

### Geändert

- Version, Build und Prototyp-Hinweis in einem Feld unten in der Seitenleiste, über der Sprachwahl
- Wissen & Ablage: Lehrplan 21 (Kanton Zürich) eingebunden

## [0.12.0] – 2026-10-30

### Neu

- Aktualisierung im Hintergrund: Das Intranet erkennt ein neues Deployment, meldet es und lädt die Seite neu
  (nicht während einer Eingabe); danach zeigt ein Fenster, was neu ist
- Menü «Verwaltung»: Workflows (SharePoint), Tickets und Elternportal (Jira Service Management)
- Fachstelle ICT: Webseiten-Snapshot, Rechnung, Telefonie Ansagetexte, LMVZ Shop (Prototyp), Jira Service Desk,
  Confluence
- Einbindung fremder Seiten und eigener Anwendungen im Rahmen oder in neuem Tab, mit Prüfung, ob der Anbieter das
  Einbetten erlaubt

### Geändert

- CI legt Formatkorrekturen im Branch `ci/format-fix` ab

## [0.11.0] – 2026-10-30

### Neu

- Versionierung: Version und Build in der Seitenleiste (über «Prototyp»), Seite «Versionen» mit diesem Changelog
- Sprachen: Deutsch (Standard), Englisch und Zürichdeutsch; Sprachwahl unter Einstellungen → Darstellung und in der
  Seitenleiste; Konzept unter Prototyp → Sprachkonzept
- MCP-Schnittstelle (Konzept) für Claude, ChatGPT und Atlassian Rovo unter Prototyp

## [0.10.0] – 2026-10-30

### Neu

- Seitenleiste: Logo über die ganze Breite, Hauptmenüs als farbige Fläche mit Symbol, Splitter für die Breite
- Menüs «Bestellen» und «Fachstelle ICT» (Platzhalter)
- Schrift umstellbar (Open Sans, Atkinson Hyperlegible, Inter, Source Serif 4, Systemschrift)
- Farbkonzept mit Menüfarben; Datenstrukturen ein- und ausklappbar, Prüfung auf vollständiges Schema

## [0.9.0] – 2026-10-30

### Neu

- REST-API v1 mit OpenAPI-Beschreibung, API-Tokens und Tester (Prototyp → API)
- Personen-Tabelle wie Excel: Zellen, Bereiche, Kopieren/Einfügen, Rückgängig, Filter nach Datentyp, Gruppieren,
  Fixieren, Export, gespeicherte Ansichten
- Änderungsverlauf mit Wiederherstellen

## [0.8.0] – 2026-10-30

### Neu

- Personen: alle Felder der Escola-Adressliste (ohne besonders schützenswerte), gruppierte Detailansicht,
  Sammeländerung und -löschung
- Plugin Escola: Import der Adressliste (Excel/CSV)

## [0.7.0] – 2026-10-30

### Neu

- Navigation nach Bereichen, ein- und ausklappbar; geplante Module als Platzhalter
- Plugin-System: Microsoft 365/Entra ID, Jamf, Inventory360, IDA

## [0.6.0] – 2026-10-29

### Neu

- Farbkonzept nach dem Auftritt von sekbuelach.ch
- Automatisches Deployment auf intranet.sekbuelach.ch mit gemeinsamem Passwort

## [0.1.0] – 2026-10-29

### Neu

- Prototyp auf dem IDA-Stack (Laravel, React, Inertia) mit Dateispeicher statt Datenbank: Start, Mitteilungen,
  Termine, Dokumente & Links, Personen, Datenstrukturen
