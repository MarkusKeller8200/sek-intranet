# Personenarten und Datenherkunft

Stand: Version 0.16.0.

## Personenart

Jede Person hat eine **Personenart** (`person_type`, Liste in `config/intranet.php` → `person_types`, erweiterbar):
Schüler:in, Eltern / Erziehungsberechtigte, Lehrperson, Schulleitung, Mitarbeiter:in, Schulbehörde,
Externe Fachperson.

- Die Personenart beschreibt, *wer* jemand ist. Die **Rolle** bestimmt weiterhin, *was* jemand im Intranet darf.
- Anmelden können sich nur Personalarten (`login_person_types`) und nur ohne Rolle «Kein Zugang».
  Schüler:innen und Eltern sind Kontakte ohne Konto.
- Schüler:innen und Eltern (`protected_person_types`) sehen nur Personen mit dem Recht `view-students`
  (Administration, Schulleitung, Verwaltung, Fachstelle ICT, Lehrperson) – in Tabelle, Detail, Export und API.
- Weitere Felder: **Klasse** (`class_name`; bei Lehrpersonen = Klasse, deren Klassenlehrperson sie sind) und
  **iPad-/Geräte-Nr.** (`device_inventory`). E-Mail ist nicht mehr Pflicht (z. B. Eltern), bleibt aber eindeutig.
- Menü Personen & Organisation → **Schüler:innen** (`/schueler`): dieselbe Tabelle wie «Personen», nur Personenart
  Schüler:in, eigene Spaltenauswahl und gespeicherte Ansichten.

## Datenherkunft und Synchronisation

Sammlung `person_links`, je Eintrag: Person, Richtung (**Quelle** oder **Ziel**), System (`sync_systems`),
**ID/PK im System**, Art der ID, **letzter Abgleich**, Bemerkung.

- **Quelle**: aus welchem System die Person stammt (z. B. Escola mit Escola-ID). Importe suchen zuerst über
  (System, ID) und erst dann über die E-Mail. So entsteht kein Duplikat, auch wenn sich die E-Mail ändert.
  Eine Quelle braucht immer eine ID.
- **Ziel**: in welche Systeme die Person exportiert wird (z. B. Entra ID mit objectId, Jamf mit Geräte-ID).
  Wurde die Person nach dem letzten Abgleich geändert (oder noch nie abgeglichen), steht der Eintrag auf
  **«Geändert – Abgleich nötig»**. Die Tabelle zeigt in der Spalte «Abgleich offen» die Anzahl.
- Regeln: je Person, System und Richtung ein Eintrag; eine ID gehört je System und Richtung zu einer Person.
- Pflege: Personen → Detail → «Datenherkunft & Synchronisation» (nur Administration): hinzufügen, ändern,
  löschen, «Abgeglichen» nach einem manuellen Export. Plugins halten Quelle und Zeitpunkt beim Import selbst fest
  (Escola: Escola-ID, Entra: objectId).
- `intranet:upgrade` übernimmt vorhandene Escola-IDs als Quelle.

## Geräteausgabe

«Schüler:innen importieren» → **Aus dem Intranet laden**: Jahrgang wählen, Klassen ankreuzen, laden. Es werden
nur Schüler:innen der gewählten Klassen übertragen (mit Klassenlehrperson und Geräte-Nr.). Passwörter und PINs
entstehen weiterhin nur im Browser und werden nicht gespeichert.

## Datenschutz

Schüler- und Elterndaten sind besonders schützenswert (Volksschule, IDG Kanton Zürich). Im Prototyp nur
**erfundene** Schüler:innen und Eltern (`@edu.sekbuelach.example`). Echte Daten erst auf dem Server in der Schweiz
(Infomaniak, ADR-005) und nach Freigabe durch die Datenschutzbeauftragte.
