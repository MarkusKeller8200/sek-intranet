# Konzept: Plugin DeepSign (elektronische Unterschrift)

Stand 04.11.2026: Plugin-Grundgerüst mit Anmeldung, Demo-Modus und API-Tester umgesetzt (#37); Schnittstelle
«Unterschriften», Webhook und Abholen folgen (#38–#43). Meilenstein «DeepSign – Integration». Quelle:
[DeepSign API Reference](https://apidocs.deepcloud.swiss/deepsign-api-docs/index.html), Swagger unter
`https://api.sign.deepbox.swiss/swagger-ui/index.html`.

## Ziel

Dokumente aus dem Intranet (zuerst das Monats-PDF aus dem Monatsabschluss, #34) elektronisch unterschreiben lassen
und das unterschriebene Dokument wieder bei der Person ablegen. DeepSign ist dabei nur der **Adapter**: Welche
Dokumente wer unterschreibt, entscheidet das Modul, das unterschreiben lässt.

## Aufbau

```
Modul (z. B. Monatsabschluss)
   │  «Bitte unterschreiben: PDF, Unterzeichnende, Reihenfolge»
   ▼
Core-Schnittstelle «Unterschriften» (App\Support\Signatures)
   │  Sammlung signature_requests: Status je Auftrag und Person, Rückmeldung an das Modul (Ereignis)
   ▼
Plugin DeepSign (plugins/Deepsign/)
   │  Anmeldung, Hochladen, Unterzeichnende, Start, Status, Webhook, Download
   ▼
DeepSign (api.sign.deepbox.swiss)
```

- Plugins speichern keine Fachdaten (`PLUGINS.md`). Darum liegt der Auftrag mit Status in einer Core-Sammlung
  `signature_requests`. Das unterschriebene PDF legt das auslösende Modul in seinem eigenen Speicher ab.
- Das Modul erfährt den Abschluss über ein Ereignis (`signatur.abgeschlossen`, `signatur.abgelehnt`) – keine
  Zugriffe quer zwischen Modulen.
- Später könnte ein anderer Anbieter dieselbe Schnittstelle bedienen.

## Was die API kann

| Schritt                             | DeepSign-API                                                                                                                                                            | Im Intranet                                               |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------- |
| Anmelden                            | OAuth Token, `grant_type=password` mit Service-Benutzer und Partner-Client (`deepcloud.swiss/auth/realms/sso/…/token`)                                                  | Zugangsdaten verschlüsselt, Token zwischenspeichern       |
| Dokument hochladen                  | `POST /documents/file` (PDF, DOCX, XLSX bis 40 MB) mit `initiatorAliasName`, `signatureMode`, `jurisdiction`, `sendMail`, `comment`, Callback                           | PDF vom Modul                                             |
| Unterzeichnende                     | `POST /documents/{id}/signees` (`email`, `signOrder`, `comment`), Position über `autographPosition` oder Text-Muster im PDF (`scanPredefined`)                          | Personen aus dem Intranet (E-Mail), Reihenfolge vom Modul |
| Beobachtende                        | `POST /documents/{id}/observers`                                                                                                                                        | z. B. Personalabteilung                                   |
| Starten                             | `PUT /documents/{id}/start`                                                                                                                                             | nach Vorschau                                             |
| Status                              | `GET /documents/{id}` (`draft`, `in-progress`, `signed`, `withdrawn`, `rejected`), `GET /overview`; Abfragen sonst höchstens alle 15 Minuten                            | Status je Auftrag und Person                              |
| Rückmeldung                         | Webhook `signee-completed`, `document-completed` mit HMAC-SHA-256 (`X-DeepSign-Signature-256`, eigenes `callbackSecret` je Dokument). **Keine Wiederholung** bei Fehler | Endpunkt im Intranet + regelmässige Abfrage als Rückfall  |
| Herunterladen                       | `documentUrl` aus den Details (gültig ca. 1 Minute, ohne Authorization-Header)                                                                                          | sofort nach Abschluss abholen und ablegen                 |
| Erinnern                            | `POST /documents/{id}/signees/{signeeId}/resend-invitation`                                                                                                             | Knopf «Erinnerung senden»                                 |
| Zurückziehen / Löschen              | `PUT /documents/{id}/withdraw`, `DELETE /documents/{id}` (nur Entwurf oder zurückgezogen)                                                                               | mit Rückfrage                                             |
| Bearbeiten in DeepSign              | `POST /documents/{id}/document-access-url` (5 Minuten gültig, ohne Login, volle Kontrolle)                                                                              | nur für die Administration                                |
| Signaturstufen                      | `timestamp` (einfach, Standard), `advanced` (fortgeschritten, 2FA), `qualified` (qualifiziert); `zertes` oder `eidas`                                                   | Vorgabe je Dokumentart                                    |
| Firmensiegel, Hash-Signatur, DeepID | vorhanden                                                                                                                                                               | vorerst nicht                                             |

## Wichtige Eigenheiten

- **DeepSign löscht Dokumente:** Entwürfe nach 7 Tagen, unterschriebene, zurückgezogene und abgelehnte 14 Tage nach
  der letzten Statusänderung. Unvollständige Unterschriften werden nach 30 Tagen automatisch zurückgezogen. Das
  Intranet muss das unterschriebene PDF darum **sofort abholen** und selbst aufbewahren.
- **Webhook ohne Wiederholung:** Der Webhook ist nur ein Hinweis. Massgebend ist die Abfrage des Status, die
  zusätzlich regelmässig läuft (Zeitplan im Container nötig).
- **Webhook muss von DeepCloud erreichbar sein:** Der Endpunkt (z. B. `/api/deepsign/callback`) liegt ausserhalb der
  Anmeldung, prüft die Signatur und nimmt nur Dokument-IDs entgegen, die das Intranet selbst kennt. Auf dem
  Testserver (Seitenpasswort) gibt es keinen Webhook; dort nur Abfrage bzw. Demo-Modus.
- **Service-Benutzer mit Passwort:** Anmeldung mit Benutzername/Passwort des Service-Benutzers plus Partner-Client.
  Alle vier Werte als `password`-Einstellung (verschlüsselt, nie anzeigen oder protokollieren).
- **Kein Testsystem dokumentiert.** Ein Demo-Modus mit simulierten Antworten ist nötig.

## Umsetzung (#37)

- `plugins/Deepsign/plugin.json`: 18 Endpunkte, alle mit Beispielwerten; offiziell beschriebene als `full`, aus dem
  Microsoft-Connector bzw. ohne Doku (Unterzeichnende auflisten, Beobachtende entfernen, Bearbeiten-Link) als
  `partial`. Hochladen und «PDF prüfen» brauchen multipart/form-data – im API-Tester nur im Demo-Modus.
- Anmeldung `oauth2_password` gegen `deepcloud.swiss/auth/realms/sso/…/token` mit Service-Benutzer und Partner-Client;
  alle vier Werte verschlüsselt, Token bis kurz vor Ablauf zwischengespeichert.
- **Demo-Modus** (`App\Plugins\DemoResponder`): feste Demo-Dokumente und -Unterzeichnende, Fehler wie bei DeepSign
  (404 unbekannte ID, 400 ungültige E-Mail). Auf einem Server ausserhalb der Schweiz immer eingeschaltet.
- Aktionen «Dokumente anzeigen» und «Signaturstufen anzeigen»; Tests mit `Http::fake()` (Token, 401, 429).

## Datenschutz

Unterschriebene Rapporte sind Personendaten. Echte Dokumente erst in der Produktion bei Infomaniak
(`INTRANET_SERVER_IN_SWITZERLAND=true`); auf dem Testserver nur Demo-Modus. Offen: Serverstandort von DeepCloud,
Vertrag zur Auftragsbearbeitung, Aufbewahrungsfrist der unterschriebenen Dokumente im Intranet.

## Offene Fragen an DeepCloud

1. API-Zugang (`development@deepcloud.swiss`), Service-Benutzer und Partner-Client einrichten; Kosten
2. Signaturstufe für Monatsrapporte (einfach oder fortgeschritten), Identifikation der Unterzeichnenden
3. Gibt es ein Testsystem bzw. eine Sandbox?
4. Serverstandort, Vertrag zur Auftragsbearbeitung, IDG Kanton Zürich
5. Verhalten bei abgelehnter Unterschrift, Sprache der Einladungen (Deutsch)
