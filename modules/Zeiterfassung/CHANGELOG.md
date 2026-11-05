# Changelog – Modul Zeiterfassung

Versionen des Moduls nach SemVer (module.json → «version»).

## [Unveröffentlicht]

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
