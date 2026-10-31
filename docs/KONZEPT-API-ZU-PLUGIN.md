# Konzept: Aufgezeichnete API → Plugin

Stand: Version 0.18.0. Ergänzt [KONZEPT-API-RECORDER.md](KONZEPT-API-RECORDER.md).

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

| Recorder / OpenAPI | Plugin | Bemerkung |
| --- | --- | --- |
| Titel, `info.description` | `name`, `description` | |
| Basis-URL (`servers[0].url`) | Einstellung `base_url` (`settings[]`) | nie fest im Code; je Schule verschieden |
| Kopfzeile `Authorization: Bearer …`, Login-Aufruf | `service.auth` (`bearer`, `basic`, `oauth2_client_credentials`, `api_key`) | Geheimnisse als `secrets`, verschlüsselt |
| Operation (Methode + Pfad) | `service.endpoints[]` mit `id`, `method`, `path`, `label` | ID aus dem Pfad (`api/v1` weggelassen, `{id}` → `one`) |
| Pfad-Platzhalter, Abfrageparameter | `params[]` (`required` für Pfad) | |
| `tags` (Gruppe) | Gruppierung im API-Tester | |
| Antwortschema | Grundlage für Feldzuordnung (Mapping) in `Plugin.php` | nur Struktur, keine Werte |
| GET-Endpunkte | Widgets, Personen-/Geräte-Abfragen (`action`) | lesend, sofort nutzbar |
| POST/PUT/PATCH/DELETE | Aktionen mit `write: true` | nur mit `allow_write` und Bestätigung |
| Listen von Personen/Klassen | `PeopleImport` mit `_external_id` | Datenherkunft über `PersonLinks` |
| Schreiben in Zielsystem | Sync-Ziel in `PersonLinks` (`direction: target`) | Status «geändert seit Sync» |

## Geplanter Generator

```
php artisan intranet:plugin-from-openapi <datei|api-doc-id> [--plugin=<id>] [--neu=<Ordner>] [--dry-run]
```

- `--plugin`: bestehendes Manifest um fehlende Endpunkte ergänzen (gleiche Logik wie «Plugin-Entwurf»,
  `App\Support\ApiDocs::endpointDraft`). Vorhandene Einträge werden nie überschrieben.
- `--neu`: neuen Ordner `plugins/<Name>/` anlegen mit `plugin.json` (Endpunkte, Einstellung `base_url`, Auth aus
  dem Schema), `Plugin.php` (Klasse mit `test()` über den ersten GET-Endpunkt, leere `action()`), `README.md`.
- `--dry-run`: nur Unterschiede anzeigen.
- Ausgabe immer als Änderung im Repository (Pull-Request), nie direkt auf dem Server.

## Prüfungen

- Status `partial` → `full` nur nach Abgleich mit der Hersteller-Dokumentation.
- CI: Manifest-Validierung (Pflichtfelder, eindeutige Endpunkt-IDs, gültige Methoden), PHPStan, Tests für jede
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
