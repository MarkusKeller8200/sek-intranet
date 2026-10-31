# Konzept: Aufgezeichnete API → Plugin

Stand: Version 0.19.0 (Generator und API-Ansicht umgesetzt). Ergänzt [KONZEPT-API-RECORDER.md](KONZEPT-API-RECORDER.md).

## Ziel

Aus einer mit dem API-Recorder aufgezeichneten Schnittstelle (OpenAPI-Datei) soll mit wenig Handarbeit ein
Plugin entstehen oder ein bestehendes Plugin um fehlende Endpunkte erweitert werden – ohne dass ungeprüfte
Aufzeichnungen direkt produktiv Daten schreiben.

## Ablauf

```
Aufzeichnen ──► Dokumentieren ──► Plugin zuweisen ──► Entwurf ──► Prüfen ──► Plugin (Code + Manifest) ──► Freigabe
 Recorder        Beschreibung,      fehlt/vorhanden     plugin.json   Hersteller-   Pull-Request, CI       allow_write
                 Gruppe, Pfad                           Endpunkte     Doku, Tester
```

1. **Aufzeichnen** – Prototyp → API-Recorder → «Neue API aufzeichnen». Name, URL, Basis-URL. Die Dokumentation
   speichert sich automatisch und kann jederzeit wieder geöffnet und ergänzt werden («Öffnen & weiter
   aufzeichnen»), wenn Befehle fehlen.
2. **Dokumentieren** – je Endpunkt Pfad mit Platzhaltern (`{id}`), kurze Beschreibung, Gruppe.
3. **Plugin zuweisen** – im Feld «Plugin (Schnittstelle erweitern)». Der Recorder vergleicht Methode und Pfad
   (Platzhalter-Namen und Schrägstrich am Ende egal) mit `service.endpoints` im Manifest und zeigt je Endpunkt
   ✓ (Endpunkt-ID) oder «fehlt». Die Plugin-Seite zeigt die zugewiesenen Aufzeichnungen ebenfalls.
4. **Entwurf** – «Plugin-Entwurf» lädt eine JSON-Datei mit den fehlenden Endpunkten im Format von
   `plugin.json → service.endpoints` (Status immer `partial`, Parameter aus Pfad und Abfrage).
5. **Prüfen** – Entwurf mit der Hersteller-Dokumentation abgleichen, Berechtigungen (`permission`) und Zweck
   (`use`) ergänzen, mit dem API-Tester des Plugins gegen eine Testumgebung aufrufen. Erst dann `status: full`.
6. **Plugin** – Manifest ergänzen, bei Bedarf Aktionen in `Plugin.php`, Tests; Pull-Request, CI.
7. **Freigabe** – Schreibende Aktionen bleiben aus, bis die Administration `allow_write` im Plugin einschaltet.

## Abbildung OpenAPI/Recorder → Plugin

| Recorder / OpenAPI                           | Plugin                                                                    | Bemerkung                                              |
| -------------------------------------------- | ------------------------------------------------------------------------- | ------------------------------------------------------ |
| Titel, `info.description`                    | `name`, `description`                                                     |                                                        |
| Basis-URL (`servers[0].url`)                 | Einstellung `base_url` (`settings[]`)                                     | nie fest im Code; je Schule verschieden                |
| `components.securitySchemes` (erstes Schema) | `service.auth` (`bearer`, `basic`, `header`, `oauth2_client_credentials`) | Geheimnisse als `password`-Einstellung, verschlüsselt  |
| Operation (Methode + Pfad)                   | `service.endpoints[]` mit `id`, `method`, `path`, `label`                 | ID aus dem Pfad (`api/v1` weggelassen, `{id}` → `one`) |
| Pfad-Platzhalter, Abfrageparameter           | `params[]` (`required` für Pfad)                                          |                                                        |
| `tags` (Gruppe)                              | Gruppierung im API-Tester                                                 |                                                        |
| Antwortschema                                | Grundlage für Feldzuordnung (Mapping) in `Plugin.php`                     | nur Struktur, keine Werte                              |
| GET-Endpunkte                                | Widgets, Personen-/Geräte-Abfragen (`action`)                             | lesend, sofort nutzbar                                 |
| POST/PUT/PATCH/DELETE                        | Aktionen mit `write: true`                                                | nur mit `allow_write` und Bestätigung                  |
| Listen von Personen/Klassen                  | `PeopleImport` mit `_external_id`                                         | Datenherkunft über `PersonLinks`                       |
| Schreiben in Zielsystem                      | Sync-Ziel in `PersonLinks` (`direction: target`)                          | Status «geändert seit Sync»                            |

## Generator (umgesetzt)

```
php artisan intranet:plugin-from-openapi <datei.json|aufzeichnungs-id> [--plugin=<id>] [--neu=<Ordner>] [--dry-run]
```

- Quelle: OpenAPI-Datei (JSON, z. B. «OpenAPI-Datei (JSON)» aus dem Recorder oder eine Hersteller-Datei) oder
  die ID einer Aufzeichnung. Bei einer Aufzeichnung mit zugewiesenem Plugin ist `--plugin` nicht nötig.
- `--plugin`: bestehendes `plugin.json` um fehlende Endpunkte ergänzen (Vergleich wie im Recorder:
  `ApiDocs::normalize`). Vorhandene Einträge werden nie überschrieben; belegte IDs erhalten `-2`, `-3` …
- `--neu`: Ordner `plugins/<Ordner>/` mit `plugin.json` (Endpunkte, Einstellung `base_url`, Anmeldung aus
  `securitySchemes` mit `password`-Einstellungen), `Plugin.php` (leere Klasse; «Testen» nutzt den ersten
  GET-Endpunkt) und `README.md` (Prüfliste, Endpunkttabelle).
- `--host=<schlüssel>`: mit `--plugin` die neuen Endpunkte einer weiteren Adresse desselben Herstellers zuordnen
  (`service.hosts`, z. B. LMVZ: Shop und `digital`); die Anmeldung gilt nur für Adressen in `service.auth.hosts`.
- `--dry-run`: nur anzeigen.
- Alle neuen Endpunkte: `status: partial`, `use: API-Tester`, `note` mit Herkunft; nicht-GET mit `danger: true`.
- Prüfschritt danach von Hand: sprechende IDs und Beschriftungen, Parameter benennen, `effect: read` für Listen per
  POST, `effect: write` für GET-Aufrufe mit Wirkung, Personendaten in `note` kennzeichnen (Beispiel: `plugins/Lmvz`).
- Jedes Ergebnis wird vor dem Schreiben mit `PluginManifest::fromArray` geprüft.
- Läuft nicht mit `APP_ENV=production` (ausser `--dry-run`): Plugins entstehen im Repository (Pull-Request).
- Im Recorder steht der passende Befehl unter den Knöpfen («Plugin daraus erzeugen», kopierbar).
- Umsetzung: `App\Plugins\PluginGenerator`, `App\Console\Commands\PluginFromOpenApiCommand`, Tests
  `tests/Feature/PluginGeneratorTest.php`.

## API-Ansicht (Scalar)

Scalar API Reference (Open Source, MIT, npm `@scalar/api-reference`) zeigt OpenAPI-Beschreibungen interaktiv an:

- Prototyp → API → «Referenz (Scalar)» (`/api-doku/referenz`): eigene REST-API, ausprobieren mit API-Token.
- Plugin-Seite → «API-Ansicht» (`/plugins/<id>/api-ansicht`): Endpunkte aus `plugin.json` (`App\Plugins\ManifestOpenApi`),
  gruppiert nach Status, ohne Einstellungswerte und Zugangsdaten.
- API-Recorder → «Ansicht (Scalar)» (`/api-recorder/<id>/ansicht`): aufgezeichnete API.

Datenschutz: Scalar wird mit dem Intranet ausgeliefert und erst beim Öffnen geladen; Telemetrie aus, keine
Schriften von fremden Servern, kein Scalar-Proxy, Agent und MCP von Scalar aus (`components/api-reference.tsx`).
«Test Request» an fremde Systeme scheitert meist an CORS – bewusst kein Umweg über den Server, damit keine
Zugangsdaten über den Server laufen.

## Prüfungen

- Status `partial` → `full` nur nach Abgleich mit der Hersteller-Dokumentation.
- CI: Manifest-Validierung (`PluginManifest`: Pflichtfelder, eindeutige Endpunkt-IDs, gültige Methoden und Status), PHPStan, Tests für jede
  neue Aktion mit erfundenen Daten (HTTP-Fakes).
- Datenschutz-Hinweis (`data_notice`) ergänzen, wenn Personendaten betroffen sind; Hosting in der Schweiz
  (ADR-005) und IDG Kanton Zürich beachten.

## Sicherheit

- Aufzeichnungen enthalten keine Geheimnisse und keine Rohdaten; Beispielwerte nur maskiert.
- Generierte Plugins sind nach der Installation ausgeschaltet; schreibende Aktionen zusätzlich gesperrt.
- Zugangsdaten werden nur in den Plugin-Einstellungen hinterlegt (verschlüsselt), nie im Manifest oder Code.

## Grenzen

- Aufzeichnung zeigt nur, was bedient wurde: seltene Fälle, Fehlercodes und Seitenumbruch (Paging) fehlen oft.
- Schemas sind aus Beispielen abgeleitet (Typen, nicht Bedeutung); Pflichtfelder sind geraten.
- Anmeldeverfahren (Login-Formulare, SSO) lassen sich nicht automatisch in `service.auth` übersetzen.
- Nicht jede Website erlaubt die Nutzung ihrer internen API; Nutzungsbedingungen und Vertrag prüfen.
