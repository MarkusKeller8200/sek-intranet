# Konzept: Fremde Seiten und eigene Anwendungen einbinden

Stand: Version 0.12.0. Frage: Lassen sich die Projekte auf GitHub (websnapshot, abaflow, 3CX-SEKB-TTS,
edu-license-portal) als Modul einbinden, ähnlich wie Plugins?

## Antwort

Ja, aber anders als Plugins. Plugins sind PHP-Code im Intranet (`plugins/<Ordner>/`) und sprechen mit
fremden Schnittstellen. Die GitHub-Projekte sind eigenständige Anwendungen mit eigenem Stack (websnapshot
z. B. Python/Streamlit mit SQLite). Sie werden **nicht in den Code übernommen**, sondern als eigener Dienst
betrieben und im Intranet **eingebunden**: im Rahmen (iframe) und/oder in neuem Tab, mit Anmeldung über das
Intranet. So bleiben die Projekte unabhängig (eigene Releases, eigenes Repository) und das Intranet
bleibt schlank.

| Art          | Beispiel                            | Läuft wo                    | Einbindung                       |
| ------------ | ----------------------------------- | --------------------------- | -------------------------------- |
| Plugin       | Escola-Import, Jamf, IDA            | im Intranet (PHP)           | `plugins/<Ordner>/`              |
| Anwendung    | websnapshot, abaflow, TTS, Lizenzen | eigener Container/Subdomain | `embed` mit `repo`, Adresse .env |
| Fremde Seite | SharePoint, Jira, Confluence        | beim Anbieter               | `embed` mit fester Adresse       |

## Umsetzung im Intranet (ab 0.12.0)

Menüpunkt in `config/intranet.php` → `modules` mit Schlüssel `embed`:

```php
'websnapshot' => [
    'title' => 'Webseiten-Snapshot',
    'icon' => 'Camera',
    'embed' => [
        'url' => env('INTRANET_APP_WEBSNAPSHOT_URL'), // null = noch nicht verbunden
        'open' => 'both',                             // embed | tab | both
        'repo' => 'MarkusKeller8200/websnapshot',
        'stack' => 'Python, Streamlit, SQLite',
    ],
],
```

- Seite `/einbindung/<schlüssel>`: Rahmen mit «Neu laden» und «In neuem Tab öffnen».
- `open: tab` führt im Menü direkt auf die Adresse (neuer Tab).
- Nur `https://`-Adressen. Rahmen mit `sandbox` (Skripte, Formulare, Popups für die Anmeldung).
- Der Server prüft beim Öffnen (6 h zwischengespeichert), ob der Anbieter das Einbetten erlaubt
  (`X-Frame-Options`, `Content-Security-Policy: frame-ancestors`). Ist es verboten, zeigt die Seite
  stattdessen den Knopf für den neuen Tab.
- Ohne Adresse zeigt die Seite Repository, Stack und die Schritte zur Einbindung.

## Fachanwendungen (ab 0.22.0)

**Fachanwendungen** ist ein eigenes Hauptmenü an zweiter Stelle (seit 0.22.2) mit `collapse_sidebar: true`:
Beim Öffnen einer Fachanwendung klappt das Seitenmenü einmal ein, damit die Anwendung die ganze Breite hat; mit
dem Symbol lässt es sich jederzeit wieder öffnen. `collapse_sidebar` geht auch für Untermenüs (`items` innerhalb
eines Menüpunkts).

| Fachanwendung        | Adresse                                                                                                 | Einbindung                                             |
| -------------------- | ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| CMI                  | `.env` `INTRANET_APP_CMI_URL`                                                                           | Rahmen, falls erlaubt, sonst neuer Tab                 |
| Escola               | `https://www.sekbuelach.ch/escola/login` (`.env` `INTRANET_APP_ESCOLA_URL` übersteuert)                 | Rahmen, falls erlaubt, sonst neuer Tab                 |
| Apple School Manager | `https://school.apple.com`                                                                              | immer neuer Tab (Apple verbietet das Einbetten)        |
| Jamf Pro             | `.env` `INTRANET_APP_JAMF_URL`                                                                          | Rahmen, falls erlaubt, sonst neuer Tab (Daten: Plugin) |
| SL-Planer            | `https://slplaner.ch/my/users/sign_in?open_modal=true` (`.env` `INTRANET_APP_SLPLANER_URL` übersteuert) | immer neuer Tab (X-Frame-Options: SAMEORIGIN)          |

Escola sendet `Content-Security-Policy: … frame-ancestors 'self'` (geprüft am 31.10.2026, Server Apache, kein
Cloudflare). `'self'` heisst: nur Seiten derselben Adresse (`https://www.sekbuelach.ch`) dürfen Escola einbetten.
Das Intranet (`https://intranet.sekbuelach.ch`) ist eine andere Adresse – der Browser zeigt den Rahmen leer.
Lösung: eigener Tab oder Browser-Erweiterung; für einen Rahmen müsste der Betreiber von www.sekbuelach.ch/Escola
`frame-ancestors 'self' https://intranet.sekbuelach.ch` setzen. `embed.allowed: true` nur verwenden, wenn die
Kopfzeilen das Einbetten wirklich erlauben (Prüfung unter Einstellungen → «Einbetten prüfen»). Bessere Lösung als ein Rahmen wäre eine Anmeldung per SSO
(Edulog/Microsoft) mit Escola als eigener Tab; Daten aus Escola kommen ohnehin über das Plugin «Escola».

## Einstellungen (ab 0.23.0)

Adresse und Art des Öffnens jeder Einbindung lassen sich unter **Einstellungen → Fachanwendungen** bzw.
**Weitere Einbindungen** ändern (Sammlung `module_settings`, übersteuert Konfiguration und `.env`). Öffnen:
«iFrame – eingebettet», «iFrame – neuer Tab» (Rahmen mit Knopf), «Neuer Browser-Tab», «Neues
Browser-Fenster» (eigenes Fenster statt Tab, ab #150), «Browser-Erweiterung (neuer Tab)» oder «Browser-Erweiterung
(gesplittetes Tab)». Beim neuen Fenster lassen sich Breite und Höhe in % des sichtbaren Anzeigebereichs
setzen, beim gesplitteten Tab die Trennlinie in % von links (#151; Felder `width`, `height`, `split`)
([KONZEPT-BROWSER-CONNECTOR.md](KONZEPT-BROWSER-CONNECTOR.md)).

## Erwartung bei den fremden Seiten

SharePoint Online und Atlassian Cloud (Jira Service Management, Confluence) erlauben das Einbetten in fremde
Seiten in der Regel nicht. Sie öffnen dann automatisch über den Knopf in einem neuen Tab; das ist gewollt
und sicher (Anmeldung bei Microsoft bzw. Atlassian bleibt beim Anbieter).

## Schritte für eine eigene Anwendung

1. Projekt als Container bauen (Dockerfile im Projekt), auf dem Schweizer Server betreiben (Infomaniak,
   ADR-005), eigene Subdomain, z. B. `snapshot.intranet.sekbuelach.ch`.
2. Anmeldung über das Intranet: Traefik leitet nur angemeldete Personen weiter (Forward-Auth gegen einen
   Intranet-Endpunkt, der Name, E-Mail und Rolle als Kopfzeilen mitgibt). Die Anwendung braucht kein eigenes
   Login.
3. Die Anwendung erlaubt das Einbetten durch das Intranet:
   `Content-Security-Policy: frame-ancestors https://intranet.sekbuelach.ch`.
   Streamlit: Aufruf mit `?embed=true` blendet Menü und Rahmen der Anwendung aus.
4. Adresse in der Server-`.env` eintragen (`INTRANET_APP_WEBSNAPSHOT_URL=https://…`).
5. Optional später: Datei `intranet-app.json` im Projekt (Name, Symbol, Adresse, Health-Check, benötigte
   Rolle). Das Intranet liest sie wie ein Plugin-Manifest und zeigt den Zustand unter Prototyp → Plugins.

## Offene Punkte

- websnapshot ist als lokale Anwendung gebaut (Windows/macOS). Für den Serverbetrieb: Datenordner als Volume,
  eine gemeinsame Datenbank für alle oder je Person?
- abaflow, 3CX-SEKB-TTS und edu-license-portal sind private Repositories; Stack und Betrieb noch zu klären.
- Sichtbarkeit: Fachstelle ICT ist heute nur für die Administration sichtbar; Verwaltung für alle.
