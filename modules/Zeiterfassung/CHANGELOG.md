# Changelog – Modul Zeiterfassung

Versionen des Moduls nach SemVer (module.json → «version»).

## [Unveröffentlicht]

### Neu

- **0.11.0 – Monatsrapport an Vorgesetzte (#208):** `Vorgesetzte` (Sammlung `zeit_vorgesetzte`, aus ClickTime oder von
  Hand), Route `POST zeiterfassung/rapport-unterschreiben` (Person zuerst, dann Vorgesetzte:r; Feld «Vorgesetzte:r» im
  PDF), Felder `unterschrift_vorgesetzte`, `dossier_status`, `dossier_at` in `zeit_monate`. Dokumente bei der Person
  über `PersonDocuments`; Kopie ins Dossier über `Dossiers`, im Visum «Ins Dossier senden».
- **0.10.0 – Monat wieder öffnen (#206):** Recht `oeffnen-zeiterfassung`, Route
  `POST zeiterfassung/visum/{person}/oeffnen` (Begründung Pflicht). Status «offen», Abschluss-Angaben zurückgesetzt,
  neue Felder `geoeffnet_at`/`geoeffnet_by` in `zeit_monate`, Ereignis `zeit.monat_geoeffnet`. Hinweis «Monat wieder
  geöffnet» in der eigenen Zeiterfassung.
- **0.9.0 – Prüfhinweise einstellbar (#204):** Seite «Zeiterfassung – Einstellungen» (Recht `config-zeiterfassung`,
  Admin und Schulleitung), gespeichert in `zeit_einstellungen` (Datensatz `pruefungen`, Klasse `Pruefregeln`).
  `Zeitkonto::pruefungen` nimmt die Regeln entgegen; neu «Prüfen – Tagesist über hh:mm» und «Prüfen – keine
  Mittagsbuchung». Bei ClickTime ist die Karte «Eintrag erfassen» eingeklappt, mit rotem Hinweis.
- **0.8.1 – Genehmigungsstatus aus ClickTime (#200):** Neues Feld `quelle_status` je Eintrag; aufgeklappte Buchungen
  zeigen den Status bei «Herkunft», die Tageszeile den Status der ClickTime-Buchungen mit Anzahl.
- **Kompensation nicht angerechnet (#169, 0.8.0):** Wie ClickTime zählt Kompensation im Ist nicht (Tages-Ist 0:00,
  Differenz −Soll); Ferien und übrige Abwesenheiten zählen weiter. `Zeitkonto::angerechnet`/`ist`, Summe `ist` für
  Soll/Delta; in Tabelle, Monatsrapport und Prüfhinweisen (an Kompensationstagen keine Warnung «Arbeitszeit prüfen»).
- **Monatsrapport wie ClickTime (#168, 0.8.0):** PDF auf dem Server im Aufbau des ClickTime-Monatsrapports – Tag,
  Von, Bis, Std., Soll, Diff., Typ (Farbfeld), Bemerkungen; Pause als eigene Zeile, Tagestotal mit Soll und
  Differenz, Montage grau, Arbeitstage ohne Buchung; Total, Stunden je Buchungsart, Saldi aus ClickTime (Vormonat,
  Ende Monat), Bestätigungen, «Seite n / m». Gilt für «Monatsrapport (PDF)» (neu vom Server statt Druckdialog),
  das abgelegte Monatsblatt und die Unterschrift (DeepSign).
- **Prüfhinweise je Tag (#167, 0.8.0):** `Zeitkonto::pruefungen` – Arbeitstag mit Sollzeit ohne Buchung («Prüfen –
  keine Buchung»), Beginn ohne Ende bzw. Ende ohne Beginn (Fehler mit Schild: «Prüfen – Austritts-/Eintrittsbuchung
  fehlt», auch an der Buchung), Ist mehr als 20 % vom Soll entfernt («Arbeitszeit prüfen»). In der Tabelle (eigene
  Seite und Visum) und als Anzahl je Person in der Visum-Übersicht.
- **Monatsabschluss mit ClickTime (#164, 0.8.0):** Für Personen mit ClickTime führt ClickTime Abschluss und
  Genehmigung. Die Person bestätigt «In ClickTime abgeschlossen» (im Notfall die Schulleitung stellvertretend,
  vermerkt); dann letzter Abgleich, Status `abgeschlossen` (gesperrt), Monatsblatt abgelegt, Schulleitung
  benachrichtigt. «Mit ClickTime vergleichen» meldet Nachbuchungen, ohne zu übernehmen; Einreichen gibt es mit
  ClickTime nicht. Export und Unterschrift auch für abgeschlossene Monate. Klasse `Monatsabschluss`.
- **Monatsblatt abgelegt (#34, 0.8.0):** Beim Visum bzw. Abschluss entsteht das Monatsblatt als PDF auf dem Server
  (Einträge, Summen, Soll/Ist/Differenz, Bestätigungen) in der Ablage des Moduls; abrufbar nur für die Person und mit
  Recht `visum-zeiterfassung` (`/zeiterfassung/monatsblatt/{person}/{monat}`).
- **Vollständigkeit und Erinnerung (#33, 0.8.0):** Arbeitstage ohne Eintrag (`Zeitkonto::fehlendeTage`, mit Soll bzw.
  Mo–Fr ohne Feiertage) vor dem Einreichen mit Rückfrage «Ja, alles eingetragen» / «Ich ergänze noch»; im Visum
  Anzahl je Person und «Erinnern» an alle offenen bzw. zurückgegebenen Monate (Benachrichtigung, `erinnert_at`).
- **ClickTime führt (#154, 0.7.0):** Mit ClickTime-Nr. und eingeschaltetem Plugin keine eigenen Einträge (Formular
  deaktiviert, Erfassen und Ändern auf dem Server abgewiesen).

## [0.6.0] – 2026-11-06 (Intranet 0.36.0)

### Neu

- **Tagestotale (#102, 0.5.0):** Tabelle mit Totalzeile pro Tag (aufklappbar), Wochentag, Datum TT.MM., Zeit
  HH:MM – HH:MM, Herkunft; Feiertage Kanton Zürich (`Feiertage`); Einträge aus dem Intranet bearbeiten.
- **Soll und Delta (#103, 0.6.0):** Sammlung `zeit_soll` (Soll pro Tag, `Sollzeiten`), beim Übernehmen aus
  ClickTime gefüllt; Tageszeilen eingefärbt nach Ferien, Kompensation, Krankheit/Unfall.

## [0.4.0]

### Neu

- **Aus ClickTime übernehmen (#101):** Knopf in der eigenen Zeiterfassung (mit Plugin ClickTime und ClickTime-Nr. in
  der Datenherkunft). Einträge erhalten die Herkunft (`quelle`, `quelle_id`); Kategorien aus ClickTime erscheinen als
  «Name (clicktime)».

## [0.3.0]

### Neu

- **Buchungsart je Eintrag (#99):** Arbeitszeit, Homeoffice, Ferien, Krankheit, Unfall, Kompensation, Weiterbildung,
  Militär/Zivilschutz, bezahlte und unbezahlte Absenz (Vorgabe wie in ClickTime). Die Buchungsart bestimmt Arbeit oder
  Abwesenheit; die Kategorie (Tätigkeit) gibt es nur noch für Arbeit. Liste unter Visum → «Buchungsarten» pflegbar
  (umbenennen, hinzufügen, deaktivieren). Ältere Einträge erhalten die Buchungsart aus Art und Kategorie. Summen,
  Monatsblatt (Druck und PDF zur Unterschrift) und Export nach Buchungsart.

## [0.2.1]

### Geändert

- Unterschriftsfelder werden ab der linken unteren Ecke angegeben, wie DeepSign es verlangt (#85).

## [0.2.0]

### Neu

- Visierte Monatsrapporte elektronisch unterschreiben (#43): Das Monatsblatt entsteht als PDF auf dem Server, geht
  über die Kern-Schnittstelle «Unterschriften» (DeepSign) an Person und Schulleitung (Reihenfolge wählbar, optional
  zur Kenntnis an die Personalabteilung). Das unterschriebene PDF wird automatisch abgelegt und ist für die Person
  und das Visum herunterladbar. Status im Visum und in der eigenen Zeiterfassung.

## [0.1.0]

### Neu

- Pilot: Arbeitszeit, Tätigkeiten/Kategorien, Projekte und Abwesenheiten pro Monat erfassen, Monat einreichen,
  Monatsvisum der Schulleitung (visieren/zurückgeben), Benachrichtigungen, Export Lohnbuchhaltung (CSV) und
  Monatsblatt als PDF (#8).
