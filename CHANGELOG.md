# Changelog – SekBülach Intranet

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Solange die Version mit 0 beginnt, ist das
Intranet ein Prototyp (nur erfundene Testdaten). Die Versionsnummer steht nur in `VERSION`; neue Einträge kommen
laufend unter **Unveröffentlicht**. Ab 0.32.0 entsteht eine Version nur bei einem freigegebenen Release
(`php tools/release.php X.Y.Z`, siehe `docs/KONZEPT-VERSIONIERUNG.md`); dazwischen ist der ausgerollte Stand ein
«Developer Release».

## [Unveröffentlicht]

### Für Mitarbeitende

- Die Zeiterfassung markiert Tage ohne Buchung und fehlende Ein- oder Austrittsbuchungen mit einem Hinweis.
- Der Monatsrapport (PDF) ist jetzt gleich aufgebaut wie der Rapport aus ClickTime.
- Wer mit ClickTime arbeitet, schliesst den Monat in ClickTime ab und bestätigt dies im Intranet mit «In ClickTime
  abgeschlossen».
- Kompensation zählt wie in ClickTime nicht als Arbeitszeit.
- [visum-zeiterfassung] Die Visum-Übersicht zeigt je Person die Tage mit Hinweisen; an fehlende Abschlüsse lässt sich
  erinnern.

### Neu

- **ClickTime: Scopes je Endpunkt (#196):** Jeder Endpunkt im Plugin (0.8) trägt seinen Scope wie in ClickTime
  (`<RESSOURCE>_READ`/`_WRITE`); Hinweis zur Einrichtung nennt nur die Scopes, die das Intranet braucht.
- **Inspektionsmanager – Konzept (#175):** Konzept mit Lösungsvorschlag, Datenmodell, Rechten, Etappen und
  Abnahmekriterien (`docs/KONZEPT-INSPEKTIONSMANAGER.md`); Ausgangspaket V39 unverändert in
  `docs/inspektionsmanager/`; Menüpunkt «Räume & Geräte → Inspektionsmanager» als Platzhalter.
- **Sicherung herunterladen und wiederherstellen (#174):** Einstellungen → Sicherung (nur Administration). Ganze
  Installation (alle Sammlungen inkl. Module, Dokumente und Dateien; ohne Schlüssel des Servers, Logs, Sitzungen)
  als ZIP oder – mit Passwort – verschlüsselt (Argon2id, XChaCha20-Poly1305) inkl. Plugin-Zugangsdaten. Sicherungen
  auf dem Server anlegen, herunterladen, löschen. Wiederherstellen mit Prüfung (Prüfsummen, Version, erlaubte
  Pfade), Vorschau je Sammlung und Bestätigung; der aktuelle Stand wird vorher automatisch auf dem Server gesichert.
  Upload-Grenze im Container auf 512 MB erhöht.
- **Betrieb: Infomaniak-Server (#170–#172):** Einrichtungsskript für Ubuntu 26.04 (Docker, Benutzer «deploy», SSH
  nur mit Schlüssel, ufw, fail2ban, automatische Sicherheitsupdates, Traefik), mehrere Instanzen pro Server
  (`INTRANET_INSTANZ`), Deploy zusätzlich nach `intranet-dev.sekbuelach.ch` (Environment «dev») und tägliche
  verschlüsselte Sicherung mit restic nach Swiss Backup samt Wiederherstellungstest. Hostinger (privater
  Entwicklungsserver) läuft neu unter `intranet.markussx.ch`; der Hostname wird bei jedem Deploy nachgeführt. Anleitung: `docs/SERVER-INFOMANIAK.md`.
- **Betrieb: Live auf Infomaniak (#173):** Workflow «deploy-live» für `intranet.sekbuelach.ch` – nur von Hand mit
  Version und Bestätigung, nie automatisch, Sicherung vor dem Deploy und Abbruch, wenn echte Daten mit
  Demo-Login laufen würden.
- **ClickTime: Mitarbeitende anlegen (#31):** Aktion «Mitarbeitende anlegen» mit Vorschau und Dubletten-Prüfung
  (Datenherkunft, gleiche Personalnummer, Benutzername, Badge-Nummer oder E-Mail → nur verbinden); nach dem Anlegen
  wird die Personalnummer aus der Antwort in der Datenherkunft gespeichert. Echte Aufrufe erst nach Freischaltung der
  Schreibrechte durch ClickTime. Arbeitsplan und Berechtigungen folgen, sobald ClickTime die Felder dokumentiert.
- **ClickTime: Sollzeit gezielt (#163):** Sollzeit pro Tag mit Filter `entry_type::IN::0` (ein Aufruf je Tag für
  alle Personen), Rückfall ohne Filter, wenn die Nummer fehlt. Ferien zählen wie in ClickTime im Ist.
- **Zeiterfassung: Kompensation nicht angerechnet (#169):** wie ClickTime zählt Kompensation im Ist nicht
  (Tages-Ist 0:00, Differenz −Soll) – in Tabelle, Monatsrapport und Prüfhinweisen.
- **Zeiterfassung: Monatsrapport wie ClickTime (#168):** PDF vom Server im Aufbau des ClickTime-Monatsrapports
  (Buchungen mit Pause, Tagestotal mit Soll/Differenz, Total, Buchungsarten, Saldi, Seitenzahl). Neu im Kern:
  `SimplePdf::fill`, `dotted`, `footerRight`.
- **Zeiterfassung: Prüfhinweise je Tag (#167):** keine Buchung (mit Sollzeit aus ClickTime), Ein- bzw.
  Austrittsbuchung fehlt (Schild mit Ausrufezeichen), Ist mehr als 20 % vom Soll entfernt («Arbeitszeit prüfen»).
- **Zeiterfassung: Monatsabschluss mit ClickTime (#164), Monatsblatt-Ablage (#34), Vollständigkeit und Erinnerung
  (#33):** siehe `modules/Zeiterfassung/CHANGELOG.md` (Modul 0.8.0). Plugin ClickTime 0.7.

## [0.38.0] – 2026-11-09

### Für Mitarbeitende

- Die Suche im Glossar findet jetzt auch Aliase und Suchbegriffe.
- [edit-glossary] Neu im Glossar: die Seite «EduKSX-Glossary» zur Vorbereitung der Anbindung an das gemeinsame
  Glossar.

### Neu

- **Glossar: Sync von der öffentlichen Plattform vorbereitet (#161):** Neue Seite «EduKSX-Glossary» im Glossar (Recht
  «Glossar pflegen») mit Stand, **Probelauf** (zeigt neu, geändert, ausgeblendet und Listen, speichert nichts) und
  «Mit EduKSX-Glossary synchronisieren». Der Zeitplan synchronisiert nur noch mit der neuen Einstellung
  «Automatisch im Zeitplan synchronisieren» (Vorgabe aus). Checkliste Go-live in `docs/KONZEPT-GLOSSAR.md`.
- **Glossar: Vorschläge ans EduKSX-Glossary (#162):** «Dem EduKSX-Glossary vorschlagen» bzw. «Korrektur
  vorschlagen» am Eintrag (Formular, Bestätigung, Begründung). Gesendet werden nur Fachdaten über
  `POST /api/v1/proposals` (Servicekonto, Scope «propose»); der Vorschlag geht in die Moderation. Nummer und Stand am
  Eintrag, Stand wird beim Sync nachgefragt; nach Annahme verbindet der Sync den lokalen Begriff mit dem
  veröffentlichten (lokale Daten übernommen, lokaler Eintrag ausgeblendet, keine Dublette).
- **Plugin EduKSX-Glossary: API nachgeführt (#160):** Stand Glossary 0.9.0 / OpenAPI 0.4.0-preview – neu Index,
  Token prüfen (whoami), Vorschläge einreichen und abfragen; Organisation (`/org/terms`) als geplant. Optionales
  Token des Servicekontos (verschlüsselt); «Verbindung testen» prüft mit Token Servicekonto und Scopes. Neu im Kern:
  `bearer` mit `optional: true`. Plugin 0.3.

### Geändert

- **Glossar: Kategorien und Herkunft mit UUID (#159):** Die Listeneinträge des Startinhalts hatten Kurz-IDs wie
  «beh» oder «vsa». `intranet:upgrade` stellt sie einmalig auf UUIDs um (alter Schlüssel im Feld `schluessel`, Label
  und Farbe fest gespeichert) und führt die Verweise der Einträge und die Zuordnung des Syncs nach; Zeitstempel
  bleiben, nichts wird gelöscht. Neue Installationen und vom EduKSX-Glossary angelegte Listeneinträge erhalten direkt
  eine UUID. Plugin EduKSX-Glossary 0.2.

## [0.37.0] – 2026-11-07

### Neu

- **Glossar: gemeinsame Felder nach DEC-020 (#155):** Alternative Schreibweisen aus dem EduKSX-Glossary in
  Einzelansicht, Suche und Tooltip; die Suche der Tabelle findet auch Aliase und Suchbegriffe. Mehrdeutigkeit
  übernommener Begriffe wie im Glossary (nur gemeinsame Werte, gleiche normalisierte Bezeichnung zählt einmal).
  Entscheid zum Excel-Format 2: Abgleich nur über die API, Import bleibt möglich (Format in `KONZEPT-GLOSSAR.md`).
- **Glossar: Sync mit dem EduKSX-Glossary (#156):** Neues Plugin «EduKSX-Glossary» übernimmt die veröffentlichten
  Begriffe über die API v1 (Vollstand, danach Change-Feed; Veröffentlichung, Rückzug, Zusammenführung), mit gleichen
  IDs. Übernommene Einträge sind gekennzeichnet, gemeinsame Felder gesperrt; eigene Formen, «Intern genutzt in»,
  Notiz und Suchbegriffe bleiben lokal. Nie löschen, wiederholbar. Zeitplan `intranet:glossar-sync` (Abstand
  einstellbar), Stand und Fehler auf der Plugin-Karte. Quellenangabe (CC BY 4.0) in Ansicht und Exporten.
- **Glossar: vollständiger Export (#157):** Für die Übernahme ins EduKSX-Glossary exportiert «Excel vollständig
  (alle Daten)» alle Einträge (auch inaktive) mit «Intern genutzt in (Schlüssel)», «Doppelte geprüft», «Erstellt»
  und «Geändert»; der bisherige Export und Import bleiben gleich. Kategorien und Herkunft haben je einen eigenen
  Excel-Export und -Import mit Vorschau (nie löschen). Rohdaten als JSON mit `php artisan intranet:glossar-export`.
- **Zeiterfassung: ClickTime führt (#154):** Ist ClickTime für eine Person aktiv (Plugin eingeschaltet, ClickTime-Nr.
  in der Datenherkunft), sind «Eintrag erfassen» und Bearbeiten deaktiviert, auch auf dem Server; Übernehmen aus
  ClickTime, Löschen übernommener Einträge und Einreichen bleiben. Modul Zeiterfassung 0.7.0.
- **Darstellung: Menü überlagernd (#153):** Optional unter Einstellungen → Darstellung (Aus, Bei schmalem Fenster
  unter 1280 px, Immer; gilt für diesen Browser). Das Seitenmenü öffnet dann über dem Inhalt statt daneben – mehr
  Platz, wenn eine Fachanwendung im Browser geteilt neben dem Intranet läuft. Hinweis auf der Seite der Anwendung.
- **Konzept Design-Werkzeuge (#152):** `docs/KONZEPT-DESIGN.md` – VisBug und Entwicklertools zum Messen und Melden,
  Penpot (Test in der Cloud, danach Hostinger KVM 2 in Frankfurt mit Microsoft-Login), Design-Tokens als einzige
  Quelle für `app.css` und Themes, Developer-Werkzeuge im Intranet.
- **Einbindungen: Fenstergrösse und Trennlinie (#151):** «Neues Browser-Fenster» mit Breite und Höhe in % des
  sichtbaren Anzeigebereichs (Vorgabe 80 %), «gesplittetes Tab» mit Trennlinie in % von links (Vorgabe 50 %, für die
  Aufteilung in zwei Fenster). Felder «Adresse» und «Öffnen über» auf gleicher Höhe.
- **Einbindungen: «Öffnen über» (#150):** Neue Bezeichnungen (iFrame – eingebettet, iFrame – neuer Tab, Neuer
  Browser-Tab, Neues Browser-Fenster, Browser-Erweiterung neuer bzw. gesplittetes Tab) und neue Option «Neues
  Browser-Fenster» (eigenes Fenster statt Tab).
- **Personen: direkt ins CMI-Dossier (#149):** Neues System «CMI» in der Datenherkunft. Mit der Dossier-ID zeigt
  die Person den Knopf «In CMI öffnen» (Personal- bzw. Schülerdossier je Personenart, neuer Tab); es werden keine
  Daten aus CMI gelesen. Vorlagen unter `record_links`, Bereiche über die `.env` übersteuerbar.
- **Sicherheit: Schutz-Kopfzeilen (#147):** Jede Antwort sendet `X-Frame-Options: SAMEORIGIN` und eine
  Content-Security-Policy mit `frame-ancestors 'self'` (kein Einbetten des Intranets durch fremde Seiten),
  `frame-src` nur für Fachanwendungen und Einbindungen im Rahmen (inkl. Einstellungen), `object-src 'none'` und
  `base-uri 'self'`; dazu `nosniff`, `Referrer-Policy`, `Permissions-Policy` und HSTS bei HTTPS.
- **Browser-Erweiterung: «Nebeneinander» in der geteilten Ansicht (#146):** Kennt der Browser die Tabs Split View
  API (Chrome ab 155), öffnet «Nebeneinander» die Fachanwendung als geteilte Ansicht neben dem Intranet im selben
  Fenster (ein schon offener Tab wird dazugeholt). Sonst wie bisher zwei Fenster je halber Bildschirm.
- **Fachanwendungen: SL-Planer (#145):** Neuer Menüpunkt «SL-Planer» (Anmeldeseite, öffnet in neuem Tab;
  der SL-Planer verbietet das Einbetten mit `X-Frame-Options: SAMEORIGIN`). Adresse wie bei CMI/Escola über die
  Server-`.env` übersteuerbar (`INTRANET_APP_SLPLANER_URL`).

### Für Mitarbeitende

- Neu im Menü Fachanwendungen: der SL-Planer.
- Fachanwendungen lassen sich neben dem Intranet öffnen – im selben Browserfenster geteilt oder in einem eigenen
  Fenster.
- Unter Einstellungen → Darstellung kann das Seitenmenü über dem Inhalt aufgehen – mehr Platz, wenn eine Anwendung
  daneben läuft.
- Wer ClickTime nutzt, erfasst die Zeiten nur noch dort; im Intranet werden sie übernommen.
- [edit-glossary] Das Glossar lässt sich vollständig exportieren, auch Kategorien und Herkunft einzeln.

## [0.36.0] – 2026-11-06

### Für Mitarbeitende

- In der Zeiterfassung wählt man neu eine Buchungsart, z. B. Arbeitszeit, Homeoffice, Ferien, Krankheit oder Unfall.
  Bisherige Einträge erhalten sie automatisch.
- Die Zeiterfassung zeigt pro Tag eine Totalzeile mit Soll, Ist und Differenz in Stunden:Minuten. Wochenende,
  Feiertage, Ferien, Kompensation und Krankheit sind farbig. Aufklappen zeigt die einzelnen Buchungen.
- Selbst erfasste Einträge in der Zeiterfassung lassen sich neu bearbeiten.
- Wer ClickTime nutzt, übernimmt einen Monat mit einem Knopf in die eigene Zeiterfassung, samt Sollzeit.
- Diese Mitteilung zeigt neu die Versionsnummer und führt mit einem Link zu allen Änderungen auf GitHub.

### Neu

- **Mitteilung «Neu im Intranet» mit Link auf GitHub (#144):** Die Mitteilung einer Version zeigt die
  Versionsnummer und den Knopf «Release-Notes auf GitHub» (`INTRANET_GITHUB_REPO`, Release `v<Version>`).
- **Konzept Betrieb (#104):** `docs/KONZEPT-BETRIEB.md` – komplett getrennte Mandanten (eine Installation pro
  Kunde), Cloud und On-Premise mit demselben Image, automatische Einrichtung über Abo- und Deploy-Plattform
  (eigenständige Projekte). Issues in den Meilensteinen «Betrieb & Auslieferung», «Plattform – Deploy», «Plattform –
  Abo», alle `triage`. Ergänzt um automatische Updates (SemVer-Regeln, Wellen, Wartungsfenster,
  Schnappschuss und Zurückschalten, On-Premise-Update-Dienst; #127–#133).
  Ergänzt um Hosting (Infomaniak VPS Lite, Hostinger), Datenschutz Standort Deutschland, Verschlüsselung, Angebote
  «Geteilt»/«Dediziert», Datenspeicher (SQLite als schlanke Datenbank) und Sicherung (#134–#143).
- **Zeiterfassung: neue Tabelle und Einträge bearbeiten (#102):** Spalten Tag (MO–SO), Datum (TT.MM.), Buchungsart,
  Kategorie, Zeit «HH:MM – HH:MM (−Pause min)», Soll, Ist (HH:MM), Delta, Herkunft (Intranet/ClickTime), Notizen.
  Alle Tage des Monats mit Tagestotal (leicht eingefärbt; Samstag, Sonntag und Feiertage Kanton Zürich in anderer
  Farbe, `Feiertage`), einzeln oder «Alle aufklappen» zu den Buchungen in kleiner Schrift; Tabelle nutzt die ganze
  Breite; Soll und Delta mit #103. Einträge aus dem Intranet bearbeiten (Stift, Formular oben) und löschen, Einträge aus Fremdsystemen nur löschen (`PUT zeiterfassung/eintraege/{id}`). Modul 0.5.0.
- **Zeiterfassung: Soll, Delta und Farben je Abwesenheit (#103):** Beim Übernehmen aus ClickTime wird die Sollzeit
  pro Tag mitgeholt (eine Abfrage je Tag) und in `zeit_soll` gespeichert (Modul 0.6.0, Plugin 0.6). Tageszeilen zeigen
  Soll, Ist und Delta in HH:MM (negativ rot), unten Soll und Delta des Monats. Farben: Ferien pastellgrün,
  Kompensation hellviolett, Krankheit/Unfall hellrot (neue Tokens `vacation-soft`, `compensation-soft`, `sick-soft`,
  Theme-Gruppe «Zeiterfassung»). Aktion «Sollzeit pro Tag» zusätzlich in HH:MM.
- **ClickTime: Sollzeit pro Tag (#102):** Aktion «Sollzeit pro Tag» fragt die Kumulative je Tag ab (`valid_from` =
  `valid_to`, Buchungsart 0 = Sollzeit) und listet Tag, Datum, Soll und Feiertag; Kontrolle Summe der Tage gegen die
  Sollzeit des Monats. Grundlage für Soll und Delta in der Zeiterfassung (Plugin 0.5).
- **ClickTime: Monat in die Zeiterfassung übernehmen (#101):** Aktion «In Zeiterfassung übernehmen» (Vorschau oder
  Übernehmen) und Knopf «Aus ClickTime übernehmen» in der eigenen Zeiterfassung (Administration; Mitarbeitende per
  Plugin-Einstellung). ClickTime-Nr. über die Datenherkunft der Person. Herkunft je Eintrag verhindert Duplikate,
  Änderungen und Löschungen in ClickTime werden nachgeführt, von Hand erfasste Einträge bleiben. Unbekannte
  Buchungsarten werden als «Name (clicktime)» angelegt, Arbeitszeit-Kategorie «Name (clicktime)», Pause in den
  längsten Arbeitsblock, Notizen übernommen. Modul Zeiterfassung 0.4.0, Plugin 0.4.
- **Zeiterfassung: Buchungsart je Eintrag (#99):** Buchungsarten wie in ClickTime (Arbeitszeit, Homeoffice, Ferien,
  Krankheit, Unfall, Kompensation, Weiterbildung, Militär/Zivilschutz, bezahlte/unbezahlte Absenz), unter Visum →
  «Buchungsarten» pflegbar. Summen, Monatsblatt und Export nach Buchungsart. Modul Zeiterfassung 0.3.0.
- **ClickTime: Abgleich mit der eigenen Zeiterfassung (#100):** Mapping-Tabelle «Buchungsart ClickTime =
  Buchungsart Zeiterfassung» in den Plugin-Einstellungen (Vorgabe nach den Buchungsarten der Schule, Pause, «nicht
  übernehmen»), Aktion «Buchungsarten zuordnen» (Vorschlag aus ClickTime, Übernehmen ergänzt die Tabelle) und «Mit
  Zeiterfassung vergleichen» (je Person und Tag: gleich, Abweichung, nur ClickTime, nur Zeiterfassung). Plugin 0.3.
- **Plugin ClickTime (#29):** Neues Plugin für die Zeiterfassung ClickTime (API V3): Anmeldung mit client_id und
  client_secret (Token 1 Stunde), Kundennummer, alle 51 Endpunkte im API-Tester mit Beispielwerten (aus der
  OpenAPI-Beschreibung), Demo-Modus mit erfundenen Daten, «Verbindung testen» über die Abteilungen. Auf einem Server
  ausserhalb der Schweiz nur Demo-Modus; mit «Endtest» dort nur Aufrufe ohne Personendaten.
- **ClickTime: Mitarbeitende und Abgleich (#30):** Aktionen «Abteilungen anzeigen», «Mitarbeitende anzeigen» (mit
  Abteilung, Vorgesetzten, Intranet-Person), «Mitarbeitende abgleichen» (Vorschau zugeordnet / neu zuordnen / ohne
  Treffer; Übernehmen speichert nur die Datenherkunft «ClickTime», legt nichts an und löscht nichts) und «Person
  nachschlagen» (Nummer, Abteilung, Vorgesetzte, Anstellungen, Rollen).
- **ClickTime: Salden und Monatsblätter (#32):** Aktionen «Salden holen» (Stichtag, eine Person oder alle) und
  «Monatsblatt holen» (Buchungen, Summen, Salden, Arbeitstage ohne Buchung ohne Feiertage); optional je Person als
  PDF in «Dokumente & Links» (neue Kategorie «Monatsblätter», nur Verwaltung sichtbar).
- **ClickTime: Änderungen nach dem Monatsabschluss (#97):** «Monat abschliessen» hält den Stand der Buchungen fest,
  «Änderungen seit Abschluss» zeigt neu / geändert (alt → neu) / gelöscht und schickt auf Wunsch einen Hinweis an
  Verwaltung und Vorgesetzte. Im Demo-Modus simuliert ClickTime nach dem Abschluss eine Korrektur.
- **ClickTime: Kumulative im echten Aufbau:** Die Antwort liefert je Person `cumulative_values` mit Name der
  Buchungsart; die Nummer ist meist leer (nur gesetzt, wenn in ClickTime eine Lohnart-Nummer hinterlegt ist). Summen
  im Monatsblatt werden jetzt danach gelesen (Plugin 0.2.1). Buchungen ebenso: Buchungsart über den Namen
  (`entry_type_name`) und die Kategorie (`entry_type_type`), Pausenkorrekturen mit negativen Stunden, gelöschte
  Buchungen werden übersprungen (Plugin 0.2.2).
- **Personendaten für Tests freischalten:** Auf einem Server ausserhalb der Schweiz kann die Administration die
  gesperrten Personendaten-Endpunkte eines Plugins mit dem Admin-PIN für 60 Minuten freischalten (Karte auf der
  Plugin-Seite, «Wieder sperren»). Freischalten und Sperren stehen im Änderungsverlauf; der PIN wird nur geprüft.
- **Plugins allgemein:** Mehrfache Abfrageparameter (`params[].repeat`, z. B. `filters=…&filters=…`) und Sperre für
  Personendaten ausserhalb der Schweiz (`endpoints[].personal_data`, auch im Endtest).

## [0.35.0] – 2026-11-05

### Für Mitarbeitende

- [manage-signatures] Verwaltung → Unterschriften: beliebiges PDF zur elektronischen Unterschrift senden
  (Unterzeichnende nacheinander oder gleichzeitig); das unterschriebene PDF lässt sich danach dort herunterladen.
- [visum-zeiterfassung] Visierte Monatsrapporte lassen sich elektronisch unterschreiben (DeepSign): Das
  unterschriebene PDF liegt danach automatisch in der Zeiterfassung der Person.
- [manage-signatures] Verwaltung → Unterschriften: offene und abgeschlossene Unterschriften, erinnern und zurückziehen.
- [edit-glossary] Glossar: Einträge lassen sich deaktivieren (Spalte «Aktiv»); inaktive sieht nur, wer das Glossar
  pflegt, und sie werden im Text nicht mehr markiert.

### Neu

- **Glossar: Zurück und Brotkrumen (#96):** Einzelansicht mit «Zum Glossar», Bearbeiten mit «Zurück zum Eintrag»;
  die Brotkrume «Glossar» führt zur Hauptseite (Glossar › Eintrag › Bearbeiten).
- **DeepSign: Signaturprozess auf der DeepSign-Seite (#95):** Aktion lädt ein PDF als Entwurf hoch und öffnet die
  DeepSign-Seite (`document-access-url`), um dort Unterzeichnende und Felder festzulegen und zu starten; auch für
  bestehende Dokumente (ID).
- **Glossar-Kacheln konfigurierbar (#90):** Die Spaltenauswahl bestimmt, welche Angaben die Kachel zeigt – alle
  Angaben möglich, Wichtiges oben, Weniger wichtiges unten; ausgeblendete Angaben brauchen keinen Platz. Sortieren
  und Gruppieren über die bestehenden Knöpfe der Tabelle (gleicher Zustand für Tabelle und Kacheln); die eigene
  Leiste über den Kacheln entfällt.
- **Glossar: Spalte «Aktiv» (#91):** Häkchen in Tabelle (umschalten, filtern), Formular, Einzelansicht und Excel
  (ja/nein). Inaktive Einträge nur für Pflegende sichtbar (abgeblendet, «inaktiv»), nicht im Export für andere und
  nicht mehr in der Begriffsmarkierung.
- **Plugins: Knöpfe auf Titelhöhe (#92):** Ein-/Ausschalten, Verbindung testen und API-Ansicht stehen rechts auf
  der Höhe des Titels; die Beschreibung darunter (`PageHeader inline`).
- **DeepSign: Webhook (#93):** Einstellungen «Webhook verwenden» und «Öffentliche Adresse des Intranets»; beim
  Versenden geht der Webhook nur mit, wenn er eingeschaltet ist (oder `INTRANET_SIGNATURE_WEBHOOK`). Aktion
  «Webhook prüfen» zeigt Zustand und Adresse und schickt eine signierte Probe-Meldung an das Intranet.
- **DeepSign: Dokumente ablegen (#94):** Aktion lädt unterschriebene Dokumente herunter und legt sie in «Dokumente &
  Links» ab (neue Kategorie «Unterschriebene Dokumente», Standard: nur Verwaltung sichtbar). Bereits abgelegte
  werden erkannt. Dokumente & Links kennen dafür eingeschränkte Sichtbarkeit (Feld `ability`, Schloss-Symbol) –
  auch in der REST-API und auf der Startseite.

- **Glossar als Kacheln (#88):** Umschalter Tabelle | Kacheln (je Browser gemerkt). Kacheln einspaltig und kompakt:
  Abkürzung, Bezeichnung, Labels, gekürzte Kurzbeschreibung (ganz im Tooltip), Status, Link zur Quelle. Suche,
  Filter, Sortierung, Gruppierung, Auswahl, Löschen und Export gelten auch für die Kacheln. Allgemein in der
  Datentabelle (`renderCard`), für andere Tabellen nutzbar.
- **Menüs zuklappen (#87):** Unter dem Logo links neben dem Vermerk zwei kleine Symbole: alle Hauptmenüs zu- bzw.
  aufklappen und «Nur ein Menü offen» (Umschalter; Aufklappen eines Menüs klappt dann alle anderen zu). Gilt für
  diesen Browser.
- **Dokument zur Unterschrift senden (#86):** Dialog auf Verwaltung → Unterschriften und Aktion im Plugin DeepSign:
  PDF wählen, Unterzeichnende in Reihenfolge oder gleichzeitig, zur Kenntnis an, Nachricht – startet den
  Signaturprozess; das unterschriebene PDF wird abgelegt und ist herunterladbar. Plugin-Seite mit Link zur
  Swagger-Doku (`service.swagger`).
- **DeepSign: alle Endpunkte (#85):** Alle 53 Endpunkte der DeepSign-API im Plugin und im API-Tester, je mit
  Beispielwerten, Feldern und Demo-Antwort (Umschläge, Gruppen und Reihenfolge, Anhänge, Hash- und Datei-Signatur,
  Siegel, Unterschrift als Bild); Aktionen «Siegel anzeigen» und «Identifikation anzeigen». Versand an die echte API
  angepasst (`signeeType`, `fileSignatureFields`, Position ab links unten, `visualSignatureRequirement`, Zurückziehen
  mit `sendMail`, Download über `files[].fileUrl`). Der API-Tester zeigt Binärdaten als Grösse und erlaubt Listen
  als Body.
- **DeepSign-Endtest (#84):** Schalter «Endtest» erlaubt echte Aufrufe an DeepSign auch auf dem Testserver (sonst
  Demo-Modus erzwungen); der API-Tester lädt bei Endpunkten mit Datei ein Dokument hoch (multipart).
- **Unterschriften (#38–#43):** Kern-Schnittstelle «Unterschriften» (Aufträge, Status je Person, Ereignisse an das
  Modul) mit DeepSign als Anbieter: PDF mit Unterzeichnenden in Reihenfolge senden und starten, Webhook mit Signatur
  und Abfrage alle 15 Minuten (Zeitplan im Container), unterschriebenes PDF sofort abholen und beim Modul ablegen,
  Warnung, wenn es liegen bleibt. Seite «Unterschriften» (Verwaltung) mit Erinnern, Zurückziehen, Löschen und
  «In DeepSign öffnen». Zeiterfassung: visierten Monatsrapport als PDF zur Unterschrift senden. Der Demo-Modus
  spielt den ganzen Ablauf durch. Einfacher PDF-Erzeuger im Kern (`SimplePdf`).

- **Plugin DeepSign (#37):** Elektronische Unterschrift mit DeepSign (DeepCloud): Anmeldung mit Service-Benutzer und
  Partner-Client (verschlüsselt), Verbindung testen, Dokumente und Signaturstufen anzeigen, API-Tester mit
  18 Endpunkten. **Demo-Modus** mit simulierten Antworten, solange kein Zugang vorhanden ist (auf dem Testserver
  immer). Neue Kern-Schnittstelle `DemoResponder` für Plugins mit Demo-Modus.
- **DeepSign: Service-Benutzer erstellen (#83):** Aktion öffnet DeepAdmin, um den Service-Benutzer für das Intranet
  freizugeben (Benutzername und Passwort werden einmal angezeigt); Einstellung «Umgebung» Test/Produktion und
  «API-Adresse»; «Verbindung testen» zeigt den Service-Benutzer und seine Firmen. Plugin-Aktionen können Links
  zurückgeben, Einstellungen ohne Wert nehmen die Vorgabe aus `plugin.json`.
- **API-Tester mit Beispielwerten (#82):** Parameter und Body sind vorausgefüllt; «Beispiel einsetzen», «Felder
  löschen», IDs aus der letzten Antwort für Pfadparameter, «Mögliche Werte» mit «Einfügen», «Formatieren» und
  laufender JSON-Prüfung – in allen Plugins und im Tester der eigenen REST-API. Die API-Ansicht (Scalar) zeigt
  Beispiele und Felder des Bodys. Beispielwerte in ePost, Inventory360 und LMVZ ergänzt.

## [0.34.0] – 2026-11-04

### Für Mitarbeitende

- Anmeldung neu mit Rolle und 8-stelligem PIN statt gemeinsamem Passwort; neue Rollen Schüler:in, Eltern und
  Externe (vorerst nur Kommunikation und Wissen & Ablage).
- [manage-users] Einstellungen → Zugang (PIN): PIN je Rolle setzen, ändern oder entfernen.
- [view-ablaeufe] Abläufe Verwaltung (Menü Verwaltung): Verzeichnis wichtiger Abläufe als Checklisten, zuerst Eintritt,
  Austritt und Mutationen von Mitarbeitenden – mit Verantwortung, Kommunikation, Dokumenten und Bemerkungen, druckbar.
- [manage-users] Einstellungen → Module: Rechte der Module je Rolle ein- und ausschalten.
- [view-glossary] Glossar (Menü Wissen): Abkürzungen und Begriffe der Volksschule – offiziell und wie wir sie
  verwenden, nach Kategorie gruppiert, mit Suche und Export als Excel, PDF (Format, Umfang und Gruppierung
  wählbar) oder Markdown. Abkürzungen wie «VSA» sind im Intranet fein gepunktet unterstrichen: darüberfahren oder
  antippen zeigt die Erklärung.

### Neu

- **Baustein «Begriff» (#60):** Abkürzungen aus dem Glossar sind fein gepunktet unterstrichen; beim Darüberfahren,
  Fokussieren oder Antippen erscheinen Abkürzung, Bezeichnung und Kurzbeschreibung, ein Klick öffnet den Eintrag.
  Mehrdeutige zeigen alle Bedeutungen. Farbe der Punkte im Farbkonzept (Theme), «Begriffe hervorheben» unter
  Darstellung abschaltbar; «Intern genutzt in» im Glossar als Links; neuer GUI-Baustein «Begriff».
- **Begriffe im Freitext (#61):** In Mitteilungen, Terminen, Abläufen und Markdown-Notizen werden Abkürzungen aus dem
  Glossar beim Anzeigen erkannt und ausgezeichnet; der gespeicherte Text bleibt unverändert.
- **Kurzform bei zu wenig Platz (#81):** Spaltentitel in Tabellen wechseln auf eine Abkürzung, wenn die Spalte
  zu schmal ist (z. B. «Bezeichnung offiziell» → «Bez. offiz.»), in allen Sprachen; volle Bezeichnung als Tooltip.
- **Vermerk unter dem Logo (#80):** «© 2026 developed by Markus Keller – in Eigeninitiative für die SekBülach» (sehr kleine Schrift) in der
  Seitenleiste und auf der Anmeldeseite.
- **Glossar-PDF und Datenstrukturen (#79):** Labels im PDF in den Farben der Tabelle; «Datenstrukturen» zeigt neu
  auch die Sprachkataloge im Code (Sprachkonzept) mit Anzahl Einträgen und Übersetzungsstand.
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

- Plugins: gespeicherte Zugangsdaten (Token, Passwörter, Secrets) erscheinen im Formular als «**\*\*\*\***»
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
