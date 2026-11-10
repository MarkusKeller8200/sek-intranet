# SekBülach Intranet – Prototyp

Intranet-Prototyp der Sekundarschule Bülach. Gleicher Stack wie **IDA**
(Laravel + Inertia + React + shadcn/ui + Tailwind), aber **ohne Datenbank**:
Alle Fachdaten liegen als lesbare JSON-Dateien im Projekt. So lassen sich
Funktionen und Datenstrukturen schnell ausprobieren. Sind sie stabil, wird
PostgreSQL darunter gebaut, ohne Controller oder Oberfläche anzufassen.

> Prototyp: nur erfundene Testdaten verwenden, keine echten Personendaten.

## Stack (wie IDA, ADR-001 bis ADR-005)

| Schicht  | Technologie                                                     |
| -------- | --------------------------------------------------------------- |
| Backend  | Laravel 13 (PHP 8.3+, empfohlen 8.4)                            |
| Frontend | React 19 + TypeScript, Inertia 3, shadcn/ui (Radix), Tailwind 4 |
| Listen   | eigene bearbeitbare Tabelle (`components/data-grid.tsx`)        |
| Ziehen   | dnd-kit (Drag & Drop mit Tastatur-Alternative)                  |
| Speicher | **JSON-Dateien** (`storage/app/data/*.json`) statt PostgreSQL   |
| Login    | Demo-Auswahl (Prototyp) statt OIDC Entra ID / Edulog            |
| Tests    | PHPUnit, PHPStan Level 7 (Larastan), Pint                       |

Bewusst weggelassen gegenüber dem Starterkit: Fortify (Passwörter, 2FA,
Passkeys, Registrierung) – das Intranet meldet später über das Schulkonto an.

## Starten (Windows, gleiche Umgebung wie IDA)

Voraussetzungen: PHP 8.4, Composer, Node 22. **Docker ist nicht nötig.**

```powershell
cd S:\_Claude\sekbuelach-intranet
composer setup     # .env, Schlüssel, Demo-Daten, npm install, Build
composer dev       # App unter http://localhost:8000
```

Anmelden: eine der Demo-Personen wählen. Rollen zum Testen:

| Person                      | Rolle          | darf                                        |
| --------------------------- | -------------- | ------------------------------------------- |
| Andrea Muster               | Administration | alles, inkl. Personen und «Datenstrukturen» |
| Beat Beispiel, Claudia Demo | Redaktion      | Mitteilungen, Termine, Dokumente bearbeiten |
| übrige                      | Mitarbeitende  | lesen                                       |

Prüfen wie in CI: `composer ci:check`. Frontend-Format und -Lint automatisch
korrigieren: `npm run check:fix`.

## Online stellen

Automatisches Deployment auf `https://intranet.markussx.ch` (Hostinger-VPS) und `https://intranet-dev.sekbuelach.ch` (Infomaniak, siehe [docs/SERVER-INFOMANIAK.md](docs/SERVER-INFOMANIAK.md))
bei jedem Push auf `main`: siehe [DEPLOY.md](DEPLOY.md).

## Funktionen

- **Start**: Kennzahlen, aktuelle Mitteilungen, nächste Termine, wichtige Links
- **Mitteilungen**: Kategorien, Zielgruppe, anheften, zeitgesteuert veröffentlichen
- **Termine**: nach Monat gruppiert, ganztägig oder mit Uhrzeit, vergangene einblendbar
- **Dokumente & Links**: Datei-Upload (max. 20 MB) oder Link, nach Kategorie
- **Personen** und **Schüler:innen**: Tabelle wie Excel, Filter, Ansichten, Export, Datenherkunft; Verwaltung nur
  Administration
- **Geräteausgabe** (Fachstelle ICT): Zugangsdaten, Kärtchen, PDF, PowerShell-Skript
- **Fachanwendungen**: CMI, Escola, Apple School Manager, Jamf Pro, LMVZ Digital – eingebettet, im eigenen Tab oder
  über die Browser-Erweiterung «SBI-Connector» (auch nebeneinander mit dem Intranet)
- **Benachrichtigungen**: Glocke oben rechts mit ungelesenen Meldungen, Seite `/benachrichtigungen`
- **Einstellungen** (Administration): Adresse und Art des Öffnens je Fachanwendung, Browser-Erweiterung
- **Prototyp** (Administration): Plugins (Microsoft 365/Entra ID, Jamf Pro, Inventory360, IDA, Escola, LMVZ, ePost –
  siehe [PLUGINS.md](PLUGINS.md)), Datenstrukturen, Farbkonzept mit Themes, API mit Scalar, API-Recorder,
  MCP-Konzept, Sprach- und Rollenkonzept
- **GUI-Prototypen**: 40 Bausteine der Oberfläche mit Beispielen, Status (Entwicklung → Prüfung → Freigegeben),
  Versionen, Überarbeitung und Wünschen; Inhaltsverzeichnis [docs/GUI-KOMPONENTEN.md](docs/GUI-KOMPONENTEN.md)
- Hell/Dunkel, Themes, responsive, PWA, Deutsch/Englisch/Züritüütsch

Konzepte: [docs/](docs) (Rollen, Datenherkunft, Sprachen, PWA, MCP, API-Recorder, Browser-Connector,
GUI-Prototypen, Themes, Benachrichtigungen).

## Mitmachen, Issues und Versionen

- Fehler, Verbesserungen, Ideen, Plugin-Ideen und Wünsche zu GUI-Bausteinen als
  [Issue mit Vorlage](https://github.com/MarkusKeller8200/sek-intranet/issues/new/choose); neue Meldungen tragen
  `triage`, umgesetzt wird erst nach Freigabe (`_1 bereit`). Ablauf in [CONTRIBUTING.md](CONTRIBUTING.md),
  Sicherheitslücken nach [SECURITY.md](SECURITY.md).
- Jeder Push auf `main` wird ausgerollt (Developer Release). Eine neue Versionsnummer, ein Tag `vX.Y.Z` und ein
  GitHub-Release entstehen nur bei einem freigegebenen Release (`php tools/release.php X.Y.Z --commit`) –
  siehe [docs/KONZEPT-VERSIONIERUNG.md](docs/KONZEPT-VERSIONIERUNG.md).

## Module

Fachthemen wie Zeiterfassung, Bestellungen oder Lizenzen entstehen als Module unter `modules/<Ordner>/`
(`php artisan intranet:make-module Name --gruppe=…`). Jedes Modul hat eigene Rechte, ein eigenes Menü und eine eigene
Datenhaltung; ein- und ausgeschaltet wird es unter Einstellungen → Module. Konzept und Regeln:
[docs/KONZEPT-MODULE.md](docs/KONZEPT-MODULE.md).

## So funktioniert der Dateispeicher

```text
React-Seite  ──Inertia──►  Controller  ──►  Repository-Schnittstelle (app/Data/Contracts)
                                                   │
                                     heute: Json*Repository (app/Data/Json)
                                                   │
                                     JsonStore ──► storage/app/data/<sammlung>.json
                                     später: Database*Repository ──► PostgreSQL
```

- **Eine Sammlung = eine Datei** (`users.json`, `announcements.json`, …), von Hand lesbar und editierbar.
- **Sicher bei gleichzeitigen Zugriffen**: Sperre pro Sammlung, atomares Schreiben (temporäre Datei + Umbenennen).
- **IDs als UUID v7** (zeitlich sortierbar) – wie in IDA geplant, übernehmbar in PostgreSQL.
- **Zeitstempel** `created_at`/`updated_at` automatisch, Daten in ISO 8601 mit Zeitzone.
- **Schema** in `config/intranet.php` (Abschnitt `schema`): Typen, Pflichtfelder, Verweise,
  Auswahllisten. Die Seite «Datenstrukturen» vergleicht es laufend mit den echten Daten.
- Hochgeladene Dateien: `storage/app/private/intranet/documents`.

Nützliche Befehle:

```powershell
php artisan intranet:seed           # Demo-Daten anlegen (fragt, bevor Daten überschrieben werden)
php artisan intranet:seed --force   # Demo-Daten ohne Rückfrage neu anlegen
```

Datenordner ändern: `INTRANET_DATA_PATH` in `.env`.

## Neue Funktion hinzufügen (Muster)

1. Sammlung im Schema `config/intranet.php` beschreiben.
2. Schnittstelle in `app/Data/Contracts`, JSON-Umsetzung in `app/Data/Json`
   (erbt `JsonRepository`), Bindung in `IntranetServiceProvider::IMPLEMENTATIONS`.
3. Controller + Routen (`routes/web.php`), Rechte über die Gates `manage-content` / `manage-users`.
4. Seite unter `resources/js/pages`, Typen in `resources/js/types/intranet.ts`.
5. Feature-Test unter `tests/Feature`.

## Später: Datenbank darunterbauen

Wenn Funktionen und Datenstrukturen stabil sind:

1. Seite «Datenstrukturen» prüfen: keine Felder ausserhalb des Schemas, Pflichtfelder gefüllt.
2. Aus dem Schema Laravel-Migrationen erstellen (PostgreSQL 17, Typvorschläge stehen auf der Seite;
   `enum` als `varchar` + CHECK, `ref` als `uuid`-Fremdschlüssel).
3. Pro Schnittstelle eine `Database*Repository`-Klasse (Eloquent oder Query Builder) schreiben
   und in `IntranetServiceProvider` unter `'database'` eintragen.
4. Einmaliger Import: JSON-Dateien lesen und in die Tabellen schreiben (IDs bleiben gleich).
5. `INTRANET_STORE=database` setzen. Die bestehenden Feature-Tests laufen unverändert gegen die
   neue Umsetzung und zeigen, ob sich etwas anders verhält.
6. Login auf OIDC umstellen (IDA ADR-004), `INTRANET_DEMO_LOGIN=false`.

## Offene Punkte

- Kategorien und Rollen in `config/intranet.php` sind Vorschläge und können angepasst werden.
- Zugriffsschutz im Prototyp: Rolle + 8-stelliger PIN (Einstellungen → Zugang, nur als Hash gespeichert). Bis der
  PIN «Administrator» gesetzt ist, gilt das gemeinsame Passwort (`INTRANET_ACCESS_PASSWORD`). Später OIDC.
- Keine Volltextsuche über alle Bereiche; Benachrichtigungen nur im Intranet (noch keine E-Mail/Push).
