# Changelog – SekBülach Intranet

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Solange die Version mit 0 beginnt, ist das
Intranet ein Prototyp (nur erfundene Testdaten). Die Versionsnummer steht nur in `VERSION`; neue Einträge kommen
laufend unter **Unveröffentlicht** und werden beim Erhöhen der Version zur neuen Version.

## [Unveröffentlicht]

## [0.23.0] – 2026-10-31

### Neu

- Menü «Einstellungen» oberhalb von Prototyp (Administration): Einstellungen der einzelnen Menüpunkte.
  Fachanwendungen und weitere Einbindungen: Adresse (https) und Öffnen – im Intranet (iFrame), iFrame mit Knopf
  «Neuer Tab», eigenes Fenster oder über die Browser-Erweiterung; «Vorgabe wiederherstellen», Änderungsverlauf
- Browser-Erweiterung «SBI-Connector» (Prototyp, Edge/Chrome, `browser-extension/`): Fachanwendungen im eigenen
  Tab gruppiert mit dem Intranet; Aufzeichnung für den API-Recorder ohne Lesezeichen und Konsole, auch auf Seiten,
  die Lesezeichen blockieren, Aufnahme läuft nach dem Neuladen weiter; Download als ZIP unter Einstellungen
- Konzept Browser-Connector (docs/KONZEPT-BROWSER-CONNECTOR.md): Wege A/B/C, Stufe 1 Erweiterung, Stufe 2 Dienst
  auf dem Server
- Sammlung `module_settings` (Datenstrukturen)

### Geändert

- Bestätigte Einbettung (Escola) gilt nur für die vorgegebene Adresse; eine geänderte Adresse wird wieder geprüft

## [0.22.2] – 2026-10-31

### Geändert

- Fachanwendungen sind ein eigenes Hauptmenü an zweiter Stelle (nach Kommunikation)

### Behoben

- Nach dem Öffnen einer Fachanwendung liess sich das eingeklappte Seitenmenü mit dem Symbol nicht mehr öffnen
  (es klappte sofort wieder zu); es wird jetzt nur einmal je Anwendung eingeklappt

## [0.22.1] – 2026-10-31

### Geändert

- Escola öffnet immer im Intranet-Rahmen (Einbetten von Escola bestätigt, `embed.allowed`), ohne automatische
  Prüfung; Rahmen erlaubt zusätzlich Dialoge (Bestätigungen in Fachanwendungen)

## [0.22.0] – 2026-10-31

### Neu

- Kommunikation → Fachanwendungen (Untermenü): CMI, Escola (https://www.sekbuelach.ch/escola/login), Apple School
  Manager, Jamf Pro. Escola im Rahmen, sofern Escola das Einbetten erlaubt (gleiche Domain, Anmeldung bleibt
  erhalten), sonst neuer Tab; Apple School Manager immer neuer Tab; CMI und Jamf Pro mit Adresse aus der `.env`
- Beim Öffnen einer Fachanwendung klappt das Seitenmenü immer ein (auch beim direkten Aufruf der Adresse)
- Menüs können Untermenüs haben (`items` innerhalb eines Menüpunkts, `collapse_sidebar`)
- Plugins: «Verbindung testen» auf jeder Plugin-Karte und «Alle Verbindungen testen» (eingeschaltete und
  eingerichtete Plugins nacheinander, übrige mit Grund übersprungen); auf der Plugin-Seite heisst der Knopf ebenso

## [0.21.0] – 2026-10-31

### Neu

- LMVZ-Plugin verbindet LMVZ Digital (digital.lmvz.ch): 19 Endpunkte aus der Aufzeichnung (Status partial),
  Aktion und Kachel «Lizenzübersicht» (gekauft, zugewiesen, frei, abgelaufen je Lehrmittel – ohne Lizenzschlüssel
  und Personendaten), Verbindungstest mit Schulname; Benutzer, Gruppen, Beitrittscodes nur im API-Tester
- Plugins mit mehreren Adressen eines Herstellers (`service.hosts`, `endpoints[].host`); Anmeldung nur an die
  freigegebenen Adressen (`service.auth.hosts`)
- `endpoints[].effect`: Listen per POST gelten als lesend, GET-Aufrufe mit Wirkung als schreibend
- Generator: `--host=<schlüssel>` ordnet neue Endpunkte einer weiteren Adresse zu; IDs ohne `rest/`

### Behoben

- API-Recorder: kurze Beispielwerte (z. B. Nachnamen mit drei Buchstaben) wurden nicht maskiert; Felder mit
  Personenbezug werden jetzt immer gekürzt

## [0.20.0] – 2026-10-31

### Neu

- Plugin «LMVZ – Lehrmittelverlag Zürich (inoffiziell)»: Artikel im LMVZ-Webshop nachschlagen (Titel,
  Artikelnummer, ISBN, Listenpreis) aus öffentlichen Artikelseiten; nur Adressen auf shop.lmvz.ch, ohne Anmeldung;
  Warenkorb/Bestellung und LMVZ Digital als «geplant» (zuerst mit dem API-Recorder aufzeichnen)
- Manifest-Feld `unofficial`: Plugins ohne offizielle Schnittstelle zeigen das Kennzeichen «inoffiziell» und
  einen Warnhinweis

## [0.19.0] – 2026-10-31

### Neu

- API-Ansicht mit Scalar (Open Source, MIT): eigene REST-API (Prototyp → API → «Referenz»), Endpunkte jedes
  Plugins («API-Ansicht») und aufgezeichnete APIs («Ansicht (Scalar)»); ohne Telemetrie, fremde Schriften und Proxy
- Generator `php artisan intranet:plugin-from-openapi`: ergänzt ein Plugin um fehlende Endpunkte aus einer
  OpenAPI-Datei oder Aufzeichnung (`--plugin`) oder legt ein neues Plugin an (`--neu`), mit `--dry-run`;
  neue Endpunkte immer `partial`, Anmeldung aus `securitySchemes`, nicht in Produktion
- API-Recorder zeigt den passenden Generator-Befehl zum Kopieren

### Geändert

- Prüfung der Plugin-Manifeste: eindeutige Endpunkt-IDs, gültige Methoden und Status (full, partial, planned)

## [0.18.0] – 2026-10-31

### Neu

- API-Recorder mit Verwaltung: Liste aller aufgezeichneten APIs (Name, URL, Anzahl Endpunkte, Plugin, zuletzt
  aufgezeichnet); «Neue API aufzeichnen» mit Name, URL und Aufzeichnung; gespeicherte API wieder öffnen – die
  Endpunkte werden eingelesen und weitere Aufzeichnungen ergänzen sie (fehlende Befehle nachträglich aufzeichnen);
  automatisches Speichern
- Zuweisung einer API zu einem Plugin: Abgleich je Endpunkt (vorhanden/fehlt), Anzeige auf der Plugin-Seite,
  «Plugin-Entwurf» mit den fehlenden Endpunkten im Format von plugin.json (Status partial)
- Konzept «Aufgezeichnete API → Plugin» (docs/KONZEPT-API-ZU-PLUGIN.md) mit geplantem Generator

## [0.17.1] – 2026-10-31

### Behoben

- API-Recorder: Website öffnet als normaler Tab statt als Pop-up, damit die Lesezeichenleiste sichtbar ist;
  zusätzlich «Code kopieren» zum Einfügen in die Browser-Konsole (ohne Lesezeichen)

## [0.17.0] – 2026-10-31

### Neu

- Prototyp → API-Recorder: Website im eigenen Fenster bedienen, Anfragen und Antworten mitschneiden (Lesezeichen
  «SBI-Recorder» oder HAR-Import), neue Endpunkte auf Rückfrage dokumentieren, Schemas automatisch ableiten,
  Dokumentation speichern und als OpenAPI-Datei (Swagger, JSON/YAML) herunterladen

## [0.16.0] – 2026-10-31

### Neu

- Personenart je Person (Schüler:in, Eltern, Lehrperson, Schulleitung, Mitarbeiter:in, Schulbehörde, Externe;
  erweiterbar), Felder Klasse und iPad-/Geräte-Nr.
- Personen & Organisation → Schüler:innen: dieselbe Tabelle, gefiltert auf Schüler:innen
- Datenherkunft & Synchronisation je Person: Quelle und Zielsysteme mit ID/PK, letztem Abgleich und Hinweis
  «Geändert – Abgleich nötig»; Importe finden Personen über die ID des Quellsystems (keine Duplikate)
- Geräteausgabe: Schüler:innen direkt aus dem Intranet laden, nur die gewählten Klassen
- Erfundene Demo-Schüler:innen und Eltern

### Geändert

- Schüler:innen und Eltern sehen nur Rollen mit Recht «Schüler:innen und Eltern sehen»; sie können sich nicht
  anmelden (Rolle «Kein Zugang»)
- E-Mail ist kein Pflichtfeld mehr (bleibt eindeutig)

## [0.15.0] – 2026-10-30

### Neu

- PWA: Intranet als App installierbar (Computer, Android, iPhone/iPad), Knopf «App installieren» in der
  Seitenleiste und unter Einstellungen → Darstellung, Anleitung für iPhone/iPad, Offline-Seite. Der Service Worker
  speichert keine Seiten oder Daten, nur Programmdateien

## [0.14.2] – 2026-10-30

### Neu

- Mobil: Menü mit Wischen von links nach rechts einblenden, von rechts nach links ausblenden (nicht in
  waagrecht scrollenden Tabellen und Eingabefeldern)

### Behoben

- Seiten sind als Deutsch (Schweiz) ausgewiesen (`lang="de-CH"`, Laravel-Sprache `de`); Browser bieten keine
  Übersetzung aus dem Englischen mehr an

## [0.14.1] – 2026-10-30

### Geändert

- Mobil: Das eingeblendete Menü schliesst sich nach der Wahl eines Menüpunkts (nicht beim Auf- und Zuklappen
  eines Hauptmenüs); beim Öffnen sind alle Hauptmenüs eingeklappt

## [0.14.0] – 2026-10-30

### Neu

- Rollenkonzept: Administration, Schulleitung, Schulverwaltung, Fachstelle ICT, Redaktion, Lehrperson,
  Mitarbeitende; Rechte je Rolle in der Konfiguration, Seite Prototyp → Rollenkonzept
- Geräteausgabe: Schüler:innen aus einer Klassenliste (Excel oder CSV) importieren, mit Auswahl von Jahrgang
  (Standard 1. Sek) und Klasse; fehlende Benutzernamen werden gebildet. Die Datei wird nur im Browser gelesen

### Geändert

- Fachstelle ICT für die Rolle Fachstelle ICT (bisher nur Administration); Workflows nur für Verwaltung und
  Schulleitung; Unterricht und Bestellen nur für Rollen mit diesen Aufgaben
- Demo-Personen haben neue Rollen (Schulleitung, Verwaltung, Lehrperson, Fachstelle ICT)

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
