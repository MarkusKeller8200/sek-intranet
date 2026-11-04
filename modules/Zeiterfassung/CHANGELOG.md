# Changelog – Modul Zeiterfassung

Versionen des Moduls nach SemVer (module.json → «version»).

## [Unveröffentlicht]

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
