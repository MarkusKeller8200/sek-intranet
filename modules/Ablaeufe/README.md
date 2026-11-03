# Modul Abläufe Verwaltung

Schlüssel `ablaeufe`, Datenbank-Schema `wf`, Rechte `view-ablaeufe` (ansehen), `edit-ablaeufe` (bearbeiten) und
`config-ablaeufe` (Einstellungen).
Regeln in `docs/KONZEPT-MODULE.md`, Planung im Meilenstein «Workflow» (#44).

- **Verzeichnis** (`/ablaeufe`): Abläufe als Checklisten-Vorlagen mit Phasen und Schritten. Personen mit
  «ansehen» sehen nur gültige Abläufe; mit «bearbeiten» auch Entwürfe und Archiv.
- **Schritte:** Text, Verantwortung, Kommunikation an, Dokumente, Bemerkung, Frist (Tage zum Stichtag, negativ =
  vorher), gilt für (Personengruppen), Gegenstück in einer anderen Phase (z. B. Login erstellen ↔ sperren).
- **Personengruppen** (`/ablaeufe/gruppen`): Auswahl für «gilt für».
- **Einstellungen** (`/ablaeufe/einstellungen`, `Listen.php`): Orte, Fachbereiche, Rollen, Zeitlich, Mitwirkung – je
  Abkürzung (eindeutig), Bezeichnung, Beschreibung, URL, Notiz; Startwerte beim ersten Zugriff.
- **Startinhalt** (`Startinhalt.php`): Gruppen und der Ablauf «Mitarbeitende» aus der Excel-Vorlage, angelegt beim
  ersten Zugriff (danach nie mehr, auch nicht nach dem Löschen).
- **Sehen je Rolle:** Einstellungen → Module → Rechte je Rolle.
- Später: Durchführung je Person (#49), Verknüpfung mit Plugins (#50).
