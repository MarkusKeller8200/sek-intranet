# Konzept: Browser-Connector (Fremde Webanwendungen ohne API)

Stand: Version 0.23.0. Grundlage: «Integrationsarchitektur für fremde Webanwendungen» (Konzept V1.1),
übertragen auf das SekBülach-Intranet. Ergänzt [KONZEPT-ANWENDUNGEN.md](KONZEPT-ANWENDUNGEN.md),
[KONZEPT-API-RECORDER.md](KONZEPT-API-RECORDER.md) und [KONZEPT-API-ZU-PLUGIN.md](KONZEPT-API-ZU-PLUGIN.md).

## Drei Wege – was das Intranet heute kann

| Weg (Konzept V1.1)       | Im Intranet                                                                                            | Stand            |
| ------------------------ | ------------------------------------------------------------------------------------------------------ | ---------------- |
| A – Offizielle API       | Plugins (`plugins/<Ordner>/`), Zugangsdaten verschlüsselt auf dem Server                               | umgesetzt        |
| B – Oberfläche einbetten | Einbindungen/Fachanwendungen (`embed`), Prüfung `X-Frame-Options`/`frame-ancestors`                    | umgesetzt        |
| C – Browser-Connector    | Stufe 1: Browser-Erweiterung «SBI-Connector» (dieser Prototyp); Stufe 2: Browser-Dienst auf dem Server | Stufe 1 Prototyp |

Priorität bleibt: zuerst offizielle API, Einbetten nur zur Darstellung, Browser-Connector nur für zugelassene
Abläufe ohne geeignete API. Eine Aufzeichnung macht eine interne Schnittstelle nicht zu einer freigegebenen API.

## Einstellung je Fachanwendung

Menü **Einstellungen → Fachanwendungen** bzw. **Weitere Einbindungen** (Sammlung `module_settings`):

| Öffnen                                     | Wert        | Verhalten                                                                                          |
| ------------------------------------------ | ----------- | -------------------------------------------------------------------------------------------------- |
| Im Intranet (iFrame)                       | `embed`     | nur Rahmen                                                                                         |
| Im Intranet (iFrame) mit Knopf «Neuer Tab» | `both`      | Rahmen und Ausweg in neuen Tab (Standard)                                                          |
| Eigenes Fenster (neuer Tab)                | `tab`       | Menü führt direkt auf die Adresse                                                                  |
| Über die Browser-Erweiterung               | `extension` | Erweiterung öffnet einen eigenen Tab, gruppiert mit dem Intranet; ohne Erweiterung Hinweis und Tab |

Dazu die Adresse (nur `https://`). Gespeicherte Werte übersteuern `config/intranet.php` und `.env`; «Vorgabe
wiederherstellen» entfernt den Eintrag. Änderungen stehen im Änderungsverlauf (`audit_log`).

## Stufe 1: Browser-Erweiterung «SBI-Connector» (Prototyp 0.23.0)

Ordner `browser-extension/` (Manifest V3, Edge und Chrome). Download unter **Einstellungen →
Browser-Erweiterung**; das Intranet setzt beim Download seine Adresse und Version ins Manifest.

```
Intranet-Tab ──postMessage──► intranet.js ──runtime──► background.js ──tabs/scripting──► Tab der Fachanwendung
   ▲  (source: sbi-intranet)   (Inhaltsskript,          (Service Worker,                  capture (Hauptwelt)
   │                            nur Intranet)            Zustand in storage.session)       relay (isolierte Welt)
   └──────────── postMessage (source: sbi-connector) ◄──────────────────────────────────── Meldungen der Anfragen
```

- **Erkennung:** `<html data-sbi-connector="<version>">` im Intranet (`resources/js/lib/connector.ts`).
- **Fachanwendung öffnen:** `{source: 'sbi-intranet', action: 'open', url}` → neuer Tab neben dem Intranet,
  Tab-Gruppe «Intranet». Nur `https://`.
- **Aufzeichnen:** Im Tab der Website Symbol der Erweiterung → «Aufnahme starten». Der Browser fragt einmal je
  Website nach der Freigabe (`optional_host_permissions`). Die Erweiterung hängt sich an `fetch`/XHR (gleiche
  Erfassung wie das Lesezeichen) und meldet jede Anfrage an alle offenen API-Recorder-Seiten. Läuft nach dem
  Neuladen weiter, bis «Aufnahme beenden». Funktioniert auch dort, wo Seiten Lesezeichen-Skripte blockieren.
- **Recorder:** meldet sich bei der Erweiterung an (`action: 'recorder'`) und nimmt Meldungen aus dem eigenen
  Fenster mit Kennzeichen `sbi-connector` an.

### Sicherheit und Datenschutz

- Zugriff fest nur auf das Intranet; andere Websites nur nach Freigabe der Person im Browser, je Website.
- Keine Passwörter, Tokens oder Cookies werden gespeichert oder an den Server geschickt; die Anmeldung bei der
  Fachanwendung (inkl. MFA, Edulog) bleibt im eigenen Browser.
- Aufgezeichnete Anfragen gehen nur an den API-Recorder im gleichen Browser; gespeichert wird nur, was dort
  gespeichert wird (Endpunkte, Schemas, Beispiele maskiert).
- Nachrichten werden nur aus dem eigenen Fenster mit gleichem Ursprung angenommen; Meldungen nur aus Tabs, deren
  Aufnahme die Person gestartet hat.
- Verteilung produktiv über Intune (Edge/Chrome-Richtlinie «ExtensionInstallForcelist»), nicht über den Store.

### Verbindung Intranet ⇄ Tab (ab 0.24.0)

| Funktion | Befehl | Wirkung | Grenzen |
| --- | --- | --- | --- |
| Abfragen | `context` | Adresse und Titel des Tabs; IDs daraus → Personen über `PersonLinks` (System aus `embed.system`) | Adresse wird nicht gespeichert; Schüler:innen nur mit `view-students` |
| Melden | (automatisch) | Seitenwechsel im Tab aktualisieren die Anzeige im Intranet | nur Tabs, die das Intranet geöffnet hat |
| Abruf | `fetch` | lesende Plugin-Endpunkte derselben Adresse, mit der Anmeldung der Seite (`page-hook.js` merkt sich die Kopfzeile, die die Seite selbst sendet) | nur gleiche Adresse, nur GET bzw. `effect: read`; Kopfzeile verlässt die Seite nie; nur `use-ict` |
| Steuern | `navigate`, `focus`, `fill` | Seite derselben Anwendung öffnen, Tab nach vorne, Feld ausfüllen | nie absenden |
| Übernehmen | `table` | Tabelle als Vorschau und CSV | nur im Browser; Import erst auf dem Schweizer Server |
| Nebeneinander | `split` | zwei Fenster, je halber Bildschirm | Fensterverwaltung des Browsers |

Nachrichten: Seite → `{source: 'sbi-intranet', action: 'tab', id, key, command, args}`, Antwort
`{source: 'sbi-connector', type: 'reply', id, result}`; Meldungen `type: 'context'`. Umsetzung:
`resources/js/lib/connector.ts`, `components/tab-connection.tsx`, `ConnectorController`,
`browser-extension/{background,intranet,page-hook,page-relay}.js`.

### Nächste Schritte (Stufe 1)

1. Verteilung über Intune vorbereiten (eigene Erweiterungs-ID, Update-URL auf dem Schweizer Server).
2. Freigegebene Abläufe als «Aktionen» der Erweiterung, z. B. LMVZ Digital: Lizenzübersicht im angemeldeten
   Browser abrufen und an das Plugin übergeben – ersetzt das kurzlebige Token in den Plugin-Einstellungen.
   Nur lesend, nur nach Klärung der Nutzungsbedingungen.
3. Herkunft der übernommenen Daten über `PersonLinks` bzw. Plugin-Läufe festhalten.

## Stufe 2: Browser-Dienst auf dem Server (später)

Für Abläufe ohne Person (z. B. nächtlicher Export) – Playwright in eigenem Container, wie im Konzept V1.1:

- je Verbindung und Person ein eigener Browserkontext, keine geteilten Cookies;
- interaktive Anmeldung über eine eingebettete Fernansicht (Bildübertragung, Eingabesteuerung) – aufwendig;
- Sitzungen liegen auf dem Server → nur auf dem Schweizer Server (Infomaniak, ADR-005), nie auf dem Prototyp;
- Warteschlange mit eindeutigen Jobs, Wiederholungen, Konflikten (`sync_jobs`, `sync_conflicts`) erst mit der
  Datenbank (PostgreSQL).

Erst bauen, wenn ein konkreter Ablauf ohne Person nötig ist.

## Grenzen

- Keine Umgehung von Captchas, MFA, Sperren oder anderen Schutzmassnahmen.
- Interne Schnittstellen können sich jederzeit ändern; Plugins daraus sind `unofficial` und `partial`.
- Schreiben in Fremdsysteme erst nach Tests und ausdrücklicher Freigabe (`allow_write`).
