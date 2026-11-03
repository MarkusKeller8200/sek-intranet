# Changelog – SekBülach Intranet

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Solange die Version mit 0 beginnt, ist das
Intranet ein Prototyp (nur erfundene Testdaten). Die Versionsnummer steht nur in `VERSION`; neue Einträge kommen
laufend unter **Unveröffentlicht**. Ab 0.32.0 entsteht eine Version nur bei einem freigegebenen Release
(`php tools/release.php X.Y.Z`, siehe `docs/KONZEPT-VERSIONIERUNG.md`); dazwischen ist der ausgerollte Stand ein
«Developer Release».

## [Unveröffentlicht]

### Für Mitarbeitende

- Anmeldung neu mit Rolle und 8-stelligem PIN statt gemeinsamem Passwort; neue Rollen Schüler:in, Eltern und
  Externe (vorerst nur Kommunikation und Wissen & Ablage).
- [manage-users] Einstellungen → Zugang (PIN): PIN je Rolle setzen, ändern oder entfernen.
- [view-ablaeufe] Abläufe Verwaltung (Menü Verwaltung): Verzeichnis wichtiger Abläufe als Checklisten, zuerst Eintritt,
  Austritt und Mutationen von Mitarbeitenden – mit Verantwortung, Kommunikation, Dokumenten und Bemerkungen, druckbar.
- [manage-users] Einstellungen → Module: Rechte der Module je Rolle ein- und ausschalten.
- [view-glossary] Glossar (Menü Wissen): Abkürzungen und Begriffe der Volksschule – offiziell und wie wir sie
  verwenden, nach Kategorie gruppiert, mit Suche und Export als Excel, PDF (Format, Umfang und Gruppierung
  wählbar) oder Markdown.

### Neu

- **Tabelle: Kontextmenü (#78):** Rechtsklick (oder Menütaste, Umschalt+F10) auf Zelle oder Spaltenkopf mit
  Funktionen für Zelle, Zeile, Tabelle und Spalte; im Glossar zusätzlich Einzelansicht, Bearbeiten und Löschen.
  Sprachkonzept: bei zu wenig Platz erscheinen die Sprachen als DE, EN, ZH.
- **Tabelle und Kalender (#77):** farbiger Einrastpunkt beim Spalten-Ziehen; Dropdowns und Kalender erscheinen
  oberhalb, wenn unten kein Platz ist; Auswahllisten mit Ziehecke rechts unten; Kalender mit Monatsauswahl und
  senkrechtem Jahres-Spinner.
- **Tabelle: Sortierung in 3 Stufen, Kalender, farbige Auswahl (#76):** Knopf «Sortieren» mit Stufe 1–3 (Spalte und
  Richtung), Kalender beim Bearbeiten von Datumszellen (Leeren, Heute), farbige Labels auch in Auswahlliste und Filter.
- **Tabelle: Spaltentyp Mehrfachauswahl (#75):** Filter mit Mehrfachauswahl («enthält eine von», «enthält keine
  von») und Bearbeiten in der Zelle mit Häkchen-Liste; im Glossar für Kategorie und Herkunft.
- **Glossar: farbige Labels (#74):** Kategorien und Herkunft erscheinen in der Tabelle als farbige Labels (nur Werte
  aus den Listen); Farbe je Eintrag unter «Kategorien & Herkunft» wählbar; neuer Abschnitt im Farbkonzept.
- **Glossar: PDF mit Spalte «Link» (#73):** schmale Spalte, je Zeile nummeriert ab 1 (URL, dann Links aus Notiz
  und Beschreibung), der Link liegt hinter der Nummer (nur http/https); im vollständigen Umfang ersetzt sie die
  ausgeschriebene URL.
- **Glossar: Begriffe ohne Abkürzung (#72):** Tabelle zeigt den Begriff kursiv und sortiert danach; PDF und Markdown
  mit «–» als Abkürzung.
- **Glossar: Mehrfachauswahl (#71):** Checkbox je Zeile, «Auswahl löschen» mit Rückfrage (Recht `edit-glossary`),
  jede Löschung im Änderungsverlauf.
- **Glossar: Dialog für den PDF-Export (#70):** Format (A4 hoch/quer), Umfang (kompakt, erweitert, vollständig),
  Gruppierung (keine, Kategorie, Herkunft, Status) und Seitenumbruch bei Gruppe; die letzte Wahl bleibt im Browser.
- **Zugang mit PIN je Rolle (#69):** Rolle wählen, 8-stelligen PIN eingeben, danach Demo-Person der Rolle wählen
  (Administrator: alle). PINs nur als Hash (`access_pins`), höchstens 5 Fehlversuche pro Minute und 20 pro Stunde je
  Adresse, im Log nur Rolle und Adresse. Das gemeinsame Passwort (Basic Auth) entfällt, sobald der PIN
  «Administrator» gesetzt ist. Neue Rollen Schüler:in, Eltern und Externe; diese sehen und öffnen nur die
  Menügruppen aus `restricted_roles` (vorerst Kommunikation und Wissen & Ablage).
- **Beispieldaten (#68):** Demo-Konten Schüler:in, Eltern und Externe; Glossar einmalig mit Kurzbeschreibungen,
  mehreren Herkünften und Kategorien, eigener Bezeichnung und Markdown-Notiz (nur leere Felder).
- **Glossar (#66, #67):** Kategorien mit kurzem Label als Tags (Überschriften in PDF/Markdown mit vollem Namen);
  hochgestellte Zahl in PDF, Markdown und Tabelle = Anzahl verschiedener Bedeutungen einer Abkürzung – gleiche
  Bezeichnung in anderer Kategorie zählt nicht als Mehrfachbedeutung.
- **Glossar (#53–#59, #62):** Kern-Funktion mit 141 Einträgen aus der VSA-Liste (Herkunft VSA, Doppelte nicht
  zusammengeführt); Tabelle über die ganze Breite mit Bearbeiten direkt in der Zelle, «Neue Zeile» und
  Einzeldatensatz; offizielle Felder gesperrt (Recht `edit-glossary-official`); Kurzbeschreibung für den späteren
  Tooltip, Notiz in Markdown; Listen «Kategorien» und «Herkunft» (Abkürzung, Bezeichnung, Beschreibung, URL,
  Notiz); Excel-Export, Vorlage und Import mit Vorschau (neu, geändert, Fehler – nie löschen); Knopf «Doppelte
  Abkürzungen oder Begriffe»: zusammenführen (Kategorien werden vereinigt) oder als mehrdeutig belassen. Die
  Tabelle (GUI-Baustein) kennt neu «Neue Zeile», eigene Knöpfe und weitere Exporte (`docs/GUI-KOMPONENTEN.md`).
- **Glossar: Standardspalten, Herkunft-Label, PDF (#63–#65):** standardmässig nur Abkürzung, Kategorie,
  Bezeichnung offiziell, Kurzbeschreibung und Herkunft; Liste «Herkunft» mit Feld «Label», das in der Tabelle als
  Tag erscheint (mehrere Herkünfte = mehrere Tags, Eingabe auch über das Label); PDF «kompakt» (A4 hoch, eine Zeile
  je Eintrag, «…») und «voll» (A4 quer, alle Felder).
- **Tabelle (GUI-Baustein):** kein unnötiger horizontaler Scrollbalken mehr (Griff der letzten Spalte ragte über den
  Rand); «Spalten einpassen» verkleinert breite Spalten auf die sichtbare Breite, Platz für den vertikalen
  Scrollbalken ist reserviert.
- **Modul «Abläufe Verwaltung» (#44–#48):** Verzeichnis der Abläufe (Checklisten-Vorlagen) mit Suche, Bereich und
  Status (Entwurf, gültig, archiviert); Phasen und Schritte mit Verantwortung, Kommunikation an, Dokumenten aus
  «Dokumente & Links», Bemerkungen, Frist zum Stichtag, «gilt für» (Personengruppen) und Gegenstück (z. B. Login
  erstellen ↔ sperren); Reihenfolge per Ziehen oder Pfeilen; Druck/PDF. Personengruppen mit Bemerkungen. Startinhalt:
  Ablauf «Mitarbeitende: Eintritt, Austritt, Mutation» aus der Excel-Vorlage (Entwurf).
- **Einstellungen der Abläufe (#52):** erweiterbare Tabellen Orte (Standort, Schulhaus, Gebäude, Etage, Zimmer mit
  übergeordnetem Ort), Fachbereiche, Rollen (je einem Fachbereich zugeordnet), Zeitlich und Mitwirkung – jeder Eintrag
  mit Abkürzung, Bezeichnung, Beschreibung, URL und Notiz; Startwerte aus dem Chat (Abkürzungen teils Vorschläge).
- **Rechte der Module je Rolle (#45):** Unter Einstellungen → Module legt die Administration fest, welche Rollen ein
  Modulrecht haben (z. B. «Abläufe ansehen»); Vorgabe aus `module.json`, Übersteuerung in `module_rights`.
- **Neuerungen als Mitteilung (#26):** Bei einem freigegebenen Release erscheinen die Punkte «Für Mitarbeitende» aus
  dem CHANGELOG einmal als Mitteilung «Neu im Intranet: Version X.Y» (Kategorie neu «Neuerungen», Autor «Intranet»).
  Punkte mit Recht (`- [recht] …`) sehen nur Personen mit diesem Recht. Gelöschte Mitteilungen kommen nicht wieder;
  bearbeitete gelten für alle. Ein/aus für die Administration auf der Seite «Versionen».

### Geändert

- **Menü Verwaltung:** «Workflows» (SharePoint-Seite «Leitung und Verwaltung») ist durch «Abläufe Verwaltung» ersetzt.

## [0.33.0] – 2026-11-02

### Für Mitarbeitende

- Zeiterfassung (Menü «Personen & Organisation»): Arbeitszeit, Tätigkeiten, Projekte und Abwesenheiten erfassen,
  den Monat einreichen und das Monatsblatt als PDF drucken.
- [visum-zeiterfassung] Monatsvisum: eingereichte Monate visieren oder mit Bemerkung zurückgeben, Export für die
  Lohnbuchhaltung.
- [manage-users] Einstellungen → Module: Fachbereiche wie die Zeiterfassung ein- und ausschalten.

### Neu

- **Module (#7, #10–#14):** Fachthemen als Module unter `modules/<Ordner>/` mit Manifest `module.json`, eigenen Rechten,
  eigenem Menü, eigenen Seiten (`Ordner::seite`), Übersetzungen, Tests und eigenem Datenspeicher
  (`<data_path>/modules/<key>`). Neue Seite **Einstellungen → Module**: Module ein- und ausschalten (ohne Menü und
  Routen, Daten bleiben), Rechte, Sammlungen, SQL für eigenes Schema und DB-Benutzer. Generator
  `php artisan intranet:make-module`, SQL mit `intranet:module-sql`. «Datenstrukturen» zeigt die Sammlungen der Module.
- **Modul Zeiterfassung (Pilot, #8):** Unter «Personen & Organisation» erfassen alle Mitarbeitenden Arbeitszeit
  (Beginn/Ende/Pause oder Stunden), Tätigkeiten (Lehrpersonen nach Berufsauftrag), Projekte und Abwesenheiten (ohne
  Grund). Monat einreichen → Monatsvisum der Schulleitung (visieren oder mit Bemerkung zurückgeben, mit
  Benachrichtigungen), Export für die Lohnbuchhaltung (CSV) und Monatsblatt als PDF.
- **Architekturprüfung:** Module dürfen weder Klassen oder Daten anderer Module noch Core-Daten am Repository vorbei
  verwenden; geprüft in `composer ci:check` und auf der Seite «Module».

### Geändert

- **GitHub-Ablauf aus dem Blueprint:** Konfiguration `.github/blueprint.toml`, Werkzeuge unter `.github/blueprint/`,
  Workflows `release` (läuft bei jedem Push, legt Tag und Release an, sobald «deploy» grün ist – auch nach einer
  nachgeschobenen Korrektur), `issue-milestone`, `milestone-admin` und neu `Blueprint anwenden`. Ablauf in
  `docs/GITHUB-ABLAUF.md`, Intranet-Eigenes in `docs/KONZEPT-VERSIONIERUNG.md`; Abschnitt in `AGENTS.md` mit
  Markierungen, `.github/copilot-instructions.md`. Dependabot zusätzlich für Composer, npm und Docker (Label
  `abhaengigkeiten`, 5 Tage Wartezeit). Version setzen bleibt bei `php tools/release.php`.
- **PHPUnit 13** (Entwicklung/Tests, #23); ein Test räumt seinen Ordner jetzt ohne Warnung auf.
- **npm-Pakete** (#24): TypeScript 7, lucide-react 1.63 (Symbole), @tanstack/react-table 9, @types/node 26, Rollup 4.66.

### Behoben

- **Module auf dem Server:** Mit zwischengespeicherter Konfiguration (`php artisan optimize`) wurde ein Modul als
  ungültig verworfen – Menüpunkt sichtbar, Seite 404. Ausgeschaltete Module verschwinden jetzt auch aus dem Menü.

## [0.32.0] – 2026-11-01

### Neu

- **GitHub-Ablauf aus TagStudio (#2):** Issue-Vorlagen Fehler, Verbesserung, Neue Funktion, Plugin-Idee und
  GUI-Baustein mit Label `triage`; Freigabe zur Umsetzung nur über `_1 bereit`, danach Status `_2 in Arbeit` und `_3 Beta-Test`. Deutsche Labels, Sammlungen
  «Fehler», «Verbesserungen», «Funktionen» mit automatischer Zuordnung (`issue-milestone.yml`), PR-Vorlage,
  `CONTRIBUTING.md`, `SECURITY.md`, Regeln in `AGENTS.md`.
- **Versionierung (#3):** `VERSION` ändert sich nur noch beim freigegebenen Release (`tools/release.php`); der
  Workflow `release.yml` legt nach grünem Deployment Tag `vX.Y.Z` und GitHub-Release mit dem Text aus dem
  CHANGELOG an. Tags und Releases v0.11.0–v0.31.0 rückwirkend über «release» mit «nachtragen»
  (`.github/release-backfill.txt`). `tools/release.php --check` prüft in
  `composer ci:check` Version, CHANGELOG und Plugin-Versionen.
- **Developer Release (#4):** Seitenleiste und «Versionen» zeigen Stände nach dem letzten Tag als «Developer Release»
  mit Anzahl Änderungen und geplanter Version; «Unveröffentlicht» erscheint als «In Entwicklung».
- **Als GitHub-Issue vorbereiten (#5):** Bei offenen Wünschen zu GUI-Bausteinen und auf «Versionen» («Fehler
  melden», «Idee vorschlagen») öffnet die Administration das GitHub-Formular vorausgefüllt – ohne Namen,
  abgeschickt wird von Hand (`INTRANET_GITHUB_REPO`).
- **Konzept Module (`docs/KONZEPT-MODULE.md`):** ein Intranet, ein Deployment; Module als Ordner mit eigener
  Datenhaltung; Systematische Rechtssammlung als eigenes öffentliches Projekt mit API; geplant in den Meilensteinen «0.33.0 – Modulgerüst & Pilot
  Zeiterfassung» und «Rechtssammlung – Projektstart» (#7–#20, noch nicht freigegeben).
- **Meilensteine per Kommentar (#6):** `/milestone list|create|close|reopen` auf Issue #1 (`milestone-admin.yml`).

## [0.31.0] – 2026-11-01

### Neu

- **Benachrichtigungen:** Glocke oben rechts (ungelesene, neueste, «Alle gelesen»), Seite `/benachrichtigungen`;
  neue Meldungen erscheinen ohne Neuladen zusätzlich als kurze Meldung mit «Öffnen»
- GUI-Prototypen melden: «… wartet auf Prüfung» (an Prüfer:innen), «zurück an Entwicklung» mit Bemerkung,
  «freigegeben» (an alle mit GUI-Zugang), «Neuer Wunsch» (an die Entwicklung), «dein Wunsch fliesst ein» (an die
  Autor:in). Ein Klick öffnet den Baustein im passenden Reiter
- Rechte `use-gui` (Administration, Schulleitung, Fachstelle ICT, Redaktion: ansehen, Wünsche) und `review-gui`
  (Administration, Schulleitung: prüfen, freigeben); Versionen anlegen und zur Prüfung geben weiterhin Administration
- Konzept `docs/KONZEPT-BENACHRICHTIGUNGEN.md`; Sammlung `notifications`

### Geändert

- Menü «GUI-Prototypen» auch für Schulleitung, Fachstelle ICT und Redaktion; Knöpfe je nach Recht
- Dokumentation nachgeführt: README, AGENTS, Rollen-, GUI- und Theme-Konzept

## [0.30.0] – 2026-11-01

### Neu

- GUI-Prototypen nach Abgleich mit shadcn/ui um 11 Bausteine ergänzt: Typografie (mit Tasten/Kbd und Trennlinie),
  Eingabegruppe, Knopfgruppe, Eintrag, Fragebogen (für «Umfragen»), Rückfrage (Alert Dialog), Kontextmenü (mit
  sichtbarer Alternative), Vorschaukarte (Hover Card), Scrollbereich, Codeblock, Geteilte Ansicht (Resizable)
- Bestehende Bausteine erweitert: Navigationsmenü (Menü), Drawer von unten (Seitenpanel), einfaches Auswahlfeld
  (Dropdown), Feldgruppen (Formularfelder), Anhang mit Vorschaubild und Fortschritt (Datei hochladen), Linien- und
  Ringdiagramm (Diagramme)
- Inhaltsverzeichnis mit Abschnitt «Später»: Aspect Ratio, Carousel, Menubar, Input OTP, Direction sowie Chat- und
  KI-Bausteine; ebenso in `docs/GUI-KOMPONENTEN.md`

## [0.29.0] – 2026-11-01

### Neu

- Farbkonzept mit **Theme-Auswahl**: Farbkonzepte (Themes) für hell und dunkel speichern, als Vorschau ansehen,
  für alle aktivieren, bearbeiten, kopieren und löschen; Vorlagen «Hoher Kontrast», «Petrol», «Violett»
- Theme-Editor mit Farbwählern je Farbwert (hell/dunkel), Kontrastprüfung nach WCAG AA und Live-Vorschau
- GUI-Prototypen: Leiste «Vorschau» über den Beispielen mit Umschalter **Hell / Dunkel** und **Theme-Auswahl**
- Aktives Theme gilt sofort im ganzen Intranet; Konzept `docs/KONZEPT-THEMES.md`, Sammlung `themes`

## [0.28.0] – 2026-11-01

### Neu

- GUI-Prototypen: **Inhaltsverzeichnis** (`/gui-prototypen`) mit allen Bausteinen nach Bereich, Erklärung,
  Bestandteilen, Status und Link zum Prototyp sowie den Regeln «Für alle Bausteine»; dieselbe Übersicht als
  `docs/GUI-KOMPONENTEN.md`. «Freigaben & Status» neu unter `/gui-prototypen/freigaben`
- Menü «GUI-Prototypen» mit Untermenüs Navigation & Aufbau, Eingabe, Daten anzeigen, Rückmeldung
- 19 neue Bausteine mit Beispielen: Seitenpanel, Reiter (Tabs), Seitenweise Anzeige, Schrittanzeige,
  Befehlspalette (Ctrl+K), Optionsfelder, Schalter, Auswahl mit Suche, Mehrfachauswahl, Datumsauswahl (TT.MM.JJJJ,
  Kalender ab Montag), Datei hochladen (Ablagefläche), Schieberegler, Drag & Drop, Akkordeon, Fortschritt,
  Diagramme (mit «Als Tabelle»), Zeitleiste, Popover, Banner; Avatar bei «Badges, Status & Avatar»
- **Drag & Drop** mit dnd-kit: Liste umsortieren (Griff, Tastatur, Nach oben/unten), Zuordnen Schüler:innen →
  Klassen und Kanban (Ziehen oder «Nach …»), deutsche Ansagen für Bildschirmleser; Regeln in
  `docs/GUI-KOMPONENTEN.md`

### Geändert

- «Reiter & Umschalter» heisst neu «Umschalter & Aufklappen» (Reiter sind ein eigener Baustein)

## [0.27.0] – 2026-11-01

### Neu

- Hauptmenü «GUI-Prototypen» (zuunterst, Administration) mit Untermenüs je Baustein: Menü, Tabellen (Grundlage:
  Personen), Dropdown (ohne/mit Vorauswahl, Fehler, Mehrfachauswahl), Button, Formularfelder, Badges & Status,
  Meldungen & Dialoge, Karten & Seitenkopf, Reiter & Umschalter, Ladezustände – je mit Live-Beispielen
- Status je Version Entwicklung → Prüfung → Freigegeben (zurück an Entwicklung mit Bemerkung), Versionsnummer
  (Korrektur/Erweiterung/grundlegend neu) und Datum mit Person je Status; höchstens eine offene Version
- Freigabe = verbindliches Template: alle Seiten, die den Baustein verwenden, werden automatisch zur Überarbeitung
  vorgemerkt und einzeln abgehakt; Übersicht mit offenen Überarbeitungen
- Verbesserungswünsche je Baustein, übernehmbar in die nächste Version
- Konzept `docs/KONZEPT-GUI-PROTOTYPEN.md`; Sammlung `gui_components`; Auswahlliste «Status GUI-Prototypen» im
  Farbkonzept

## [0.26.1] – 2026-11-01

### Behoben

- ePost: Mit nur einem Token (ohne Benutzername/Passwort) galt das Plugin als «nicht fertig eingerichtet»,
  Verbindungstest und API-Tester waren gesperrt. Ein eingefügtes Zugriffstoken («eyJ…», auch mit «Bearer »)
  wird jetzt als Bearer-Token gesendet, ein API-Schlüssel als X-API-KEY

### Geändert

- Plugins: gespeicherte Zugangsdaten (Token, Passwörter, Secrets) erscheinen im Formular als «********»
- Manifest: Einstellung `required_unless` (Pflicht nur, wenn eine andere Einstellung leer ist)

## [0.26.0] – 2026-10-31

### Neu

- Plugin «ePost (Schweizerische Post)»: ePost Communication Platform / KLARA Public API mit 44 Endpunkten aus der
  OpenAPI der Post (Versand über eLetter, E-Mail, SMS und Brief, Vorschau von Kanälen und Preisen, Monitoring,
  Identitätsabgleich, digitaler Briefkasten, ePost Sign). Umgebung Test oder Produktion wählbar
- Aktionen «Mandanten anzeigen», «Posteingang» (ungelesene Briefe) und «Versandübersicht» (ohne Empfängerdaten);
  alles Schreibende (Versand, Abgleich, Briefe ändern) nur im API-Tester mit Schreibfreigabe und Rückfrage
- Plugin-System: Anmeldetyp `oauth2_password` (Passwort-Anmeldung mit Zusatzfeldern, Token wird zwischengespeichert;
  alternativ API-Schlüssel); Plugin-Kategorie «Kommunikation»

## [0.25.0] – 2026-10-31

### Geändert

- Plugin Inventory360 nach der API-2.0-Referenz von enteksystems: 40 Endpunkte (Hardware, Software, Lizenzen,
  Verträge, Standorte/Räume, Benutzer, Mitarbeitende, Status, eigene Rechte, Version …), schreibende nur mit Rückfrage
- Token-Übergabe: Vorgabe jetzt «Authorization» ohne Präfix (laut Referenz); Bearer/Token weiterhin wählbar
- Verbindungstest zeigt die Anzahl Geräte; Suche mit Seitenangabe («50 von 1200»)

### Neu

- Inventory360-Aktionen «Gerät anzeigen» (nach Inventarnummer) und «Hardware-Kategorien»

## [0.24.0] – 2026-10-31

### Neu

- Verbindung Intranet ⇄ Tab einer Fachanwendung (SBI-Connector), auf der Seite jeder Fachanwendung mit
  «Über die Browser-Erweiterung» bzw. «Nebeneinander»:
    - **Abfragen:** Was ist im Tab offen? IDs aus der Adresse werden über die Datenherkunft (PersonLinks) mit
      Personen im Intranet abgeglichen (Escola, Jamf); Schüler:innen nur mit Recht «view-students»
    - **Melden:** Wechselt die Seite im Tab, aktualisiert sich die Anzeige von selbst
    - **Abruf über die Anmeldung im Tab:** lesende Plugin-Endpunkte derselben Adresse (z. B. LMVZ Digital
      Lizenzübersicht) – die Anmeldung der Seite verlässt den Tab nie; nur Fachstelle ICT und Administration
    - **Steuern:** Seite öffnen, Tab nach vorne, Feld ausfüllen (nie absenden)
    - **Übernehmen:** Tabelle aus dem Tab als Vorschau mit CSV (nur im Browser)
- «Nebeneinander»: Intranet und Fachanwendung als zwei Fenster je halber Bildschirm (Einstellung und Knopf)
- Fachanwendung «LMVZ Digital» (über die Browser-Erweiterung)
- Erweiterung: «Website verbinden» im Fenster der Erweiterung; Adressen der Fachanwendungen sind im
  heruntergeladenen Manifest bereits freigegeben

## [0.23.2] – 2026-10-31

### Geändert

- Klick auf die erste Brotkrume einer Einbindung (z. B. «Fachanwendungen») blendet das Seitenmenü wieder ein

## [0.23.1] – 2026-10-31

### Neu

- Einstellungen: «Einbetten prüfen» je Menüpunkt zeigt, warum sich eine Seite nicht einbetten lässt
  (X-Frame-Options, Content-Security-Policy frame-ancestors, Server, Cloudflare ja/nein); dieselbe Erklärung
  erscheint auf der Seite der Einbindung

### Behoben

- Escola sendet `frame-ancestors 'self'` (nur www.sekbuelach.ch darf einbetten, nicht das Intranet): Escola
  öffnet wieder in einem eigenen Tab statt in einem leeren Rahmen (Bestätigung aus 0.22.1 zurückgenommen)

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
