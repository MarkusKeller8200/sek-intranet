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

- Intranet: Meilenstein «Betrieb & Auslieferung», #104 (Konzept) mit #105–#113; Updates #127–#129.
- Deploy-Plattform: Meilenstein «Plattform – Deploy», #114–#120; Updates #130–#132.
- Abo-Plattform: Meilenstein «Plattform – Abo», #121–#126; Updates #133.

Alle mit `triage`. Die Issues der beiden Plattformen ziehen in die eigenen Repositories um, sobald es diese gibt.

## Offene Fragen

1. Hoster in der Schweiz mit API für Server, DNS und Speicher; zum Start ein Server mit Docker oder gleich
   Kubernetes?
2. Freigabe neuer Kunden: immer von Hand oder automatisch für Schul-Domains?
3. Betreiber und Vertragspartner für die ADV (Person, Schule Bülach oder eigene Organisation)?
4. Kostenmodell (gratis, Pauschale pro Schule, nach Grösse) – bestimmt, ob die Abo-Plattform eine Abrechnung braucht.
5. Eigene Repositories für Abo- und Deploy-Plattform anlegen (bis dahin Meilensteine in diesem Repository).

Entschieden am 06.11.2026: Wartungsfenster 23:00–05:00; Wellen 24 Stunden (Patch) bzw. 3 Tage (Minor); Major nach
Bestätigung, spätestens nach 90 Tagen; Kanal «Beta» bekommt alle Versionen so rasch wie möglich.
