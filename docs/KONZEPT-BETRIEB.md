# Konzept: Betrieb, Mandanten und Selbstbedienung

Entwurf vom 06.11.2026 nach Besprechung mit dem Projektverantwortlichen. Umsetzung erst nach Freigabe der einzelnen
Issues (`_1 bereit`).

## Ziel

Eine Schule oder Gemeinde, die das Intranet nutzen möchte, füllt ein Formular aus. Danach wird ihre Installation
automatisch aufgesetzt – in der Cloud (Schweizer Hoster) oder als Paket für den eigenen Server (On-Premise). Der
Installationsaufwand ist so klein wie möglich und vollständig automatisierbar.

## Entscheid: Mandanten komplett getrennt

1. **Eine eigene Installation pro Kunde** (Silo-Modell): eigener Container mit dem gleichen Image, eigenes
   Datenvolumen, eigene Datenbank (später), eigener App-Schlüssel, eigene Subdomain mit TLS, eigene Sicherung,
   eigene Logs. Getrenntes Netzwerk; gemeinsam ist nur der Reverse Proxy, der nach Subdomain weiterleitet.
2. **Keine Mandanten-Logik im Intranet.** Jede Installation kennt nur die eigene Schule. Es gibt keine gemeinsame
   Datenbank, keinen gemeinsamen Speicher und keinen gemeinsamen Cache.
3. **Der Betreiber sieht keine Schuldaten.** Support-Zugriff nur, wenn die Administration der Schule ihn befristet
   freischaltet, mit Protokoll.
4. **Ein Produkt, mehrere Betriebsarten.** Cloud und On-Premise nutzen dasselbe Image und dieselben Releases. Der
   Code fragt nur nach Fähigkeiten (`INTRANET_STORE`, `INTRANET_FILES`, `INTRANET_DATA_LOCATION`), nie nach der
   Betriebsart.

### Angebote: wie weit getrennt (Entscheid 06.11.2026)

Beide Varianten der Cloud werden angeboten; die Schule wählt im Formular. Die Installation ist in allen Fällen
dieselbe, es unterscheidet sich nur, wo sie läuft.

1. **«Geteilt» – Daten getrennt:** Installation als Container auf einem gemeinsamen Server. Getrennt sind Daten,
   Dateien, Schlüssel, Netzwerk, Konten und Einstellungen. Gemeinsam sind Betriebssystem-Kern, Docker, Reverse Proxy,
   Rechenleistung und der Root-Zugang des Betreibers. Eine Lücke im Kernel oder in Docker könnte alle Installationen
   des Servers betreffen. Absicherung: Container ohne Root-Rechte, nur lesbares Dateisystem, keine Sonderrechte,
   User-Namespaces, Grenzen für RAM und CPU, automatische Sicherheitsupdates, kein Shell-Zugang für Kunden.
   Günstig; für Demo, Tests und Schulen, die das bewusst wählen.
2. **«Dediziert» – Installation und Daten getrennt:** eigener virtueller Server pro Schule mit eigenem Kern, eigenem
   Docker, eigener Firewall und eigenem Root-Zugang; gemeinsam bleibt nur die Virtualisierung des Hosters.
   Automatisch bestellt: Hostinger per API (`POST /api/vps/v1/virtual-machines`), Infomaniak über die Public Cloud
   (OpenStack). Vorgabe für echte Schuldaten (#140).
3. **On-Premise:** eigener Server bei der Schule oder Gemeinde, nichts geteilt (Installationspaket, #116).

Ein Wechsel von «Geteilt» zu «Dediziert» ist jederzeit möglich (Export/Import, #109). Stufen 1 und 2 der
Verschlüsselung gelten für alle Angebote.

## Drei Teile, drei Projekte

```
Abo-Plattform            Deploy-Plattform              Intranet (dieses Repository)
Formular, Vertrag, ADV   richtet Installationen ein,   eine Installation pro Kunde,
Kunden, Freigabe,   ───► aktualisiert, sichert,   ───► gleiches Image, eigene Daten
Abrechnung, Kündigung    sperrt, löscht                und eigener Schlüssel
keine Schuldaten         keine Schuldaten
```

### 1. Abo-Plattform (eigenes Projekt)

Kundensicht und Verträge. Kennt nur Vertrags- und Kontaktdaten, nie Schuldaten.

- Formular: Schule/Gemeinde, Kanton, Kontaktperson, Subdomain, Module, Betriebsart (Cloud oder On-Premise),
  Speicherort. Schutz vor Spam (Captcha, Begrenzung der Anfragen).
- E-Mail-Bestätigung (Double-Opt-in), Nutzungsbedingungen und Vertrag zur Auftragsdatenbearbeitung (ADV) online.
- Freigabe: anfangs durch den Projektverantwortlichen, später automatisch für bestätigte Schul-Domains.
- Kundenkonto: Abo, Module, Termin für Updates, Kündigung, Export anfordern.
- Abrechnung (falls kostenpflichtig) und Mitteilungen an Kundinnen und Kunden.
- Gibt Aufträge an die Deploy-Plattform weiter (anlegen, ändern, sperren, löschen).

### 2. Deploy-Plattform (eigenes Projekt)

Technik und Betrieb. Spricht über die API des Hosters mit der Infrastruktur.

- Pro Kunde: Umgebung, Volumen, Geheimnisse und App-Schlüssel, DNS und TLS, Container starten, `intranet:setup` mit
  der Konfiguration aus dem Formular, Einladungslink für die erste Administration.
- On-Premise: Installationspaket (`docker-compose.yml`, Konfiguration, Einrichtungs-Token) statt Cloud-Umgebung.
- Updates: freigegebene Version nacheinander ausrollen, Health-Check, automatisch zurückrollen; Wunschtermin des
  Kunden beachten.
- Sicherung täglich pro Kunde, mit dem Schlüssel des Kunden verschlüsselt; Wiederherstellen per Auftrag.
- Sperren, Export und Löschung nach Frist mit Protokoll.
- Überwachung (`/health`), Kapazität und Kosten pro Installation.

### 3. Intranet (dieses Repository)

Was das Intranet selbst können muss, damit es automatisch aufgesetzt und betrieben werden kann:

1. **Container-Image** pro Release und Konfiguration komplett über Umgebungsvariablen.
2. **Einrichtung ohne Rückfragen:** `php artisan intranet:setup --config=…` (Name, Logo, Kanton, Module) ohne
   Demo-Daten.
3. **Einladungslink für die erste Administration** (einmalig, befristet); Passwort und Admin-PIN setzt sie selbst.
4. **Health-Endpunkt** `/health` für Überwachung und Updates.
5. **Export und Import** der ganzen Installation (Sammlungen, Dateien, Plugin-Einstellungen), um den Betreiber oder
   die Betriebsart zu wechseln.
6. **Befristeter Support-Zugriff** durch die Administration der Schule freigeschaltet, mit Protokoll.
7. **Ersatz für Webhooks durch Abfragen** (z. B. DeepSign), weil On-Premise oft nicht aus dem Internet erreichbar
   ist; ausgehende Verbindungen optional über einen Proxy.
8. **Speicherort als Angabe des Betreibers** (`INTRANET_DATA_LOCATION`) statt Annahme im Code; die Sperre der
   Personendaten in Plugins stützt sich darauf.
9. **Betriebshandbuch:** Anforderungen, Installation, Sicherung, Update, Adressen für die Firewall.

## Automatische Updates

Ziel: Alle Installationen sind ohne Handarbeit aktuell, ein fehlerhaftes Update trifft nie alle gleichzeitig und
lässt sich automatisch zurücknehmen. Freigegeben wird eine Version weiterhin nur vom Projektverantwortlichen
(`docs/KONZEPT-VERSIONIERUNG.md`); automatisch ist nur das Ausrollen.

### Was automatisch kommt (nach SemVer)

1. **Patch** (z. B. 0.36.1, Fehler- und Sicherheitskorrekturen): automatisch im nächsten Wartungsfenster, ohne
   Ankündigung. Dringende Sicherheitskorrekturen auch ausserhalb des Fensters, mit Hinweis an die Administration.
2. **Minor** (z. B. 0.37.0, neue Funktionen): automatisch im Wartungsfenster, Ankündigung an die Administration
   mindestens 3 Tage vorher (Text aus «Für Mitarbeitende»). Danach wie heute die Mitteilung «Neu im Intranet».
3. **Major** (z. B. 1.0.0, grössere Umstellungen): nie automatisch (ausser im Kanal «Beta»). Die Administration
   bestätigt im Kundenkonto oder wählt einen Termin; nach spätestens 90 Tagen wird nachgezogen, weil die alte
   Version nicht mehr unterstützt wird.

### Kanäle und Wellen

- **Kanal «Beta»:** Wer sich im Kundenkonto für Beta-Versionen registriert, bekommt **alle Versionen so rasch wie
  möglich** – Developer Releases, Patch, Minor und Major – ohne Abstand zwischen Wellen und ohne Bestätigung, im
  nächsten Wartungsfenster nach der Veröffentlichung. Abmelden jederzeit; zurück auf «Stabil» geht es mit der
  nächsten stabilen Version (kein Zurückspringen auf ältere Daten).
- **Kanal «Stabil»** (Vorgabe): nur freigegebene Releases, in Wellen: Welle 0 eigene Test-Installation, Welle 1
  die Beta-Installationen (haben die Version dann schon), Welle 2 alle übrigen. Zwischen den Wellen mindestens
  24 Stunden (Patch) bzw. 3 Tage (Minor). Häufen sich Fehler in einer Welle, hält das Ausrollen automatisch an, bis
  es freigegeben wird.

### Wartungsfenster

- Möglich zwischen **23:00 und 05:00** Uhr; der Kunde wählt im Kundenkonto ein Fenster in diesem Bereich (Vorgabe
  die ganze Zeit 23:00–05:00). Ausserhalb gibt es nur dringende Sicherheitskorrekturen.
- Sperrzeiten, die der Kunde setzt (z. B. Zeugniswoche, Semesterende): keine Minor- oder Major-Updates.
- Updates höchstens 30 Tage aufschieben; Sicherheitskorrekturen lassen sich nicht aufschieben.

### Ablauf pro Installation

1. Vorprüfung: `/health` in Ordnung, genug Speicherplatz, keine laufende Sperrzeit.
2. Image holen und prüfen: nur signierte Images mit bekannter Prüfsumme.
3. Sicherung: Schnappschuss von Daten und Dateien unmittelbar vor dem Update.
4. Wartungsmodus mit Hinweis für Angemeldete; neuer Container startet, `intranet:upgrade` stellt die Daten um.
5. Prüfung: `/health` und eine kurze Funktionsprobe (Anmeldeseite, Startseite).
6. Umschalten auf den neuen Container; der alte bleibt 24 Stunden bereit.
7. Bei Fehler: automatisch zurück auf den alten Container, bei geänderten Daten mit dem Schnappschuss aus Schritt 3;
   Meldung an den Betreiber, die Welle hält an.

### Regeln für die Entwicklung

- `intranet:upgrade` läuft beliebig oft ohne Schaden und hat einen Probelauf (`--dry-run`).
- Datenumstellungen in zwei Schritten (erweitern, später aufräumen): Die vorherige Minor-Version muss die Daten der
  neuen noch lesen können, damit Zurückschalten ohne Wiederherstellung geht.
- Die Datenversion steht in den Daten; eine ältere Version startet nicht auf neueren Daten, die sie nicht kennt.
- Plugins und Module kommen nur mit dem Image, nie einzeln.
- Abhängigkeiten werden laufend aktualisiert (automatische Pull Requests); Sicherheitslücken führen zu einem Patch.
- Unterstützt werden die aktuelle und die vorherige Minor-Version.

### On-Premise

- Optionaler **Update-Dienst** als kleiner Container neben dem Intranet: Er fragt den Kanal ab, prüft die Signatur
  und führt denselben Ablauf aus (Sicherung, Upgrade, Prüfung, Zurückschalten). Nur ausgehende Verbindungen zur
  Registry, keine Verbindung von aussen nötig.
- Ohne Update-Dienst: Hinweis in der Administration, sobald eine neue Version freigegeben ist, und Anleitung
  (`docker compose pull && docker compose up -d`). Ohne Internet: Paket zum Herunterladen.

## Hosting

Entscheid 06.11.2026: Start mit **Infomaniak VPS Lite** (Schweiz), zuerst als Demoplattform auf dem kleinsten
Angebot (1 vCPU, 2 GB RAM, 20 GB).

- Der VPS wird **von Hand bestellt** (keine Bestellung per API bekannt, Jahresbindung). Automatisiert wird alles
  darauf: Die Deploy-Plattform legt pro Kunde Container, Volumen, Netzwerk und Schlüssel per SSH bzw. Docker an.
- Eine Wildcard-Domain (`*.<domain>`) zeigt auf den Server; pro Kunde ist kein DNS-Eintrag nötig. Zertifikate holt
  der Reverse Proxy pro Subdomain automatisch.
- Keine Snapshots bei VPS Lite: Die Deploy-Plattform sichert selbst, verschlüsselt pro Kunde, auf einen S3-Speicher
  in der Schweiz (z. B. Infomaniak Public Cloud oder Swiss Backup). Der Schnappschuss vor Updates ist eine solche
  Sicherung.
- Ausgehend ist Port 25 gesperrt: Mails über einen SMTP-Dienst mit Port 587.
- Ziele austauschbar halten: «Container auf bestehendem Server» (VPS Lite) und später «eigener Server pro Kunde»
  in der Infomaniak Public Cloud (OpenStack-API, Terraform), z. B. als Option «eigener Server».
- Die Grösse für den Betrieb (Schulen pro Server) zeigt ein Lasttest auf der Demoplattform.

### Infomaniak-API (geprüft 06.11.2026, developer.infomaniak.com)

- **VPS (Lite und Cloud): keine Endpunkte.** Bestellen, neu installieren und Snapshots nur von Hand im Manager.
- **DNS:** `POST /2/zones/{zone}/records`, `PUT`/`DELETE /2/zones/{zone}/records/{record}` – Subdomain pro Kunde
  anlegen und entfernen; auch für Zertifikate per DNS-Prüfung (Wildcard) nutzbar.
- **Swiss Backup:** `POST /1/swiss_backups/{id}/slots` (Platz pro Kunde), `GET …/slots/{slot}/rclone`
  (Zugangsangaben für rclone), Platz deaktivieren und löschen.
- **Public Cloud:** Projekte, Benutzer, Zugangsdaten, Object Storage (S3), Datenbanken, Kubernetes per REST; die
  virtuellen Server selbst über die OpenStack-API bzw. Terraform. Ein Projekt pro Kunde ist möglich.
- API-Token aus dem Infomaniak Manager mit nur den nötigen Rechten (DNS, Swiss Backup), verschlüsselt in der
  Deploy-Plattform; nie im Repository oder im Chat.

### Was auf dem VPS läuft

Einmalig von Hand: VPS bestellen, Linux (Ubuntu LTS) mit SSH-Schlüssel. Danach automatisch:

1. **Grundeinrichtung per Skript** (einmal, wiederholbar): Updates, Firewall (22/80/443), Docker Engine mit
   Compose, Reverse Proxy (Container, holt Zertifikate), Deploy-Agent.
2. **Deploy-Agent** (kleiner Container auf dem VPS): holt Aufträge bei der Deploy-Plattform ab (nur ausgehende
   Verbindung, kein Zugang von aussen nötig) und führt sie mit Docker aus.
3. **Pro Kunde ein Compose-Projekt:** Intranet-Container (Image aus #105), eigenes Volumen, eigenes Netzwerk,
   eigene `.env` mit eigenem App-Schlüssel; Label für den Reverse Proxy (Subdomain); Sicherung per rclone in den
   Swiss-Backup-Platz des Kunden.

Grob 150–250 MB RAM pro Installation: Auf dem kleinsten VPS Lite (2 GB) haben 4–6 Demo-Installationen Platz.

### Alternative: Hostinger VPS (geprüft 06.11.2026, OpenAPI 2.7.0)

Bei Hostinger lässt sich **auch der Server selbst automatisieren**:

- **Bestellen und einrichten:** `POST /api/vps/v1/virtual-machines` (Katalog-Artikel, `data_center_id`, Vorlage,
  SSH-Schlüssel, `post_install_script_id`, wöchentliche Sicherung); Standorte über `GET /api/vps/v1/data-centers`,
  Preise über `GET /api/billing/v1/catalog`.
- **Grundeinrichtung:** Post-Install-Skripte (`/api/vps/v1/post-install-scripts`) – unser Skript aus #135 läuft
  beim ersten Start.
- **Docker-Projekte pro Kunde direkt per API:** `POST /api/vps/v1/virtual-machines/{id}/docker` (Compose-Inhalt
  und Umgebungsvariablen), dazu start, stop, update, logs, löschen. Ein eigener Deploy-Agent wäre nicht zwingend.
- **Snapshots und Sicherungen:** `…/snapshot` (erstellen, wiederherstellen), `…/backups` (wöchentlich,
  wiederherstellen) – ideal für den Schnappschuss vor Updates.
- **Firewall** (`/api/vps/v1/firewall`) und **DNS** (`/api/dns/v1/zones/{domain}`).
- **Standort:** VPS u. a. in Deutschland (Frankfurt, Gebäude von Digital Realty, eigene Server von Hostinger);
  kein Standort in der Schweiz.

### Datenschutz Standort Deutschland (Volksschule Kanton Zürich)

Einschätzung, keine Rechtsberatung; vor dem produktiven Einsatz mit der Datenschutzbeauftragten des Kantons Zürich
bzw. dem Volksschulamt abklären.

- **Ausland grundsätzlich möglich:** Cloud ist Bearbeiten im Auftrag (§ 6 IDG, § 25 IDV); Bearbeitung im Ausland nur
  mit gleichwertigem Datenschutzniveau und/oder zusätzlichen Massnahmen (§ 19 IDG analog). Deutschland bzw. die EU
  gelten nach Schweizer Recht als Länder mit angemessenem Schutzniveau.
- **Vertrag ist die eigentliche Hürde:** Verlangt werden u. a. Schweizer Recht und Gerichtsstand Schweiz,
  Kontrollrechte für Schule, Datenschutzbeauftragte und Finanzkontrolle, Offenlegung aller Bearbeitungsorte und
  Unterauftragnehmer, Zustimmung bei Änderungen, Meldung von Vorfällen, Löschung und Portabilität (Merkblatt Cloud
  Computing; empfohlen: AGB Auslagerung Informatikleistungen). Standard-AGB eines Massen-Hosters erfüllen das in der
  Regel nicht und sind kaum verhandelbar.
- **Besondere Personendaten** (z. B. Gesundheit, Förderung, Verhalten) nur mit besonderen Massnahmen, vor allem
  Verschlüsselung; Datenschutz-Folgenabschätzung (§ 10 IDG) vor der Einführung.
- **Folgerung für uns:** Hostinger in Frankfurt eignet sich für die **Demoplattform mit erfundenen Daten**. Für
  echte Schuldaten nur, wenn die Vertragsfragen geklärt sind und die Daten bei Hostinger **verschlüsselt** liegen
  (Schlüssel nur bei der Schule bzw. in der Installation, nicht beim Hoster). Sonst Schweizer Hoster.
- `INTRANET_DATA_LOCATION` (#112) erhält dafür die Werte `CH` und `EU`; Personendaten-Endpunkte der Plugins bleiben
  bei `EU` gesperrt, solange nichts anderes entschieden ist.

### Verschlüsselung

Leitfaden «Nutzung externer Cloud-Dienste» der Datenschutzbeauftragten: Liegt das Schlüsselmanagement beim
öffentlichen Organ, ist das Offenbaren der Daten ausgeschlossen (Ziff. 3); Schweizer und europäische Clouds werden
bevorzugt (Ziff. 11); eine Datenschutz-Folgenabschätzung bleibt nötig, bei vielen besonderen Personendaten mit
Vorabkontrolle (Ziff. 4, 6); die Cloud darf kein höheres Risiko bringen als eine lokale Lösung (Ziff. 14).

Technischer Haken: Das Intranet muss Daten zum Anzeigen, Suchen und Rechnen entschlüsseln. Auf einem gemieteten
Server liegen Schlüssel und Klartext dabei im Arbeitsspeicher der Maschine des Hosters. Verschlüsselung schützt
sicher vor Zugriff auf Datenträger, Sicherungen und Snapshots, nicht vollständig vor einem Hoster, der aktiv in die
laufende Maschine greift.

Entscheid 06.11.2026:

1. **Stufe 1 – ganze Installation verschlüsselt:** Volumen bzw. Datenspeicher pro Kunde verschlüsselt; der Schlüssel
   liegt nicht auf dem Server, sondern kommt beim Start aus einem **Schlüsseldienst in der Schweiz** (Teil der
   Deploy-Plattform) und bleibt nur im Arbeitsspeicher. Sicherungen und Snapshots sind für den Hoster unlesbar.
2. **Stufe 2 – Feldverschlüsselung** für besonders schützenswerte Angaben (z. B. Gesundheit, Förderung,
   Verhalten): eigener Schlüssel pro Kunde, Entschlüsselung nur mit Recht; nicht in Logs, Suche oder Exporten.
3. Stufen 1 und 2 gelten **unabhängig vom Hoster** (Infomaniak, Hostinger, On-Premise).
4. **Keine Ende-zu-Ende-Verschlüsselung für das ganze Intranet.** Gründe: Suche, Summen, PDFs, Plugins (ClickTime,
   DeepSign), Benachrichtigungen, geplante Abläufe und die REST-API funktionieren nur mit Klartext auf dem Server;
   Rechte würden zu Schlüsselverteilung (neue und austretende Personen), verlorene Schlüssel bedeuten verlorene
   Daten; mehrere Geräte pro Person; der Verschlüsselungscode kommt vom Server und könnte dort manipuliert werden;
   Metadaten bleiben sichtbar; praktisch ein Neubau.
5. **Ende-zu-Ende höchstens für einen klar abgegrenzten Bereich** ohne Suche und Plugins (Idee: Modul «Vertrauliche
   Dokumente»), falls die Datenschutzbeauftragte das verlangt.
6. Hostinger Frankfurt mit Stufe 1 und 2 und Schlüssel in der Schweiz ist die Ausgangslage für die Anfrage an die
   Datenschutzbeauftragte. Bis zum Entscheid: echte Schuldaten nur bei einem Schweizer Hoster.

## Reihenfolge

1. Intranet: Container-Image, Konfiguration über Umgebungsvariablen, `/health`, `intranet:setup`, Einladungslink.
2. Deploy-Plattform: ein Server mit Docker und Reverse Proxy, Einrichtung per Skript, dann per API.
3. Intranet: sichere Updates (Probelauf, Datenversion, Wartungsmodus), signierte Images.
4. Deploy-Plattform: Updates in Wellen mit Wartungsfenster und Zurückschalten.
5. Intranet: Export/Import, Support-Zugriff, Webhook-Ersatz, Speicherort.
6. Abo-Plattform: Formular, Bestätigung, Freigabe, Auftrag an die Deploy-Plattform, Update-Einstellungen.
7. Später: Update-Dienst für On-Premise, PostgreSQL und S3-kompatibler Speicher pro Kunde, automatische Freigabe,
   Abrechnung.

## Issues

- Intranet: Meilenstein «Betrieb & Auslieferung», #104 (Konzept) mit #105–#113; Updates #127–#129; Verschlüsselung #136–#137.
- Deploy-Plattform: Meilenstein «Plattform – Deploy», #114–#120; Updates #130–#132; Demoplattform #134;
  Grundeinrichtung und Deploy-Agent #135; Schlüsseldienst #138; Angebot «Dediziert» #140.
- Abo-Plattform: Meilenstein «Plattform – Abo», #121–#126; Updates #133.
- Idee Ende-zu-Ende für einen abgegrenzten Bereich: #139 (Sammlung «Funktionen»).

Alle mit `triage`. Die Issues der beiden Plattformen ziehen in die eigenen Repositories um, sobald es diese gibt.

## Offene Fragen

1. Freigabe neuer Kunden: immer von Hand oder automatisch für Schul-Domains?
2. Betreiber und Vertragspartner für die ADV (Person, Schule Bülach oder eigene Organisation)?
3. Kostenmodell (gratis, Pauschale pro Schule, nach Grösse) – bestimmt, ob die Abo-Plattform eine Abrechnung braucht.
4. Eigene Repositories für Abo- und Deploy-Plattform anlegen (bis dahin Meilensteine in diesem Repository).
5. Hostinger (Frankfurt) für echte Schuldaten: Abklärung mit der Datenschutzbeauftragten des Kantons Zürich bzw.
   dem Volksschulamt (Vertrag, Verschlüsselung, Folgenabschätzung). Bis dahin nur Demoplattform.

Entschieden am 06.11.2026: Wartungsfenster 23:00–05:00; Wellen 24 Stunden (Patch) bzw. 3 Tage (Minor); Major nach
Bestätigung, spätestens nach 90 Tagen; Kanal «Beta» bekommt alle Versionen so rasch wie möglich. Hosting: VPS Lite
bei Infomaniak, zuerst als Demoplattform (Abschnitt «Hosting»).
