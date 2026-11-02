# Modul Zeiterfassung

Pilotmodul (#8) und Muster für weitere Module. Schlüssel `zeiterfassung`, DB-Schema `zeit`, Datenklasse vertraulich.

- **Erfassen** (`/zeiterfassung`, Recht `use-zeiterfassung`, alle Mitarbeitenden): Einträge je Tag mit Beginn/Ende/Pause
  oder Stunden, Art (Arbeit/Abwesenheit), Kategorie (Lehrpersonen nach Berufsauftrag, übrige nach Bereich),
  Projekt/Einsatz, Notiz. Summen je Kategorie, Monatsblatt als PDF (Drucken → Als PDF speichern).
- **Einreichen:** sperrt den Monat und benachrichtigt die Schulleitung.
- **Visum** (`/zeiterfassung/visum`, Recht `visum-zeiterfassung`, Schulleitung/Administration): Übersicht je Monat,
  Monatsblatt je Person, visieren oder mit Bemerkung zurückgeben (Person wird benachrichtigt).
- **Export Lohnbuchhaltung:** CSV (Excel Schweiz, Semikolon, UTF-8) mit Stunden je Person und Kategorie – standardmässig
  nur visierte Monate; zusätzlich alle Einträge als Detail-CSV.
- Abwesenheiten bewusst ohne Grund oder Diagnose (IDG). Kategorien in `Kategorien.php`, Rechenregeln in `Zeitkonto.php`.

Daten: `<data_path>/modules/zeiterfassung/zeit_eintraege.json`, `zeit_monate.json`.
