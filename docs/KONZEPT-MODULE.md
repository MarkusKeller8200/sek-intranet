# Konzept: Module, Plugins und eigenständige Projekte

Entscheid des Projektverantwortlichen vom 01.11.2026. Umsetzung erst nach Freigabe der einzelnen Issues
(`_1 bereit`).

## Entscheid

1. **Ein Intranet, ein Repository, ein Deployment.** Installation, Updates, Überwachung, Backup und Patches bleiben
   für ein einziges System. Einzeln ausgerollte Module (eigene Container und Repositories) gibt es nicht auf Vorrat.
2. **Module sind eine Ordnerstruktur im Intranet** und trennen die Themen fachlich:
   `modules/<Name>/` mit `module.json`, eigenen Rechten, eigenem Menü, eigenen Tests und eigenem CHANGELOG.
3. **Jedes Modul hat seine eigene Datenhaltung.**
    - Mit Datenbank: ein PostgreSQL-Schema und ein DB-Benutzer pro Modul. Der Benutzer darf nur im eigenen Schema
      schreiben («eigener DB-Writer»).
    - Jedes Modul hat eigene Migrationen und eigene Repositories (Contracts mit JSON- und Database-Umsetzung).
      Die Umstellung auf die Datenbank erfolgt pro Modul.
4. **Keine Zugriffe quer zwischen Modulen.** Erlaubt sind nur Core-Schnittstellen (z. B. Personen), Ereignisse
   und die REST-API. Fremdschlüssel nur auf Core-Stammdaten (UUID). Eine Architekturprüfung in der CI soll das
   sicherstellen.
5. **Plugins bleiben Adapter zu fremden Systemen** (Entra, Jamf, Escola, ePost …). Module nutzen sie über die
   Plugin-Schnittstelle.
6. **Eigenständige Projekte nur mit konkretem Grund**, zum Beispiel öffentlicher Zugang ohne Login, eine
   andere verantwortliche Stelle oder die Weitergabe an andere Schulen. Sie werden über eine API als Plugin
   und/oder als Einbindung (`embed`) angeschlossen.

## Abgrenzung

|           | Core                                                                                             | Modul                                                                                                                    | Plugin                                             | Eigenständiges Projekt                 |
| --------- | ------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------- | -------------------------------------- |
| Ort       | Intranet                                                                                         | `modules/<Name>/` im Intranet                                                                                            | `plugins/<Ordner>/`                                | eigenes Repository, eigenes Deployment |
| Daten     | Stammdaten (Personen, Organisation, Rechte)                                                      | eigenes Schema                                                                                                           | keine eigenen Fachdaten (nur Einstellungen, Läufe) | eigene Datenbank                       |
| Login     | OIDC (später)                                                                                    | über den Core                                                                                                            | –                                                  | eigenes, falls nötig                   |
| Beispiele | Anmeldung, Personen, Rollen, Navigation, Benachrichtigungen, GUI-Bausteine, Plugin-Laufzeit, API | Kommunikation, Wissen, Zeiterfassung, Beschaffung (HW/SW bestellen), ICT/Lizenzen verteilen, Unterricht, Synchronisation | Entra, Jamf, Escola, ePost, Inventory360, LMVZ     | Systematische Rechtssammlung           |

## Systematische Rechtssammlung (eigenes Projekt)

Begründung: Die Inhalte sind öffentlich, es gibt kein Login für Endnutzer:innen und kaum Stammdaten. Sie hat also
ein anderes Publikum und einen anderen Schutzbedarf als das Intranet.

- **Eigenes Repository** (z. B. `sek-rechtssammlung`). Öffentliche Website mit Suche, Gliederung nach
  Systematik, Erlass mit Fassungen («gültig ab/bis»), Änderungshistorie und Druck-/PDF-Ansicht.
- **Redaktion** mit Anmeldung nur für Bearbeitende (OIDC über Entra ID); das ist das einzige Login.
- **Öffentliche, lesende REST-API** mit OpenAPI-Beschreibung (z. B. `/api/v1/erlasse`, `/erlasse/{id}`,
  `/systematik`, `/suche?q=`), ohne Token, mit Rate-Limit.
- **Anbindung ans Intranet:**
    - Plugin `Rechtssammlung` mit den Aktionen «Erlass suchen» und «Erlass anzeigen», einer Kachel «Neu/geändert»
      und gegebenenfalls einer Benachrichtigung bei neuen Fassungen.
    - Menüpunkt unter «Wissen & Ablage» als Einbindung bzw. Link.
- **Stack** wie IDA und Intranet (Laravel, React/Inertia, GUI-Bausteine und Farbkonzept des Intranets), damit das
  Aussehen einheitlich ist. Der GitHub-Ablauf (Vorlagen, Labels, Releases) wird aus diesem Repository übernommen.
- **Hosting:** eigenes kleines Deployment, `rechtssammlung.sekbuelach.ch`. Die Daten sind öffentlich, trotzdem gilt
  Infomaniak als Ziel.
- **Antworten (02.11.2026, #16):**
    - Bestand nur als **Archiv-PDF**. Import mit Prüfansicht (#21); das PDF bleibt als Archivfassung verlinkt.
    - Sprache: **Deutsch**.
    - **Verweise** auf Erlasse anderer Stellen (Gemeinde, Kanton/ZH-Lex): ja, als Links mit Titel und Stand.
    - Adresse: **rechtssammlung.sekbuelach.ch**.
    - Offen: verbindliche Fassung / Publikationsorgan.

## Umsetzung (0.33.0)

### Aufbau eines Moduls

```
modules/Zeiterfassung/
  module.json                     Manifest (siehe unten)
  ModuleServiceProvider.php       bindet die Repositories an den eigenen Speicher (erbt App\Modules\ModuleProvider)
  routes.php                      Routen (nur geladen, wenn eingeschaltet; immer angemeldet)
  Http/…Controller.php            Controller (Namensraum Modules\Zeiterfassung\…)
  Data/Contracts/…Repository.php  Schnittstellen
  Data/Json/Json…Repository.php   JSON-Umsetzung (erbt App\Data\Json\JsonRepository)
  resources/js/pages/*.tsx        Seiten, Inertia-Name «Zeiterfassung::seite»
  resources/js/i18n/*.json        Übersetzungen des Moduls
  Tests/*Test.php                 Tests (laufen in composer ci:check mit)
  CHANGELOG.md, README.md
```

Anlegen mit `php artisan intranet:make-module Zeiterfassung --gruppe=organisation [--titel=…] [--key=…] [--schema=…]`.
Ein- und ausschalten unter **Einstellungen → Module**. Ausgeschaltet heisst: kein Menü, keine Routen (404), keine
Rechte. Die Daten bleiben erhalten.

### Manifest `module.json`

| Feld | Bedeutung | Prüfung |
|---|---|---|
| `key` | Schlüssel, z. B. `zeiterfassung` | Kleinbuchstaben, Ziffern, Bindestrich; eindeutig |
| `name`, `description` | Anzeige | Pflicht bzw. optional |
| `version` | Version des Moduls (SemVer) | eigener CHANGELOG |
| `core` | benötigte Intranet-Version, z. B. `>=0.33.0` | wird beim Laden verglichen |
| `aktiv` | Vorgabe ein/aus | Übersteuerung in der Sammlung `module_states` |
| `datenklasse` | `oeffentlich`, `intern`, `vertraulich`, `besonders` (IDG) | Pflicht |
| `db_schema` | Schema bzw. Präfix der Sammlungen, z. B. `zeit` | alle Sammlungen beginnen mit `<db_schema>_` |
| `provider` | ServiceProvider des Moduls | Namensraum `Modules\<Ordner>\` |
| `rechte` | eigene Rechte mit `label` und `roles` | keine Überschneidung mit Core oder anderen Modulen |
| `menu` | `gruppe` (Hauptmenü) und `eintraege` (`title`, `icon`, `href`, `can`) | Gruppe muss existieren, `href` beginnt mit `/<key>` |
| `sammlungen` | Datenschema wie `config/intranet.php` → `schema` | erscheint unter «Datenstrukturen» mit Modulangabe |
| `ereignisse` | `sendet` / `empfaengt`, z. B. `zeit.eintrag_erfasst` | Format `bereich.ereignis` |
| `plugins` | benötigte Plugins | Anzeige |

### Datenhaltung

- Jedes Modul hat einen eigenen Datenordner: `<data_path>/modules/<key>/`. Den Core-Speicher und Ordner anderer Module
  erreicht es nicht. `ModuleProvider::store()` liefert den eigenen Speicher.
- Umschaltung pro Modul: `<KEY>_STORE` (z. B. `ZEITERFASSUNG_STORE=database`), sonst wie `INTRANET_STORE`.
- Für PostgreSQL gibt es pro Modul ein eigenes Schema und einen eigenen DB-Benutzer, der nur dort schreiben darf.
  `php artisan intranet:module-sql <key>` zeigt das SQL dazu; es steht auch auf der Seite «Module». Migrationen
  kommen später nach `modules/<Ordner>/database/migrations`. Fremdschlüssel gehen nur auf Core-Stammdaten (UUID).
  Core-Daten liest ein Modul nur über Schnittstellen bzw. Views.

### Architekturprüfung

`App\Modules\ModuleArchitecture` läuft als Test in `composer ci:check` und zeigt Verstösse auch auf der Seite
«Module» an. Ein Modul darf:

- keine Klassen eines anderen Moduls verwenden (`Modules\Anderes\…`) und nicht aus `modules/Anderes/…` importieren,
- keine relativen Importe aus dem eigenen Ordner hinaus machen,
- nicht auf JSON-Umsetzungen des Core, den Core-Speicher (`JsonStore`), `config('intranet.schema')` oder den
  Datenordner zugreifen.

Erlaubt sind Core-Schnittstellen (`App\Data\Contracts`, `App\Support`), die GUI-Bausteine (`@/components/…`) und
`App\Data\Json\JsonRepository` als Grundklasse.

## Planung (Issues und Meilensteine)

**Meilenstein «0.33.0 – Modulgerüst & Pilot Zeiterfassung»**

- #7 Modulgerüst mit den Teilen:
    - #10 Manifest und Laden der Module
    - #11 Seiten und Übersetzungen
    - #12 Datenhaltung pro Modul
    - #13 Architekturprüfung
    - #14 Generator und Doku
- #8 Pilotmodul Zeiterfassung (Umfang und Felder vorher abstimmen)

**Meilenstein «Rechtssammlung – Projektstart»**

- #9 Rechtssammlung als eigenes Projekt mit den Teilen:
    - #16 Offene Fragen klären
    - #17 Repository und Grundgerüst
    - #18 Website und Redaktion
    - #19 Öffentliche API
    - #20 Plugin und Menüpunkt im Intranet
    - #21 Import der Archiv-PDFs und Verweise
- #18 und #19 werden im neuen Repository umgesetzt und dort weitergeführt.

**Später (Sammlung Verbesserungen)**

- #15 Bestehende Bereiche schrittweise in Module verschieben

Alle Issues tragen `triage` (Status-Labels siehe `docs/KONZEPT-VERSIONIERUNG.md`). Umgesetzt wird erst nach Freigabe mit `_1 bereit`; das neue Repository wird erst nach
ausdrücklicher Bestätigung angelegt.
