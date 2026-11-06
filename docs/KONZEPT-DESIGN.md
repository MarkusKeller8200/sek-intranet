# Konzept: Design-Werkzeuge (VisBug und Penpot)

Entwurf vom 06.11.2026 nach Besprechung mit dem Projektverantwortlichen. Umsetzung erst nach Freigabe der einzelnen
Issues (`_1 bereit`). Übersicht: #152, Meilenstein «Design & Developer-Werkzeuge».

## Ziel

Gestaltung des Intranets gemeinsam verbessern: Abstände, Farben, Schriften und Ausrichtung auf den Seiten sehen,
Änderungen genau beschreiben und neue Seiten als Entwurf vorbereiten. Der Code bleibt die verbindliche Quelle;
Änderungen kommen immer über ein Issue bzw. einen Pull Request in das Intranet.

Drei Stufen:

1. **Sofort:** Browser-Erweiterung VisBug und die Entwicklertools des Browsers – sehen und genau melden.
2. **Danach:** Penpot als Design-Werkzeug (wie Figma, quelloffen) für Entwürfe und eine gemeinsame Bibliothek.
3. **Später:** Verbindung von Penpot und Code über Design-Tokens, dazu Developer-Werkzeuge im Intranet.

## Stufe 1: VisBug und Entwicklertools (sofort)

**VisBug** (gratis, von Google, Chrome Web Store; läuft in Chrome und Edge):

- Messen: Abstand zwischen Elementen, Grösse in px, Innen- und Aussenabstände (Padding/Margin) farbig markiert
- Schrift, Farbe und Kontrast eines Elements anzeigen
- Hilfslinien für die Ausrichtung
- Testweise verschieben und ändern – nur im eigenen Browser, nach dem Neuladen wieder weg

Sicherheit: In den Einstellungen der Erweiterung den Websitezugriff auf «Auf bestimmten Websites» stellen und nur
`intranet.sekbuelach.ch` (bzw. die Demoplattform) eintragen.

**Entwicklertools des Browsers** (ohne Installation): `F12` bzw. Rechtsklick → «Untersuchen» zeigt Grösse, Padding
und Margin; unter «Stile» stehen die Variablen des Farbkonzepts (z. B. `--primary`). Die **CSS-Übersicht** (Chrome:
Menü → Weitere Tools → CSS Overview) listet alle Farben, Schriften und Abstände einer Seite – gut für Ausreisser.

**Änderungen melden** (im Chat oder als Issue):

1. Wo: Seite bzw. Adresse und Element (z. B. «Zeiterfassung, Tagestotal-Zeile»)
2. Ist: Wert aus VisBug bzw. Entwicklertools (z. B. «Abstand oben 8 px, Schrift 14 px, Farbe `--muted-foreground`»)
3. Soll: gewünschter Wert (z. B. «16 px», «13 px», «etwas dunkler»)
4. Optional: Screenshot mit den Messlinien

Die Änderung wird im passenden GUI-Baustein umgesetzt, damit sie überall gilt. Screenshots nur vom Testserver bzw.
der Demoplattform mit erfundenen Daten – nie von Seiten mit echten Personendaten (z. B. CMI).

## Stufe 2: Penpot

Penpot ist ein quelloffenes Design-Werkzeug im Browser (Lizenz MPL-2.0), vergleichbar mit Figma. Die Software ist
gratis; Kosten entstehen nur für den Server. Figma wäre für die Entwicklung des Intranets kostenpflichtig (die
Bildungslizenz gilt nur für Unterricht, nicht für administrative oder kommerzielle Zwecke).

### Vorgehen

1. **Test** (etwa zwei Wochen): Konto in der Penpot-Cloud, Bibliothek für shadcn/ui, Import der Tokens, Ablauf mit
   Issues ausprobieren. Nur erfundene Inhalte.
2. **Eigener Server**, wenn der Test passt (siehe Hosting).

### Hosting: Hostinger KVM 2 mit Penpot

Angebot (06.11.2026): Hostinger **KVM 2 mit Penpot** für **zwei Jahre 175.00** (rund 7.30 pro Monat).

- KVM 2: 2 vCPU, 8 GB RAM, 100 GB NVMe, 8 TB Datentransfer pro Monat (laut Übersicht; Angebot vor der Bestellung
  prüfen). Penpot empfiehlt 2 vCPU und 4 GB RAM – genug Reserve.
- **Standort Deutschland (Frankfurt)** wählen. In Penpot liegen nur Entwürfe mit erfundenen Daten und die Konten der
  Mitarbeitenden (Name, E-Mail); dafür genügen ein Standort in der EU und der Auftragsverarbeitungsvertrag von
  Hostinger. Keine Schülerdaten und keine echten Personendaten in Penpot.
- **Eigener Server**, getrennt vom Intranet und von der Demoplattform (eigene Updates, eigene Last).
- Bestellen und das Root-Passwort bzw. den SSH-Schlüssel verwaltet der Projektverantwortliche; Claude meldet sich
  nicht an und erhält keine Zugangsdaten im Chat.

### Was Penpot braucht

- **Dienste** (Docker Compose bzw. Vorlage von Hostinger): Frontend (Nginx), Backend (Java), Exporter (Headless
  Chromium für PDF/PNG/SVG), PostgreSQL, Valkey (wie Redis), Dateiablage (auf dem Server oder S3-kompatibel)
- **Domain und TLS:** z. B. `design.sekbuelach.ch`, Zertifikat automatisch (Let's Encrypt)
- **Anmeldung:** OIDC mit **Microsoft Entra ID** (Schul-Login); offene Registrierung ausschalten
- **E-Mail** für Einladungen: SMTP über einen Mail-Dienst (Port 587)
- **Sicherung:** täglich Datenbank-Dump und Dateien, verschlüsselt ausserhalb des Servers (z. B. restic auf Swiss
  Backup), wie im Betriebskonzept (`docs/KONZEPT-BETRIEB.md`)
- **Updates:** feste Penpot-Versionen, etwa monatlich nachziehen; Erreichbarkeit überwachen

### Aufbau in Penpot

- Team «SekBülach», Projekt «Intranet»
- Gemeinsame Bibliothek «SekBülach Intranet» mit denselben Namen wie die GUI-Bausteine
  (`config/intranet.php` → `gui_components`), Start mit einem Kit für shadcn/ui
- Seiten- und Ablaufentwürfe mit Demo-Personen

## Stufe 3: Verbindung zum Code

1. **Design-Tokens als einzige Quelle:** Datei `resources/tokens/tokens.json` im Format der W3C Design Tokens
   Community Group (DTCG). Ein Build-Schritt (z. B. Style Dictionary) erzeugt daraus die Variablen in
   `resources/css/app.css` (hell und dunkel) und die Vorgaben der Themes; die CI prüft den Kontrast (WCAG AA).
   Penpot importiert und exportiert diese Datei; Änderungen kommen als Pull Request zurück.
2. **Issues mit Link** auf den Frame in Penpot; umgesetzt wird mit den GUI-Bausteinen.
3. **GUI-Prototypen:** Entwurf (Link bzw. Ansicht aus Penpot) neben der Umsetzung; Einbettung über die Einstellungen
   der Einbindungen, erlaubt über `frame-src` (#147).
4. **Zugang für Claude:** persönliches Zugriffstoken aus Penpot (nur lesend nutzen, verschlüsselt hinterlegt, nie im
   Chat), über die API bzw. eine MCP-Anbindung – Verfügbarkeit zu prüfen. Ohne Anbindung genügen Link und Export.
5. **Nicht vorgesehen:** Code automatisch aus dem Entwurf übernehmen oder Gestaltung am Code vorbei ändern.

## Developer-Werkzeuge im Intranet (später)

- Rolle «Developer» mit Recht `use-dev-tools`, Schalter «Developer-Modus» im Benutzermenü (Vorgabe aus); keine
  zusätzlichen Rechte auf Personendaten
- Beim Ziehen eines Splitters Breite in px und % anzeigen
- Inspektor nur zum Anzeigen: Masse, Abstände, Tokens statt Hex-Werte, Name des GUI-Bausteins, Kontrast
- Später: live ausprobieren und «Als Änderung vorschlagen» → Issue (`triage`) mit Seite, Baustein, alt → neu

## Reihenfolge

1. VisBug installieren, Websitezugriff einschränken, Änderungen nach dem Schema oben melden (sofort).
2. Penpot-Cloud testen (Bibliothek, Tokens, Ablauf).
3. Hostinger KVM 2 mit Penpot bestellen (Standort Frankfurt), Domain, Microsoft-Login, Sicherung.
4. Token-Datei und Build-Schritt im Intranet, Kontrastprüfung in der CI.
5. Developer-Werkzeuge im Intranet.

## Offene Fragen

1. Wer gestaltet neben dem Projektverantwortlichen mit (Konten in Penpot)?
2. Domain für Penpot (z. B. `design.sekbuelach.ch`)?
3. Penpot-Cloud zuerst testen oder direkt den eigenen Server bestellen?
