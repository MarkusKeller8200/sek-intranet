# Changelog – Modul Zeiterfassung

Versionen des Moduls nach SemVer (module.json → «version»).

## [Unveröffentlicht]

### Neu

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
