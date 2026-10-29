# AGENTS.md – Hinweise für KI-Agenten (Claude, ChatGPT)

Projekt: SekBülach Intranet, Prototyp. Stack und Konventionen wie IDA.

## Grundregeln

- Nur erfundene Testdaten verwenden, nie echte Personendaten (Volksschule, IDG Kanton Zürich).
- Keine Datenbank einführen, solange `INTRANET_STORE=json` gilt. Fachdaten nur über die
  Repository-Schnittstellen in `app/Data/Contracts` lesen und schreiben, nie direkt über Dateien.
- Jede neue oder geänderte Sammlung zuerst im Schema `config/intranet.php` nachführen.
- Dateien und Daten nie ohne Rückfrage löschen; `intranet:seed --force` nur in Tests.
- UI-Texte Deutsch (Schweiz): «ss» statt «ß».

## Vor jedem Commit

```bash
composer ci:check   # Format/Lint Frontend, TypeScript, Pint, PHPStan Level 7, PHPUnit
```

Frontend-Format korrigieren: `npm run check:fix`. PHP-Format korrigieren: `composer lint`.

## Aufbau

| Pfad | Inhalt |
| --- | --- |
| `app/Data/Store/JsonStore.php` | Dateispeicher (Sperren, atomares Schreiben, UUID v7) |
| `app/Data/Contracts` | Repository-Schnittstellen (bleiben beim Wechsel auf PostgreSQL) |
| `app/Data/Json` | JSON-Umsetzungen der Repositories |
| `app/Data/SchemaInspector.php` | Vergleich Schema ↔ gespeicherte Daten |
| `app/Auth` | Login-Benutzer und UserProvider auf Basis des UserRepository |
| `app/Providers/IntranetServiceProvider.php` | Bindung Schnittstelle → Umsetzung, Gates |
| `config/intranet.php` | Speicher, Auswahllisten, Datenschema |
| `resources/js/pages` | Inertia-Seiten (React) |
| `tests` | Jeder Test startet mit frischen Demo-Daten in `storage/framework/testing/data` |
