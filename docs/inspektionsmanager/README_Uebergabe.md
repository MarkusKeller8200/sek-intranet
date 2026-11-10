# Inspektionsmanager – technische Übergabe an Markus
Stand: 10.11.2026 | Fachstelle Infrastruktur, Sekundarschule Bülach

## Ziel und Auftrag
Bestehendes HTML-Tool über einen dauerhaften Link aus SharePoint zugänglich machen. Berechtigte Mitarbeitende sollen es ortsunabhängig auf PC, Tablet und Smartphone verwenden und dieselben zentral gespeicherten Inspektionen, Mutationen und Mängel sehen. Anmeldung erforderlich; kein öffentlich zugänglicher Datenbestand. Bestehende Bedienung und Checklisten möglichst weiterverwenden.

Die CAFM-/FacilitySoft-API ist noch nicht geklärt. Die Rückmeldung steht aus. Die erste Onlineversion soll unabhängig davon nutzbar werden; eine spätere Anbindung wird separat geplant.

## Paketinhalt
- Inspektionsmanager_V39.html: vollständiger aktueller Quellcode, unverändert übernommen; HTML, CSS, JavaScript, Logo/Icons und Daten sind eingebettet. Kein Build-Schritt und keine separat eingebundenen externen JavaScript-/CSS-Bibliotheken erkannt.
- Checklisten_Katalog.json: zusätzlich aus dem HTML exportierter Checklisten- und Gerätekatalog. Massgebend für den aktuellen Programmablauf ist weiterhin die eingebettete Version.
- CAFM_Anlagenbestand.json: zusätzlich exportierte 732 eingebettete CAFM-Anlageneinträge. Statische Bestandskopie, keine Live-Schnittstelle und kein automatisch aktueller Datenbestand.
- Mail_an_Martin.txt: Begleitmail.
- SHA256.txt: Prüfsumme der HTML-Datei.

## Tatsächlicher Ist-Zustand
Das Tool ist eine eigenständige Browseranwendung, kein bereits eingerichteter Mehrbenutzerdienst. Es gibt im Paket keinen Servercode, keine zentrale Datenbank, keine Zugangsdaten, keine eingerichtete Anmeldung und keine produktive CAFM-API.

Die Speicherung erfolgt lokal im jeweiligen Browser: IndexedDB-Datenbank „SekBuelachInspektionsmanager“, Object Store „app-data“; ergänzend localStorage-Schlüssel „sekbuelach_inspections_v1“. Ein Kopieren der HTML-Datei nach SharePoint oder das Öffnen derselben URL synchronisiert diese Daten NICHT zwischen Personen/Geräten. Neue URL/Origin und neue Browser können eigene, zunächst leere Datenbestände haben.

Berichte werden aktuell über Browserdruck (window.print) ausgegeben. Das ist kein automatischer zentraler PDF-Upload. Lokale OneDrive-/SharePoint-Ordnerpfade sind keine Server-Speicheranbindung und unterscheiden sich je Benutzer.

JSON-Export/-Import ist vorhanden. Bereits ausgefüllte Kontrollen, Fotos, Unterschriften und Mutationen aus den Benutzerbrowsern sind NICHT automatisch in diesem Paket enthalten. Vor Umstellung pro Browser einen Export erstellen. Importzusammenführung, Dubletten und IDs kontrolliert behandeln; vorhandener Import ersetzt lokale Daten und ist kein Mehrbenutzerabgleich.

## Umsetzungsweg – durch IT zu konkretisieren
1. Hosting und Einbindung festlegen: SharePoint als Einstiegspunkt. Ausführung beispielsweise über eine von IT verwaltete SharePoint-Erweiterung (SPFx) oder über eine separat gehostete HTTPS-Webanwendung mit Link aus SharePoint. Welche Variante passt, hängt von Tenant, Berechtigungen und IT-Betrieb ab. Das reine Hochladen der HTML-Datei in eine Dokumentbibliothek ist nicht die vollständige Umsetzung.
2. Anmeldung/Berechtigungen: organisationsinterne Microsoft-Anmeldung; Zugriff nur für freigegebene Mitarbeitende/Gruppen. Bedien- und Administrationsrechte definieren. Zugriff aus externen Netzen gemäss IT-Richtlinien ermöglichen.
3. Zentrale Speicherung implementieren: geeignete SharePoint-Listen und Dokumentbibliotheken oder eine von IT betriebene Datenbank mit API evaluieren. Inspektionen und Mutationen als separate Datensätze mit stabiler ID, Änderungsstand, Bearbeiter und Zeitstempeln speichern. Fotos, Unterschriften und PDFs als geschützte Dateien ablegen. Ein gemeinsam überschriebenes JSON-Gesamtdokument genügt bei paralleler Nutzung nicht.
4. Datenzugriff umbauen: load(), save(), importData(), exportData(), Initialisierung und IndexedDB-Speichererweiterung gemeinsam prüfen. Mehrere spätere Skripte überschreiben Funktionen; blosses Ersetzen der ersten save()-Definition reicht nicht. Zentralen Lade-/Speicheradapter und sichtbare Speicherung/Fehlerzustände einführen; lokale Ablage höchstens als klar definierter Cache.
5. Gleichzeitige Bearbeitung: Konflikterkennung pro Datensatz, z. B. über Versionsstände/ETags. Änderungen anderer Personen beim Laden/Aktualisieren sichtbar machen. Bei Netzfehlern keinen erfolgreichen zentralen Speichervorgang anzeigen. Offlinefunktion ist ein eigener Entscheid, aktuell kein gemeinsamer Offlineabgleich.
6. Berichte: PDF-Ausgabe und zentrale Ablage in der bestehenden SharePoint-Struktur umsetzen. Gewünschte Zielordner: „Abgeschlossene Inspektionen“ und „Neue Mutation“ innerhalb „01_Infra-HD geteilt/02_Betriebsmittel_Maschinen_Geraete/03_Checklisten“. Bibliothek/Site-URL und tatsächliche Ordner durch IT verifizieren; nicht den personenbezogenen C:\Users-Pfad als gemeinsame Adresse übernehmen. Bestehende Dateinamenslogik möglichst erhalten, Datum JJJJMMTT, Standortkürzel z. B. H_A.
7. Migration: Browserexporte sichern und kontrolliert in den zentralen Bestand übernehmen. Abgeschlossene Berichte inkl. Checklisten-Snapshot, Versionsbezug und Unterschrift erhalten. Kein automatisches Überschreiben historischer Berichte durch neue Kataloge.
8. Betrieb: Verantwortlichkeit, Berechtigungen, Sicherung/Wiederherstellung, Versionierung und Releaseweg festlegen. Keine Zugangsdaten oder Anwendungsgeheimnisse im HTML hinterlegen.

## Gewünschter funktionaler Erhalt
Inspektionen und Sammelkontrollen, Geräte-/Anlagenauswahl nach Standort, einmalige Gruppenbewertung mit Zuordnung der Mängel, Fotos/Kamera, Unterschrift, Abschluss/Schreibschutz und Wiederöffnung, PDF-Berichte, Mutationsformulare, Mängelübersicht, Papierkorb mit Wiederherstellung/endgültigem Löschen sowie Export/Import. Oberflächenstandard: Arial, normale Texte 11 pt; mobil bedienbar. Betriebliche Prüfumfang-Abgrenzung und bestehende Checklisten unverändert übernehmen.

## Abnahme vor Freigabe
- Zwei berechtigte Personen auf verschiedenen Geräten sehen denselben neuen/aktualisierten Bericht.
- Gleichzeitiges Bearbeiten führt zu erkennbarem Konflikt statt unbemerktem Datenverlust.
- PC sowie iPhone/Android: Auswahl, Eingabe, Foto/Upload, Unterschrift und Bericht funktionieren; Kamera/Berechtigungen in der Zielumgebung testen.
- Bericht/PDF und Mutation liegen am vorgesehenen zentralen Ort und sind für berechtigte Personen zugänglich.
- Unberechtigte haben keinen Zugriff; Löschen/Wiederherstellen und Schreibschutz funktionieren zentral.
- Netzfehler werden sichtbar; Sicherung und Wiederherstellung sind geprüft.

## Erwartete Rückmeldung von Markus
Vorgeschlagene technische Lösung, benötigte Zugänge/Lizenzen und IT-Freigaben, Aufwand und Termin für eine erste nutzbare Onlineversion. Die zentrale Speicherung und Anmeldung sind noch zu implementieren; dieses Paket ist die vollständige vorhandene Frontend-Ausgangsbasis, kein fertig eingerichteter Serverbetrieb.
