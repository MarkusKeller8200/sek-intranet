# AGENTS.md – Hinweise für KI-Agenten (Claude, ChatGPT)

Projekt: SekBülach Intranet, Prototyp. Stack und Konventionen wie IDA.

## Grundregeln

- Nur erfundene Testdaten verwenden, nie echte Personendaten (Volksschule, IDG Kanton Zürich).
- Keine Datenbank einführen, solange `INTRANET_STORE=json` gilt. Fachdaten nur über die
  Repository-Schnittstellen in `app/Data/Contracts` lesen und schreiben, nie direkt über Dateien.
- Datenstrukturen immer nachführen: jede neue oder geänderte Sammlung und jedes Feld zuerst im Schema
  `config/intranet.php` → `schema` (Label, Typ, Gruppe). `SchemaCompletenessTest` und die Seite
  «Datenstrukturen» prüfen das.
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
- Mehrsprachigkeit (siehe `docs/KONZEPT-SPRACHEN.md`): im Code nur deutsche Texte. Jeder neue sichtbare Text
  kommt im selben Commit in einen Katalog `resources/js/i18n/*.json` als `"Deutsch": ["English", "Züritüütsch"]`
  (Zahlen als `{n}`). `node tools/i18n-extract.mjs` muss «0 fehlen» melden (läuft in `composer ci:check` mit `--check`). Daten (Namen, Inhalte) mit
  `translate="no"` schützen.
- Versionierung: Nummer nur in `VERSION` (SemVer), Änderungen laufend in `CHANGELOG.md` unter «Unveröffentlicht».
- MCP-Schnittstelle (Konzept, `docs/KONZEPT-MCP.md`): Werkzeuge werden aus `App\Support\OpenApi` abgeleitet
  (`App\Support\McpConcept`). Neue API-Operationen dort mit Werkzeugnamen ergänzen.
- Eigene Anwendungen (andere Repositories) und fremde Seiten nicht in den Code übernehmen, sondern als
  Menüpunkt mit `embed` einbinden (`docs/KONZEPT-ANWENDUNGEN.md`); Adressen eigener Anwendungen nur über `.env`.
- Rollen und Rechte (`docs/KONZEPT-ROLLEN.md`): Menüs und Routen fragen nur Rechte ab (`can:<recht>`), nie Rollen.
  Neue Rechte unter `config/intranet.php` → `abilities`.
- Schüler:innen und Eltern sind Personen mit Personenart (`docs/KONZEPT-DATENHERKUNFT.md`): im Prototyp nur
  erfundene; sichtbar nur mit Recht `view-students` (`PersonTypes::visibleFor`); ohne Anmeldung. Passwörter und PINs
  der Geräteausgabe nie speichern.
- Importe: Personen zuerst über die Datenherkunft (`PersonLinks`, System + ID) suchen, dann über die E-Mail;
  `PeopleImport` mit Systemschlüssel und `_external_id` verwenden.
- API-Recorder (`docs/KONZEPT-API-ZU-PLUGIN.md`): Aufzeichnungen einem Plugin zuweisen; Entwürfe daraus nur als
  `partial` ins Manifest übernehmen und erst nach Abgleich mit der Hersteller-Doku `full` setzen. Dafür
  `php artisan intranet:plugin-from-openapi` verwenden (nie in Produktion). API-Ansichten nur über
  `components/api-reference.tsx` (Scalar ohne Telemetrie, fremde Schriften und Proxy).
- Einstellungen der Menüpunkte (Menü «Einstellungen», `App\Support\ModuleSettings`): Adressen und Art des Öffnens
  von Einbindungen nicht hart codieren, wenn sie je Schule verschieden sind – Vorgabe in `config/intranet.php`
  bzw. `.env`, Anpassung über das Menü. Browser-Erweiterung `browser-extension/` (docs/KONZEPT-BROWSER-CONNECTOR.md):
  Nachrichten nur über `resources/js/lib/connector.ts`; Erfassung in `background.js` und
  `lib/api-recorder/capture.ts` gemeinsam nachführen.
- GUI-Prototypen (`docs/KONZEPT-GUI-PROTOTYPEN.md`): Oberflächen nur aus den Bausteinen unter Menü «GUI-Prototypen»
  bauen und an die freigegebene Version halten. Neue Bausteine in `config/intranet.php` → `gui_components` und
  `components/gui-demos.tsx` (bzw. `gui-demos-extra.tsx`, `gui-demos-more.tsx`), Übersicht in `docs/GUI-KOMPONENTEN.md`; nach einer Freigabe
  die Liste «Überarbeitung» abarbeiten. Drag & Drop nur mit dnd-kit (`components/ui/sortable-list.tsx`,
  `drag-board.tsx`) und immer mit Alternative ohne Ziehen (WCAG 2.2, 2.5.7).
- Themes (`docs/KONZEPT-THEMES.md`): neue Farbwerte in `resources/css/app.css` (hell und dunkel) und in
  `App\Support\Themes::GROUPS` sowie `lib/themes.ts` (Bezeichnung) eintragen, damit Themes sie übersteuern können.
- Benachrichtigungen (`docs/KONZEPT-BENACHRICHTIGUNGEN.md`): Ereignisse nur über `App\Support\Notifications`
  melden (`toAbility` nach Recht, `toUsers` einzeln), Link nur innerhalb des Intranets, keine Schülerdaten im Text.
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
