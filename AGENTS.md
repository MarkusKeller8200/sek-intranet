# AGENTS.md – Hinweise für KI-Agenten (Claude, ChatGPT)

Projekt: SekBülach Intranet, Prototyp. Stack und Konventionen wie IDA.

## Grundregeln

- Nur erfundene Testdaten verwenden, nie echte Personendaten (Volksschule, IDG Kanton Zürich).
- Keine Datenbank einführen, solange `INTRANET_STORE=json` gilt. Fachdaten nur über die
  Repository-Schnittstellen in `app/Data/Contracts` lesen und schreiben, nie direkt über Dateien.
- Jede neue oder geänderte Sammlung zuerst im Schema `config/intranet.php` nachführen.
- Dateien und Daten nie ohne Rückfrage löschen; `intranet:seed --force` nur in Tests.
- UI-Texte Deutsch (Schweiz): «ss» statt «ß».
- Farben nur über das Farbkonzept: Tokens in `resources/css/app.css`, Farben je Auswahlwert in
  `config/intranet.php` → `colors`, Badges über `OptionBadge`. Keine festen Farbwerte in Seiten.
  Text und Buttons in `primary` (Kontrast AA), Logo-Blau `brand` nur für Linien und Symbole.
- Anbindungen anderer Systeme nur als Plugin unter `plugins/<Ordner>/` (siehe `PLUGINS.md`), nie
  direkt in Controllern. Endpunkte nur aus der Hersteller-Doku; Unbestätigtes als `partial` markieren.
  Zugangsdaten nur über `password`-Einstellungen (verschlüsselt), nie in Code, Logs oder Tests im Klartext.
- Module und Navigation nur in `config/intranet.php` → `modules` pflegen. Geplante Module ohne `href`
  (erscheinen als Platzhalter unter `/module/<schlüssel>`); beim Umsetzen `href` setzen. Symbol in
  `resources/js/lib/module-icons.ts` eintragen.
- REST-API unter `/api/v1` (routes/api.php, `App\Http\Controllers\Api`), Beschreibung in
  `App\Support\OpenApi` nachführen – die Seite «API» (Tester) und `/api/openapi.json` lesen daraus.
  Anmeldung nur mit Token (`api_tokens`, nur Hash gespeichert), Rechte wie die Besitzerin/der Besitzer.
- Wird ein Schlüssel einer Auswahlliste umbenannt, Umstellung unter `renames` eintragen
  (`php artisan intranet:upgrade` stellt bestehende Daten beim Start um).

## Vor jedem Commit

```bash
composer ci:check   # Format/Lint Frontend, TypeScript, Pint, PHPStan Level 7, PHPUnit
```

Frontend-Format korrigieren: `npm run check:fix`. PHP-Format korrigieren: `composer lint`.

## Aufbau

| Pfad                                        | Inhalt                                                                         |
| ------------------------------------------- | ------------------------------------------------------------------------------ |
| `app/Data/Store/JsonStore.php`              | Dateispeicher (Sperren, atomares Schreiben, UUID v7)                           |
| `app/Data/Contracts`                        | Repository-Schnittstellen (bleiben beim Wechsel auf PostgreSQL)                |
| `app/Data/Json`                             | JSON-Umsetzungen der Repositories                                              |
| `app/Data/SchemaInspector.php`              | Vergleich Schema ↔ gespeicherte Daten                                          |
| `app/Auth`                                  | Login-Benutzer und UserProvider auf Basis des UserRepository                   |
| `app/Providers/IntranetServiceProvider.php` | Bindung Schnittstelle → Umsetzung, Gates                                       |
| `config/intranet.php`                       | Speicher, Auswahllisten, Module/Navigation, Datenschema                        |
| `resources/js/pages`                        | Inertia-Seiten (React)                                                         |
| `tests`                                     | Jeder Test startet mit frischen Demo-Daten in `storage/framework/testing/data` |
