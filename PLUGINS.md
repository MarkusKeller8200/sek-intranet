# Plugins – andere Systeme per API anschliessen

Plugins verbinden das Intranet mit anderen Systemen (Microsoft 365, Jamf, Inventory360, IDA …).
Aufbau nach dem Vorbild von TagStudio: Ein Plugin beschreibt sich in `plugin.json`, der Code in
`Plugin.php` macht nur das Fachliche. Formulare, Endpunktliste, API-Tester und Protokoll entstehen
automatisch aus der Beschreibung.

> Plugins liegen **nur im Repository** und laufen durch CI (Tests, PHPStan). Im Intranet werden sie
> unter **Prototyp → Plugins** nur ein- und ausgeschaltet und eingerichtet (nur Administration).

## Mitgelieferte Plugins

| Plugin                      | Ordner                 | Kann                                                                                                                                                                                  | Stand                                                              |
| --------------------------- | ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| Microsoft 365 / Entra ID    | `plugins/Entra`        | Personen übernehmen (alle oder eine Gruppe), Person nachschlagen, zu Gruppe hinzufügen, Kachel (Tenant, Benutzer, Gruppen), prüft neue Personen                                       | nach Microsoft-Doku verifiziert                                    |
| Jamf Pro                    | `plugins/Jamf`         | Geräte einer Person, iPad-Übersicht, Inventar aktualisieren (MDM), iPad zuweisen, Kachel (iPads, Macs), meldet Geräte deaktivierter Personen                                          | grösstenteils verifiziert, Rest als «teilweise» markiert           |
| ePost (Schweizerische Post) | `plugins/Epost`        | Verbindung prüfen, Mandanten anzeigen, Posteingang, Versandübersicht (ohne Empfängerdaten), API-Tester (44 Endpunkte: Versand, Vorschau, Identitätsabgleich, Briefkasten, ePost Sign) | nach OpenAPI der Post; Versand noch nicht als Aktion               |
| Inventory360                | `plugins/Inventory360` | Verbindung prüfen, Inventar durchsuchen, Gerät nach Inventarnummer, Hardware-Kategorien, API-Tester (40 Endpunkte)                                                                    | nach API-2.0-Referenz; Antwortfelder an der eigenen Instanz prüfen |
| Escola (Datei-Import)       | `plugins/Escola`       | Mitarbeitende aus der Escola-Adressliste (.xlsx/.csv) übernehmen, nur freigegebene Spalten                                                                                            | fertig – ohne API, per Datei                                       |
| IDA                         | `plugins/Ida`          | Verbindung prüfen, Personen-Ereignisse vormerken                                                                                                                                      | wartet auf die IDA-API                                             |
| DeepSign                    | `plugins/Deepsign`     | Elektronische Unterschrift: Verbindung prüfen, Dokumente anzeigen, Signaturstufen, API-Tester (18 Endpunkte: Hochladen, Unterzeichnende, Starten, Erinnern, Zurückziehen), Demo-Modus | nach API-Doku DeepCloud; Zugang noch offen (#36)                   |
| LMVZ (inoffiziell)          | `plugins/Lmvz`         | Shop: Artikel nachschlagen (Titel, Artikelnummer, ISBN, Listenpreis); LMVZ Digital: Lizenzübersicht, Kachel, API-Tester (Benutzer, Gruppen)                                           | inoffiziell – aus Aufzeichnung, Token kurzlebig                    |

## Datenschutz

Entra, Jamf und Inventory360 liefern **echte Personen- und Gerätedaten**. Solange das Intranet nicht
auf einem Server in der Schweiz läuft (IDA ADR-005: Infomaniak), zeigt die Plugin-Seite eine
Warnung. Auf der Schweizer Umgebung `INTRANET_SERVER_IN_SWITZERLAND=true` setzen.

## Einrichten (Administration)

«Verbindung testen» steht auf jeder Plugin-Karte und auf der Plugin-Seite; «Alle Verbindungen testen» prüft alle
eingeschalteten und eingerichteten Plugins nacheinander (Ergebnis je Karte, Protokoll unter «Läufe»).

1. **Prototyp → Plugins** → Plugin wählen → **Einstellungen** ausfüllen. Zugangsdaten werden mit dem
   `APP_KEY` verschlüsselt gespeichert und nie angezeigt (leer lassen = unverändert).
2. **Einschalten**, dann **Testen**.
3. Unter **Endpunkte & Tester** zeigt **Zugriff prüfen**, welche Endpunkte die hinterlegte Berechtigung
   tatsächlich erlaubt. Der **API-Tester** ruft einzelne Endpunkte auf (Zugangsdaten geschwärzt).
   Parameter und Body sind mit **Beispielwerten** vorausgefüllt; «Beispiel einsetzen» stellt sie wieder her,
   «Felder löschen» leert sie. IDs aus der letzten Antwort (z. B. `documentId` aus einer Liste) werden für
   Pfadparameter anderer Endpunkte übernommen. Zum Body zeigt **Mögliche Werte** die Felder mit «Einfügen»,
   «Alle Werte einfügen» und «Formatieren»; der Body wird laufend als JSON geprüft.
4. Für Übernahmen und schreibende Aktionen **Schreibende Aktionen erlauben** einschalten. Jede solche
   Aktion fragt vor dem Ausführen nach.

Jeder Lauf (Testen, Aktion, API-Aufruf, Ereignis) steht unter **Protokoll** – mit Person, Dauer und
Meldung, ohne Zugangsdaten.

## Aufbau eines Plugins

```text
plugins/
  MeinDienst/          ← Ordner = Namespace Plugins\MeinDienst
    plugin.json
    Plugin.php         ← class Plugin extends App\Plugins\BasePlugin
```

### plugin.json

```json
{
    "id": "mein-dienst",
    "name": "Mein Dienst",
    "version": "1.0",
    "api": 1,
    "category": "identity | devices | inventory | school | other",
    "description": "Erscheint auf der Plugin-Karte.",
    "data_notice": "Optional: welche Personendaten der Dienst liefert.",
    "service": {
        "name": "Mein Dienst API",
        "docs": "https://…",
        "base": "https://api.example.ch/v1",
        "auth": {
            "type": "oauth2_client_credentials",
            "token_url": "https://login.example.ch/{tenant}/token",
            "scope": "…"
        },
        "auth_note": "Welche Berechtigungen der Zugang braucht.",
        "endpoints": [
            {
                "id": "people",
                "method": "GET",
                "path": "/people",
                "label": "Personen",
                "status": "full",
                "use": "Personen übernehmen",
                "permission": "People.Read",
                "params": [
                    { "name": "top", "default": "100" },
                    { "name": "filter", "example": "…" }
                ]
            },
            {
                "id": "person",
                "method": "GET",
                "path": "/people/{id}",
                "label": "Eine Person",
                "status": "full",
                "params": [{ "name": "id", "required": true }]
            },
            {
                "id": "tag",
                "method": "POST",
                "path": "/people/{id}/tag",
                "label": "Markieren",
                "status": "partial",
                "danger": true,
                "body": { "tag": "intranet" }
            },
            {
                "id": "later",
                "method": "GET",
                "path": "/calendar",
                "label": "Kalender",
                "status": "planned"
            }
        ]
    },
    "settings": [
        {
            "key": "tenant",
            "type": "text",
            "label": "Tenant",
            "required": true
        },
        {
            "key": "client_id",
            "type": "text",
            "label": "Client-ID",
            "required": true
        },
        {
            "key": "client_secret",
            "type": "password",
            "label": "Client-Secret",
            "required": true
        }
    ],
    "actions": [
        {
            "id": "import-people",
            "label": "Personen übernehmen",
            "kind": "import",
            "options": []
        },
        {
            "id": "lookup",
            "label": "Nachschlagen",
            "kind": "read",
            "options": [
                {
                    "key": "email",
                    "type": "text",
                    "label": "E-Mail",
                    "required": true
                }
            ]
        },
        {
            "id": "tag",
            "label": "Markieren",
            "kind": "write",
            "confirm": "Was sich im Fremdsystem ändert."
        }
    ],
    "widgets": [
        {
            "id": "summary",
            "label": "Mein Dienst",
            "ttl": 900,
            "audience": "admin"
        }
    ],
    "events": ["person.created"]
}
```

| Angabe                       | Bedeutung                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `service.base`               | feste Basis-URL; fehlt sie, gilt die Einstellung `base_url` (muss `https://` sein)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `service.hosts`              | weitere Adressen desselben Herstellers (`{"digital": "https://…"}`), je Endpunkt mit `endpoints[].host` gewählt; nur `https://`                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `auth.hosts`                 | Liste der Adressen, an die die Anmeldung geht (z. B. `["digital"]`); ohne Angabe nur an die Hauptadresse – Zugangsdaten gehen nie an andere Server                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `endpoints[].effect`         | `read` oder `write` übersteuert die Methode: Listen per POST sind lesend (ohne Schreibfreigabe), GET-Aufrufe mit Wirkung schreibend                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `unofficial: true`           | keine offizielle Herstellerschnittstelle: Kennzeichen «inoffiziell» und Warnhinweis                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `{name}` in URLs             | wird durch die Einstellung gleichen Namens ersetzt (z. B. `{tenant_id}`)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `auth.type`                  | `none`, `bearer` (`token_setting`), `header` (`setting`, `header`/`header_setting`, `prefix`/`prefix_setting`), `basic` (`username_setting`, `password_setting`), `oauth2_client_credentials` (`token_url`, `scope`, `client_id_setting`, `client_secret_setting`; Token wird bis kurz vor Ablauf zwischengespeichert, relative `token_url` hängt an die Basis-URL), `oauth2_password` (`token_url`, `username_setting`, `password_setting`, `form` = Zusatzfelder Feld → Einstellung, optional `api_key_setting` + `api_key_header`: ist ein Schlüssel hinterlegt, wird er statt des Tokens gesendet) |
| `endpoints[].status`         | `full` umgesetzt, `partial` teilweise/noch zu bestätigen, `planned` geplant (nicht aufrufbar)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `endpoints[].danger`         | schreibend/riskant: Warnsymbol, nur mit Schreibfreigabe und Rückfrage                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| `params[].example`           | **Beispielwert** (Pflicht für jeden Parameter ohne `default`, ausser IDs aus einer vorherigen Antwort): im API-Tester vorausgefüllt und in der API-Ansicht (Scalar)                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `endpoints[].body`           | **Beispiel-Body** (Pflicht für POST/PUT/PATCH): im Tester vorausgefüllt, «Beispiel einfügen», in Scalar als Beispiel                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `endpoints[].fields`         | Felder des Bodys `{name, type, required, help, example, choices}` – im Tester unter «Mögliche Werte», in Scalar als Schema                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| `endpoints[].test: false`    | von «Zugriff prüfen» ausnehmen (z. B. kostenpflichtig)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `settings[].type`            | `text`, `url`, `password` (verschlüsselt), `select` (`choices`), `check`, `number`, `textarea`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `settings[].required_unless` | Schlüssel einer anderen Einstellung: ist sie gesetzt, ist diese Pflichteinstellung nicht nötig (z. B. Token statt Benutzername/Passwort). Gespeicherte Zugangsdaten zeigt das Formular als «********»                                                                                                                                                                                                                                                                                                                                                                                                  |
| `actions[].options[]`        | wie `settings`, zusätzlich `file` (`accept`, z. B. `.xlsx,.csv`): Datei kommt als `UploadedFile` in `$options`, wird nicht gespeichert und beim Übernehmen erneut gesendet                                                                                                                                                                                                                                                                                                                                                                                                                             |
| `actions[].kind`             | `read` (nur anzeigen), `import` (Vorschau → Auswahl → Übernehmen), `write` (ändert im Fremdsystem)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `widgets[].audience`         | `admin` (Standard) oder `all` – Kachel auf der Startseite, `ttl` Sekunden zwischengespeichert                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `events`                     | `person.created`, `person.updated`, `person.deleted`, `announcement.created`, `event.created`, `document.created`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |

### Demo-Modus (`App\Plugins\DemoResponder`)

Ein Plugin, das `DemoResponder` umsetzt, liefert simulierte Antworten, solange `demoActive()` gilt (in der Regel
eine Einstellung `demo`). Dann geht keine Anfrage an den Dienst und keine Anmeldung; Verbindungstest, Aktionen,
«Zugriff prüfen» und API-Tester laufen trotzdem (`$ctx->demo()` zeigt es an). Zugangsdaten mit
`"required_unless": "demo"` sind im Demo-Modus nicht nötig. Beispiel: DeepSign (auf einem Server ausserhalb der
Schweiz immer im Demo-Modus).

### Anbieter für Unterschriften (`App\Plugins\SignatureProvider`)

Ein Plugin, das `SignatureProvider` umsetzt (DeepSign), bedient die Kern-Schnittstelle «Unterschriften»
(`App\Support\Signatures`): senden (mit Unterzeichnenden, Reihenfolge, Position, Beobachtenden), starten, Status,
unterschriebenes PDF, erinnern, zurückziehen, löschen, Link zum Bearbeiten, Webhook prüfen und im Demo-Modus
«nächste Unterschrift». Es gilt das erste eingeschaltete und eingerichtete Plugin. Datei-Uploads gehen über
`$ctx->call($endpunkt, $params, $data, ['name' => …, 'contents' => …])` als multipart/form-data.

### Links und Vorgaben

- `ActionResult::ok(…)->withLink('Öffnen', 'https://…')` zeigt im Ergebnis einen Knopf, der die Adresse in einem neuen
  Tab öffnet (nur `https://`), z. B. «Service-Benutzer erstellen» bei DeepSign.
- `$ctx->setting('x')` liefert ohne gespeicherten Wert die Vorgabe aus `plugin.json` (`settings[].default`); auch
  `{x}` in Adressen und `token_url` wird so ersetzt.

### Plugin.php

```php
namespace Plugins\MeinDienst;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use App\Plugins\Support\PeopleImport;

class Plugin extends BasePlugin
{
    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        $people = (array) $ctx->call('people')->json('value');      // Endpunkt aus plugin.json
        $import = new PeopleImport($ctx->users());
        $rows = $import->plan(array_map(fn ($p) => ['name' => $p['name'], 'email' => $p['mail']], $people), 'Mein Dienst');

        if ($options['_apply'] === true) {                          // zweiter Lauf nach «Übernehmen»
            $done = $import->apply($rows, $options['_selected']);
            return ActionResult::ok("{$done['created']} angelegt, {$done['updated']} geändert.");
        }

        return ActionResult::preview(count($rows).' verglichen.', PeopleImport::columns(), $rows);
    }
}
```

Überschreibbar (alles optional): `test()`, `action()`, `widget()`, `onEvent()`, `status()`.
Standard-`test()` ruft den ersten prüfbaren GET-Endpunkt auf.

### Der Kontext `$ctx`

|                                              |                                                                                                                       |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `$ctx->call($endpoint, $params, $body)`      | Endpunkt aus dem Manifest aufrufen; Pfadparameter `{id}` und Abfrage aus `$params`; nicht-GET nur mit Schreibfreigabe |
| `$ctx->http()`                               | Laravel-HTTP-Client mit Basis-URL, Anmeldung, Zeitlimit, Wiederholung bei 429/503 (`Retry-After`)                     |
| `$ctx->setting($key)` / `$ctx->secret($key)` | Einstellung / entschlüsselte Zugangsdaten (nur serverseitig)                                                          |
| `$ctx->log($text)`                           | Zeile ins Lauf-Protokoll, Zugangsdaten und Bearer-Tokens werden geschwärzt                                            |
| `$ctx->requireWrite()`                       | bricht ab, wenn Schreiben nicht erlaubt ist                                                                           |
| `$ctx->users()`                              | Personenverzeichnis des Intranets (für Übernahmen)                                                                    |
| `$ctx->user`                                 | angemeldete Person, die den Lauf ausgelöst hat (bei Ereignissen ggf. leer)                                            |

Ergebnisse: `ActionResult::ok()`, `::warning()`, `::table($meldung, $spalten, $zeilen)`,
`::preview(...)` (Zeilen mit `_key`, `_change` = neu/ändern/gleich/fehlt dort, optional `_selectable`).
Für Antworten mit unbekannten Feldern baut `Support\Tabular::from($datensätze, $bevorzugt)` die Spalten selbst.

### Regeln

- Übernahmen legen an und ändern – **nie löschen**. Personen nur im Intranet erscheinen als «fehlt dort».
- Schreiben im Fremdsystem nur als Aktion `write` bzw. Endpunkt mit `danger` – mit Rückfrage.
- Fehler eines Plugins brechen nie die Aktion im Intranet ab (Ereignisse laufen danach, Fehler ins Protokoll).
- Ohne offizielle Schnittstelle (`"unofficial": true` im Manifest): nur öffentliche Seiten, keine Anmeldung ohne
  geklärte Nutzungsbedingungen, nur Adressen des Herstellers abfragen (keine frei eingegebenen URLs).
- Endpunkte nur aus der Hersteller-Doku übernehmen; Unbestätigtes als `partial` mit `note`, Fehlendes als `planned`.
- Für jedes Plugin Tests mit `Http::fake()` schreiben (Vorlage: `tests/Feature/PluginIntegrationsTest.php`).
- Endpunkte aus einer OpenAPI-Datei oder einer Aufzeichnung des API-Recorders nicht abtippen, sondern mit
  `php artisan intranet:plugin-from-openapi` übernehmen (`docs/KONZEPT-API-ZU-PLUGIN.md`); Ergebnis ist `partial`.

## Offene Punkte je Plugin

- **ePost:** Endpunkte aus der OpenAPI der Post (`/openapi?query=ePost`, Test api-test.klara-epost.tech,
  Produktion api.epost.ch). Offen: Testkonto (test.klara-epost.tech, Widget «eLetter: Versand»), Versand von
  Elternbriefen als Aktion (PDF/A, multipart, Kanäle DIGITAL/EMAIL/PHYSICAL, Identitätsabgleich), Vertrag mit der Post
  und IDG-Klärung; echte Empfängerdaten erst auf dem Schweizer Server.
- **Inventory360:** Endpunkte seit 0.25.0 aus der API-2.0-Referenz (apidoc, docs.enteksystems.de/inventory360/api/2.0/):
  Token unverändert in «Authorization», Listen mit `per_page` als `{_query, results}`. Offen: Antwortfelder an der
  eigenen Instanz prüfen (Referenz beschreibt sie kaum), Zuordnung Gerät ↔ Person (Felder `owner`/`user_id` der
  Hardware, `GET /users/byEmail/{email}`) für Personen-Abgleich und Kachel – erst auf dem Schweizer Server.
- **Jamf:** Token-Pfad (`/api/v1/oauth/token` oder `/api/oauth/token`) an der eigenen Instanz prüfen
  (Einstellung), `computers-inventory` v4, genaue Rechte für MDM-Befehle.
- **LMVZ (inoffiziell):** Keine offizielle Schnittstelle. LMVZ Digital (digital.lmvz.ch) ist aus einer Aufzeichnung
  übernommen (19 Endpunkte, `partial`): Bedeutung des ersten Pfadwerts der Lizenzübersicht (`license_view`) klären,
  dauerhafte Anmeldung (statt kurzlebigem Browser-Token) beim LMVZ anfragen. Artikelsuche und Warenkorb/Bestellung
  noch aufzeichnen und mit `intranet:plugin-from-openapi … --plugin=lmvz [--host=digital]` ergänzen. Nutzungsbedingungen klären, bevor mit
  Anmeldung gearbeitet wird; Plugins mit `"unofficial": true` zeigen einen Warnhinweis.
- **IDA:** Sobald die IDA-API steht, Endpunkte von `planned` auf `full` setzen und Personen/Lizenzen anbinden.
