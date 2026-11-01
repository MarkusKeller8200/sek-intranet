# Sicherheit

## Unterstützte Versionen

Das Intranet ist ein Prototyp (Version 0.x). Korrekturen gibt es nur für den aktuellen Stand auf `main`, der
automatisch auf https://intranet.sekbuelach.ch ausgerollt wird. Ältere Versionen werden nicht nachgebessert.

| Version              | Unterstützt        |
| -------------------- | ------------------ |
| aktueller Stand main | :white_check_mark: |
| ältere 0.x           | :x:                |

## Sicherheitslücke melden

Bitte **kein Issue** für Sicherheitslücken anlegen, auch nicht im privaten Repository – Issues sehen alle
Mitwirkenden, und Benachrichtigungen gehen per E-Mail hinaus.

1. Den Projektverantwortlichen (Eigentümer dieses Repositorys) direkt per E-Mail oder Teams-Nachricht
   kontaktieren. Private Sicherheitsmeldungen über GitHub («Report a vulnerability») stehen nur bei öffentlichen
   Repositorys zur Verfügung.
2. Beschreiben: betroffene Version (Seite «Versionen»), Seite oder Schnittstelle, Schritte zum Nachstellen,
   mögliche Auswirkung. **Keine echten Personendaten** und keine Zugangsdaten mitschicken.

## Was dich erwartet

- Eingangsbestätigung innerhalb von **5 Arbeitstagen**.
- Einschätzung und, wenn nötig, Korrektur auf `main`; betrifft es Personendaten, wird die Meldepflicht nach
  IDG Kanton Zürich geprüft.
- Nach der Korrektur ein Eintrag im CHANGELOG unter «Behoben» (ohne Details, die einen Angriff erleichtern).

## Grundsätze im Prototyp

- Nur erfundene Testdaten; der Testserver steht nicht in der Schweiz und erhält keine echten Daten.
- Zugangsdaten von Plugins werden verschlüsselt gespeichert und nie angezeigt oder protokolliert.
- API nur mit Token (gespeichert als Hash), Rechte wie die Besitzerin bzw. der Besitzer.
