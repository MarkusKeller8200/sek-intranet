# Modul Zeiterfassung

Pilotmodul (#8) und Muster für weitere Module. Schlüssel `zeiterfassung`, DB-Schema `zeit`, Datenklasse vertraulich.

- **Erfassen** (`/zeiterfassung`, Recht `use-zeiterfassung`, alle Mitarbeitenden): Einträge je Tag mit Beginn/Ende/Pause
  oder Stunden, Art (Arbeit/Abwesenheit), Kategorie (Lehrpersonen nach Berufsauftrag, übrige nach Bereich),
  Projekt/Einsatz, Notiz. Summen je Kategorie, Monatsblatt als PDF (Drucken → Als PDF speichern).
- **Einreichen:** prüft die Vollständigkeit (Arbeitstage ohne Eintrag → Rückfrage), sperrt den Monat und
  benachrichtigt die Schulleitung.
- **Mit ClickTime (#164):** Abschluss und Genehmigung in ClickTime; im Intranet «In ClickTime abgeschlossen» bestätigen
  (Person, im Notfall Schulleitung stellvertretend) → letzter Abgleich, Status `abgeschlossen`, Monatsblatt abgelegt.
  Nachbuchungen im Visum mit «Mit ClickTime vergleichen» prüfen; zum Übernehmen den Monat zurückgeben.
- **Monatsblatt (#34):** beim Visum bzw. Abschluss als PDF in der Ablage des Moduls
  (`zeiterfassung/monatsblatt/<person>/<monat>.pdf`), nur für die Person und mit Recht `visum-zeiterfassung`.
  Aufbewahrungsfrist: noch festzulegen.
- **Soll und Ist (#103, #163):** Sollzeit pro Tag aus ClickTime (Arbeitszeitmodell, Pensum, Feiertage). Ferien und
  Abwesenheiten werden nicht vom Soll abgezogen, sondern zählen im Ist wie geleistete Zeit – wie der Saldo in ClickTime
  (Ferientag: Soll = Ist, Differenz 0). **Ausnahme Kompensation (#169):** zählt im Ist nicht (Kompensationstag: Ist
  0:00, Differenz −Soll), da sie Überzeit abbaut.
- **Visum** (`/zeiterfassung/visum`, Recht `visum-zeiterfassung`, Schulleitung/Administration): Übersicht je Monat
  mit Arbeitstagen ohne Eintrag und «Erinnern» (#33), Monatsblatt je Person, visieren oder mit Bemerkung zurückgeben
  (Person wird benachrichtigt).
- **Export Lohnbuchhaltung:** CSV (Excel Schweiz, Semikolon, UTF-8) mit Stunden je Person und Kategorie – standardmässig
  nur visierte Monate; zusätzlich alle Einträge als Detail-CSV.
- Abwesenheiten bewusst ohne Grund oder Diagnose (IDG). Kategorien in `Kategorien.php`, Rechenregeln in `Zeitkonto.php`.

Daten: `<data_path>/modules/zeiterfassung/zeit_eintraege.json`, `zeit_monate.json`.
