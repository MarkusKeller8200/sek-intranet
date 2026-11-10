# Konzept: Modul Inspektionsmanager

Stand: 10.11.2026 · Status: **Konzept, noch nicht freigegeben** (Issues mit `triage`) · Meilenstein «Inspektionsmanager»

Auftrag der Fachstelle Infrastruktur (Übergabe vom 10.11.2026, `docs/inspektionsmanager/README_Uebergabe.md`): Das
bestehende HTML-Tool «Inspektionsmanager» soll über einen dauerhaften Link aus SharePoint für berechtigte
Mitarbeitende auf PC, Tablet und Smartphone nutzbar werden – mit Anmeldung und **zentral gespeicherten**
Inspektionen, Mutationen und Mängeln. Dieses Dokument beschreibt den Lösungsvorschlag und die **Abnahme**.

## Ausgangslage (geprüft)

| Teil                                      | Inhalt                                                                                                                          |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `Inspektionsmanager_V39.html`             | eigenständige Browser-Anwendung, rund 1,2 MB, 92 Funktionen, Daten und Logo eingebettet                                         |
| Prüfsumme                                 | SHA-256 `51417412…7162` – stimmt mit `SHA256.txt` überein (geprüft 10.11.2026)                                                  |
| `Checklisten_Katalog.json` (Katalog 23.6) | 148 Kontrollarten (144 Sammel-, 4 Einzelkontrollen), 4–10 Prüfpunkte je Art, 71 Geräte, 100 Grundlagen (VUV, EKAS, Suva, VKF …) |
| `CAFM_Anlagenbestand.json`                | 732 Anlagen (HIBI 362, METTMI 354, BWS 14, Schulverwaltung 2) – statische Kopie, keine Schnittstelle                            |
| Standorte                                 | HIBI (Trakte A–D, P, Infra), METTMI (O, S, TH, W, Z), BWS, Schulverwaltung                                                      |

Das Paket liegt unverändert in `docs/inspektionsmanager/` (nicht im Container-Image, nicht formatiert).

**Ist-Zustand:** Speicherung nur im Browser (IndexedDB «SekBuelachInspektionsmanager» und localStorage
«sekbuelach_inspections_v1»); keine Anmeldung, keine zentrale Ablage, kein Mehrbenutzerbetrieb. Fotos und
Unterschriften liegen als Bilddaten in den Datensätzen. Berichte über den Druckdialog des Browsers. Export/Import
als JSON ersetzt den ganzen Bestand. Mehrere spätere Skriptteile überschreiben frühere Funktionen (z. B. Editor,
Sammelkontrolle, Speichern) – die Logik ist darum über die Datei verteilt.

**Fachlicher Umfang (zu erhalten):** Inspektionen als Einzel- und Sammelkontrolle, Geräte-/Anlagenauswahl nach
Standort (mit CAFM-Bestand), einmalige Gruppenbewertung mit Zuordnung der Mängel, Spezialprüfungen (Notlicht:
Funktionstest/Betriebsdauer; Wasserstellen; Wartung/Fachprüfung mit Status; Folgemassnahmen), Fotos/Kamera,
Unterschrift, Abschluss mit Schreibschutz und Wiederöffnen, Berichte (Dateiname `JJJJMMTT_Standortkürzel_Art_Objekt`),
Mutationen (Neuanschaffung, Ersatz, Verschiebung, Abgang/Entsorgung, Änderung) mit Bericht, Mängelübersicht,
Papierkorb mit Wiederherstellen und endgültigem Löschen, Export/Import, Grundlagen je Prüfpunkt, Pflege der
Kontrollarten.

## Lösungsvorschlag: Modul im Intranet, Einstieg aus SharePoint

| Variante                                    | Bewertung                                                                                                                                                                                                                                |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| HTML in SharePoint-Bibliothek hochladen     | **nicht ausreichend** – jede Person und jedes Gerät hat eigene Daten (laut Übergabe ausgeschlossen)                                                                                                                                      |
| SharePoint-Erweiterung (SPFx) + Listen      | möglich, aber: App-Katalog, Tenant-Freigaben, Listen-Grenzen für Fotos/Versionen, eigener Build- und Releaseweg, Know-how bei der IT nötig                                                                                               |
| Eigene Webanwendung neben dem Intranet      | zweiter Betrieb (Anmeldung, Sicherung, Updates, Server) für ein einzelnes Werkzeug                                                                                                                                                       |
| **Modul im SekBülach Intranet (Vorschlag)** | zentrale Speicherung, Anmeldung, Rechte, Änderungsverlauf, Sicherung (#172, #174), Server in der Schweiz, Handy als App (PWA) und Releaseweg sind schon vorhanden; Link aus SharePoint auf `https://intranet.sekbuelach.ch/inspektionen` |

- **Modul** `modules/Inspektionen` (eigener Speicher, eigene Rechte, eigene Tests; `docs/KONZEPT-MODULE.md`).
  Menüpunkt «Räume & Geräte → Inspektionsmanager» ist als Platzhalter angelegt.
- **Oberfläche neu mit den Bausteinen des Intranets** (GUI-Prototypen), Bedienung und Abläufe wie V39. Die
  HTML-Datei wird nicht eingebettet: Ihre Speicher- und Ladefunktionen sind mehrfach überschrieben, und ein Umbau auf
  zentrale Speicherung bliebe schwer prüfbar. Übernommen werden **Kataloge, Prüfpunkte, Texte, Regeln und
  Dateinamen** – maschinell aus den JSON-Dateien, damit nichts von Hand abgeschrieben wird.
- **Oberflächenstandard:** Im Intranet gilt dessen Design (Schrift, Grössen, Kontrast AA). Arial 11 pt gilt für die
  **PDF-Berichte** (offene Frage 2).
- **SharePoint:** Link (Kachel bzw. Navigation) in der Site der Fachstelle; Berichte zusätzlich als PDF in die
  bestehende Ordnerstruktur (Etappe 3, braucht eine IT-Freigabe).

## Datenmodell (Modul-Speicher, Präfix `inspektion_`)

| Sammlung                  | Inhalt                                                                                                                                                                                                                                                                                           |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `inspektion_kataloge`     | Katalogversionen (Kontrollarten, Prüfpunkte, Details, Grundlagen, Geräte); importiert aus V39, versioniert                                                                                                                                                                                       |
| `inspektion_anlagen`      | Anlagenbestand (CAFM-Kopie, später Plugin), Standort, Trakt, Geschoss, Nummer                                                                                                                                                                                                                    |
| `inspektion_inspektionen` | Kopf (Kontrollart, Standort, Trakt, Geschoss, Raum, Datum, prüfende Person), Objekte mit Antworten (Ergebnis ok/Mangel/n. z., Text, Massnahme, Foto-Verweis), Sonderfelder, **Katalog-Snapshot**, Status (Entwurf/abgeschlossen/wiedereröffnet), `version`, `bearbeitet_von/_am`, `geloescht_am` |
| `inspektion_mutationen`   | Art, Anlage, alter/neuer Standort, Menge, Status, Bemerkungen, `version`, Bearbeitung, Papierkorb                                                                                                                                                                                                |
| Dateien (Disk «local»)    | Fotos und Unterschriften als Bilddateien, Berichte als PDF; nur über Rechte abrufbar, nie öffentlich                                                                                                                                                                                             |

- Jede Inspektion und jede Mutation ist ein **eigener Datensatz mit stabiler ID** (UUID v7); kein gemeinsam
  überschriebenes Gesamtdokument.
- **Abgeschlossene Berichte** behalten ihren Katalog-Snapshot: Ein neuer Katalog ändert keine alten Berichte.
- Änderungen laufen über den Änderungsverlauf des Intranets (wer, wann, was).

## Rechte

| Recht                  | Für                                                                                            |
| ---------------------- | ---------------------------------------------------------------------------------------------- |
| `inspektion-lesen`     | Berichte, Mängel und Mutationen ansehen                                                        |
| `inspektion-erfassen`  | Inspektionen und Mutationen erfassen, Fotos, Unterschrift, abschliessen                        |
| `inspektion-verwalten` | Wiederöffnen, Papierkorb endgültig leeren, Kataloge und Anlagen pflegen, Import, Einstellungen |

Zuweisung je Rolle über die Modulverwaltung; später über Gruppen aus Entra ID (Anmeldung mit Microsoft, siehe
Etappe 1). Unberechtigte sehen den Menüpunkt nicht und erhalten 403.

## Gleichzeitige Bearbeitung und Netzfehler

- Jeder Datensatz hat eine **Version**. Speichern schickt die gelesene Version mit; ist sie veraltet, lehnt der Server
  ab (409) und die Oberfläche zeigt «Inzwischen von … geändert» mit beiden Ständen – kein stilles Überschreiben.
- Speichern ist **sichtbar**: «Gespeichert 10:42», «Wird gespeichert …», «Nicht gespeichert – keine Verbindung»
  mit erneutem Versuch. Ohne Bestätigung des Servers gilt nichts als gespeichert.
- Offline-Erfassung mit späterem Abgleich ist **nicht** Teil der ersten Version (eigener Entscheid, offene Frage 4).

## Fotos, Unterschrift, Berichte

- Fotos direkt aus der Kamera (Handy/Tablet) oder als Datei; im Browser verkleinert (z. B. 1600 px), auf dem Server als
  Datei mit Verweis gespeichert. Hinweis in der Oberfläche: keine Personen fotografieren.
- Unterschrift auf dem Bildschirm (Finger/Stift/Maus), als Bild gespeichert; nach dem Abschluss unveränderlich.
- **Bericht als PDF vom Server** (wie der Monatsrapport der Zeiterfassung), Dateiname wie V39
  (`JJJJMMTT_H_A_Leiternkontrolle_…`). Beim Abschluss wird der Bericht abgelegt; Wiederöffnen markiert ihn als
  Entwurf, ein erneuter Abschluss legt eine neue Fassung ab (alte bleiben).
- **Ablage in SharePoint** («Abgeschlossene Inspektionen», «Neue Mutation» unter
  `01_Infra-HD geteilt/02_Betriebsmittel_Maschinen_Geraete/03_Checklisten`) über ein Plugin «Microsoft 365»
  (Graph, Recht `Sites.Selected` nur für diese Site). Site-URL und Ordner verifiziert die IT.

## Migration aus den Browsern

1. Jede Person exportiert in V39 ihre Daten (Administration → Export), **bevor** umgestellt wird.
2. Import im Modul mit **Vorschau**: neue, bereits vorhandene (gleiche ID) und ähnliche Datensätze (Datum, Standort,
   Art, Objekt); nichts wird ohne Bestätigung überschrieben. Fotos und Unterschriften werden zu Dateien.
3. Abgeschlossene Berichte bleiben mit Snapshot, Abschlussdatum und Unterschrift erhalten.
4. Nach der Abnahme: V39 nicht mehr verwenden (Hinweis an die Nutzenden).

## Datenschutz und Betrieb

- Inspektionen enthalten Namen, Unterschriften und Fotos: **echte Daten nur auf dem Schweizer Server**
  (`intranet.sekbuelach.ch`), auf Dev und Hostinger nur erfundene Testdaten.
- Sicherung: tägliche Server-Sicherung (#172) und Sicherung in der Oberfläche (#174) enthalten den Modul-Speicher und
  die Dateien.
- Releases: Dev bei jedem Push, Produktion nur auf Anweisung (deploy-live).
- CAFM/FacilitySoft: vorerst statische Kopie mit Stand-Datum; Anbindung als Plugin, sobald die API geklärt ist.

## Etappen

| Etappe                     | Inhalt                                                                                                                                                                                            |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1 – Grundlage              | Modul, Rechte, Katalog- und Anlagen-Import, Standorte; Anmeldung mit Microsoft (Entra ID) als Voraussetzung für echte Nutzende                                                                    |
| 2 – Erste nutzbare Version | Inspektionen (Einzel/Sammel, Gruppenbewertung, Sonderprüfungen), Fotos, Unterschrift, Abschluss/Wiederöffnen, Konflikterkennung, PDF-Bericht im Intranet, Mängelübersicht, Mutationen, Papierkorb |
| 3 – Integration            | SharePoint-Ablage der PDFs, Migration der Browser-Exporte, Link aus SharePoint, Pilot mit zwei Personen                                                                                           |
| 4 – Später                 | CAFM-Plugin, Offline-Erfassung (falls entschieden), Auswertungen                                                                                                                                  |

## Abnahme

Abnahme durch die Fachstelle Infrastruktur auf `intranet-dev.sekbuelach.ch` (Testdaten), danach Pilot auf der
Produktion. Jeder Punkt wird im Abnahmeprotokoll mit Datum, Gerät, Person und Ergebnis festgehalten.

**Testumgebung:** zwei Testkonten mit Recht `inspektion-erfassen`, eines mit `inspektion-verwalten`, eines ohne
Recht; Geräte: Windows-PC (Edge/Chrome), iPhone (Safari), Android (Chrome), iPad.

| Nr. | Kriterium (aus der Übergabe)                                                         | Prüfung                                                                                                                              | Erwartet                                                                                                                 |
| --- | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------ |
| A1  | Zwei Personen auf verschiedenen Geräten sehen denselben neuen/aktualisierten Bericht | Person A erfasst auf dem PC eine Leiternkontrolle (HIBI, Trakt A) und schliesst ab; Person B öffnet sie auf dem iPhone               | B sieht denselben Stand inkl. Fotos und Unterschrift                                                                     |
| A2  | Gleichzeitiges Bearbeiten führt zu erkennbarem Konflikt                              | A und B öffnen dieselbe Inspektion; A speichert eine Änderung, danach B eine andere                                                  | B erhält «Inzwischen geändert» mit beiden Ständen; nichts geht verloren                                                  |
| A3  | PC, iPhone, Android: Auswahl, Eingabe, Foto, Unterschrift, Bericht                   | Je Gerät eine Sammelkontrolle mit Mangel, Foto aus der Kamera und Unterschrift; Bericht öffnen                                       | Alles bedienbar; Kamera-Berechtigung wird abgefragt; PDF korrekt                                                         |
| A4  | Bericht/PDF und Mutation am vorgesehenen zentralen Ort                               | Abschluss einer Inspektion und einer Mutation                                                                                        | PDF im Intranet und (Etappe 3) im SharePoint-Ordner, Dateiname wie V39                                                   |
| A5  | Unberechtigte haben keinen Zugriff                                                   | Konto ohne Recht ruft Menü, Liste, Bericht-URL und Foto-URL direkt auf                                                               | Kein Menüpunkt, 403 bzw. Anmeldung; keine Daten sichtbar                                                                 |
| A6  | Löschen/Wiederherstellen und Schreibschutz zentral                                   | Inspektion in den Papierkorb, auf anderem Gerät wiederherstellen; abgeschlossene Inspektion bearbeiten; Wiederöffnen ohne/mit Recht  | Papierkorb für alle gleich; abgeschlossen = nur lesbar; Wiederöffnen nur mit `inspektion-verwalten`, im Verlauf vermerkt |
| A7  | Netzfehler werden sichtbar                                                           | Während der Erfassung Netz trennen (Flugmodus) und speichern                                                                         | «Nicht gespeichert – keine Verbindung», erneuter Versuch nach Wiederverbindung                                           |
| A8  | Sicherung und Wiederherstellung geprüft                                              | Sicherung herunterladen (#174), Inspektion löschen, Sicherung wiederherstellen                                                       | Inspektion inkl. Fotos und Bericht wieder vorhanden                                                                      |
| A9  | Checklisten unverändert übernommen                                                   | Stichprobe: 10 Kontrollarten (darunter Leitern, Brandschutz wöchentlich/jährlich, Elektroverteilungen, Notlicht) mit V39 vergleichen | Prüfpunkte, Details und Grundlagen identisch; Katalogversion 23.6 angezeigt                                              |
| A10 | Abgeschlossene Berichte behalten ihren Stand                                         | Katalog ändern (neue Version), alten Bericht öffnen                                                                                  | Alter Bericht zeigt den Katalog-Snapshot                                                                                 |
| A11 | Migration                                                                            | Export aus V39 (Testdaten) importieren, zweimal                                                                                      | Vorschau richtig; beim zweiten Mal keine Dubletten                                                                       |
| A12 | Mutationen und Mängelübersicht                                                       | Je eine Mutation jeder Art; Mängel filtern nach Standort/Status                                                                      | Bericht je Art, Mängel vollständig mit Massnahme und Status                                                              |

**Freigabe:** alle Punkte erfüllt, Abweichungen als Issue erfasst und entschieden; Freigabe der Produktion durch die
Projektleitung (Markus) und die Fachstelle Infrastruktur.

## Benötigte Zugänge und Freigaben

1. **Microsoft Entra ID:** App-Registrierung für die Anmeldung am Intranet (OIDC), Gruppen für die Rechte – Freigabe
   durch die IT der Schule.
2. **SharePoint-Ablage (Etappe 3):** App-Registrierung mit `Sites.Selected`, Freigabe nur für die Site der Fachstelle
   Infrastruktur; Site-URL und Ordner durch die IT.
3. **Link in SharePoint:** Bearbeitungsrecht auf der Site der Fachstelle.
4. **Auftragsverarbeitungsvertrag** mit Infomaniak (vor echten Daten, siehe #173).
5. **CAFM/FacilitySoft:** API-Dokumentation und Zugang (Rückmeldung ausstehend).

Keine Lizenzen für das Intranet selbst; bestehende Microsoft-365-Lizenzen genügen.

## Issues (Meilenstein «Inspektionsmanager»)

- Konzept und Abnahme: #175 · Anmeldung mit Microsoft (Voraussetzung): #176
- Etappe 1: Modul #177, Prüfkatalog #178, Anlagen und Standorte #179
- Etappe 2: Erfassen #180, Sonderprüfungen #181, Fotos/Unterschrift #182, Abschluss #183, Konflikte/Speicherstatus
  #184, PDF-Berichte #185, Mutationen #186, Mängel #187, Papierkorb #188
- Etappe 3: Migration #189, SharePoint-Ablage #190, Einstieg aus SharePoint/mobil #191, Abnahme und Pilot #193
- Etappe 4: CAFM-Plugin #192

Alle ausser #175 mit `triage` – umgesetzt wird erst nach Freigabe.

## Offene Fragen

1. Wer darf abschliessen, wer wiederöffnen (eine Person der Fachstelle oder alle Erfassenden)?
2. Oberflächenstandard Arial 11 pt: nur für Berichte oder auch für die Bildschirmansicht?
3. Wer pflegt Kontrollarten und Prüfpunkte im Betrieb (Rolle «verwalten»), und braucht es eine Freigabe neuer
   Katalogversionen (vier Augen)?
4. Offline-Erfassung nötig (z. B. Keller ohne Netz)? Wenn ja, eigene Etappe mit Abgleich und Konfliktlösung.
5. Aufbewahrungsfrist für Berichte, Fotos und Mutationen; Löschen nach Frist?
6. Sollen Mängel als Aufgaben weiterlaufen (Zuweisung, Erledigt-Meldung) oder genügt die Übersicht?
7. SharePoint-Ablage: zusätzlich zur Ablage im Intranet oder nur dort?

## Rückmeldung an die Fachstelle (Entwurf)

> Vorgeschlagen wird ein Modul im SekBülach Intranet (Server in der Schweiz) mit Link aus SharePoint. Damit sind
> Anmeldung, zentrale Speicherung, Rechte, Sicherung und Releaseweg bereits vorhanden; die Checklisten werden
> unverändert aus V39 übernommen. Benötigt werden die Freigabe der Anmeldung mit Microsoft (Entra ID) und – für die
> PDF-Ablage – eine eng begrenzte SharePoint-Berechtigung. Eine erste nutzbare Version (Etappe 2) wird nach Freigabe
> des Konzepts und der Anmeldung in mehreren Releases umgesetzt und auf der Testumgebung zur Abnahme bereitgestellt;
> den Termin legen wir nach den Antworten auf die offenen Fragen fest.
