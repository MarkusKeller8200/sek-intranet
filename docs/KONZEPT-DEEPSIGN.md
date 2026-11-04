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

## Umsetzung (#38–#43)

**Kern-Schnittstelle «Unterschriften»** (`App\Support\Signatures`, Sammlung `signature_requests`, Recht
`manage-signatures`):

- `create()` legt den Auftrag an, lädt das PDF beim Anbieter hoch (multipart: `data` + `file`), fügt Unterzeichnende
  in Reihenfolge (`signOrder`, Position `autographPosition`) und Beobachtende hinzu und startet ihn. Das PDF wird im
  Kern nicht gespeichert.
- Status: entwurf → versendet → teilweise → unterschrieben / abgelehnt / zurueckgezogen / abgelaufen (von DeepSign
  nach 30 Tagen zurückgezogen) / fehler. Je Person: wartet (Reihenfolge), offen, unterschrieben, abgelehnt.
- **Ereignisse an das Modul** (`Signatures::listen('<modul>', fn ($ereignis, $auftrag, $pdf) => …)` im
  ServiceProvider des Moduls): `signatur.unterschrieben`, `signatur.abgeschlossen` (mit PDF; das Modul gibt `true`
  zurück, wenn es abgelegt ist), `signatur.abgelehnt`.
- Anbieter über `App\Plugins\SignatureProvider` (DeepSign setzt es um); der Kern kennt DeepSign nicht.
- **Webhook** `POST /api/signaturen/{anbieter}/callback` (ausserhalb der Anmeldung): Signatur
  `X-DeepSign-Signature-256` = HMAC-SHA-256 des Inhalts mit dem Geheimnis des Auftrags (hex, optional `sha256=`),
  nur bekannte Dokument-IDs, Rate-Limit; danach fragt das Intranet den Status selbst ab. Nur aktiv mit
  `INTRANET_SIGNATURE_WEBHOOK=true` (Vorgabe: wie `INTRANET_SERVER_IN_SWITZERLAND`).
- **Regelmässige Abfrage** `php artisan intranet:signatures-refresh` alle 15 Minuten (`routes/console.php`, im
  Container über `schedule:work`, ausschaltbar mit `INTRANET_SCHEDULER=false`): offene Aufträge abfragen,
  unterschriebene sofort abholen und dem Modul übergeben. Kann ein unterschriebenes PDF 24 Stunden lang nicht
  abgelegt werden, geht eine Benachrichtigung an `manage-signatures` (DeepSign löscht nach 14 Tagen).
- **Seite «Unterschriften»** (Verwaltung): Filter nach Modul, Monat und Status; Erinnern, Zurückziehen, Löschen
  (mit Rückfrage), Status aktualisieren, «In DeepSign öffnen» (nur Administration, Link 5 Minuten gültig); im
  Demo-Modus «Demo: nächste Unterschrift». Alle Aktionen im Änderungsverlauf und im Plugin-Protokoll.
- Für das Senden braucht DeepSign «Schreibende Aktionen erlauben».

**Zeiterfassung (#43):** Nach dem Visum sendet die Schulleitung den Monatsrapport zur Unterschrift (Reihenfolge:
Person zuerst, Schulleitung zuerst oder gleichzeitig; optional zur Kenntnis an die Personalabteilung). Das PDF
entsteht auf dem Server (`App\Support\SimplePdf`, `Modules\Zeiterfassung\MonatsblattPdf`) mit zwei
Unterschriftsfeldern, deren Position an DeepSign geht. Das unterschriebene PDF liegt im Speicher des Moduls und ist
für die Person und das Visum herunterladbar.

**Offen bis zum echten Zugang:** Einheit und Ursprung von `autographPosition` (Punkte ab oben links angenommen),
genaues Format des Webhooks und der Signatur, Antwort von `document-access-url`. Im Demo-Modus ist der Ablauf
vollständig testbar; mit echtem Zugang auf dem Schweizer Server prüfen.

## Dokument von Hand senden (#86)

- **Verwaltung → Unterschriften → «Dokument zur Unterschrift senden»** (Recht `manage-signatures`) bzw. Plugin-Aktion
  «Dokument zur Unterschrift senden» (Unterzeichnende als Zeilen «Name; E-Mail»): PDF bis 20 MB, Unterzeichnende
  nacheinander oder gleichzeitig, optional zur Kenntnis an und Nachricht. Läuft über `Signatures::sendManual()`
  (Modul `unterschriften`); ohne festes Unterschriftsfeld platzieren die Unterzeichnenden ihre Unterschrift selbst.
- Das unterschriebene PDF legt der Kern unter `storage/app/private/signatures/<auftrag>.pdf` ab
  (`Signatures::manualEvent`); Download unter `/unterschriften/{id}/pdf`.
- Swagger-Doku von DeepSign als Link auf der Plugin-Seite (`service.swagger`).

## Alle Endpunkte (#85)

Quelle: OpenAPI-Beschreibung von DeepSign (Swagger `api.sign.deepbox.swiss/swagger-ui`, 53 Operationen, am 04.11.2026 von
Markus übergeben). Alle 53 stehen im Plugin (Status «umgesetzt»), dazu `users/me` beim Anmeldedienst – je mit
Beispielwerten, Feldern («Mögliche Werte») und Demo-Antwort:

| Bereich                    | Endpunkte                                                                                                                                             |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Konto                      | DeepSign-Benutzer lesen/ändern, Siegel, Identifikation je Stufe, Signaturstufen, Unterschrift (Bild) hochladen/löschen, Vorschau                      |
| Dokumente                  | Übersicht (mit Suche `q`), hochladen (Datei oder DeepBox), Details, ändern, starten, zurückziehen, löschen, Link zum Bearbeiten, PDF prüfen           |
| Umschlag (mehrere Dateien) | Datei hinzufügen, lesen, umbenennen, entfernen, Reihenfolge                                                                                           |
| Unterzeichnende            | auflisten, hinzufügen, lesen, ändern, entfernen, erinnern                                                                                             |
| Gruppen und Reihenfolge    | Gruppe (nötige Unterschriften), Unterschriftsfeld je Datei, Reihenfolge der Gruppen, Reihenfolge lesen/festlegen/aufheben, Absender:in zuerst/zuletzt |
| Beobachtende               | hinzufügen, ändern, entfernen                                                                                                                         |
| Anhänge                    | auflisten, hinzufügen, umbenennen, entfernen, Reihenfolge, Download-Link                                                                              |
| Service                    | Hash unterschreiben/versiegeln, Datei unterschreiben/versiegeln, XML versiegeln (XAdES), Status, Download                                             |

**Geklärt durch die Beschreibung** (vorher offen):

- **Position** (`Position`): Punkte (72 dpi), x/y = linke **untere** Ecke des Felds, gemessen ab der linken unteren
  Ecke der Seite. Das Monatsblatt rechnet die Felder entsprechend um.
- **Unterzeichnende** brauchen `signeeType` (`sign` oder `approve`) und `fileSignatureFields` (Schlüssel = `fileId`
  aus `files[]` der Dokumentdetails, Wert = `{autographPositions: [...]}`); `autographPosition` ist veraltet.
- **Hochladen** braucht `sendMail` und `visualSignatureRequirement`.
- **Zurückziehen** braucht einen Body mit `sendMail`.
- **Download:** `files[].fileUrl` (`documentUrl` ist veraltet).
- **Link zum Bearbeiten:** Antwort ist die Adresse als Text.
- **Webhook** (`CallbackInfo`): `eventType` (`signee-completed`, `document-completed`), `documentId`, `signeeId`,
  `timestamp`; Signatur im Kopf `X-DeepSign-Signature-256` (HMAC-SHA-256 des Inhalts mit `callbackSecret`).

## Endtest auf dem Testserver (#84)

Auf einem Server ausserhalb der Schweiz ist der Demo-Modus erzwungen. Für den Endtest mit echtem Zugang gibt es den
Schalter **«Endtest»** (Demo-Modus aus, Endtest an): Dann gehen echte Aufrufe an DeepSign, die Plugin-Karte zeigt
«ENDTEST». Nur mit erfundenen Dokumenten und der eigenen E-Mail als Unterzeichnende:r testen und danach wieder
ausschalten. Im API-Tester lässt sich bei «Dokument hochladen» eine Datei wählen (multipart: `data` + `file`).

## Zugang einrichten (#83)

Quelle: [API Access Setup](https://apidocs.deepcloud.swiss/deepsign-api-docs/setup_overview.html).

1. **Partner-Registrierung (einmalig):** Anfrage an development@deepcloud.swiss. DeepCloud gibt `client_id` und
   `client_secret` (Methode Client-Secret; alternativ JWKS – noch nicht umgesetzt). Übergabe der Service-Benutzer:
   «Manual Copy-Paste» (empfohlen) oder Callback-URL (Redirect-URI muss registriert sein).
2. **Service-Benutzer erstellen:** Aktion «Service-Benutzer erstellen» öffnet
   `https://admin.deepbox.swiss/third-party-connection/?response_type=service_account&client_id=…&response_mode=copy_paste`
   (Test: `admin.int.deepbox.swiss`). Die Schule meldet sich mit ihrem DeepCloud-Konto an und bestätigt. Benutzername
   (`…@in.deepcloud.swiss`) und Passwort erscheinen **nur einmal** und kommen in die Einstellungen.
3. **Token:** `grant_type=password` mit Service-Benutzer und Partner-Client (`deepcloud.swiss` bzw.
   `int.deepcloud.swiss`). Token gilt 15 Minuten und wird zwischengespeichert.
4. **Prüfen:** «Verbindung testen» ruft die Übersicht und `GET https://deepcloud.swiss/api/v1/users/me` (Service-Benutzer
   und Firmen) auf.

- **Testumgebung vorhanden:** `int.deepcloud.swiss` / `admin.int.deepbox.swiss`; Testkonten akzeptieren eine
  Test-Kreditkarte. Die API-Adresse der Testumgebung ist nicht dokumentiert → bei DeepCloud erfragen (Einstellung
  «API-Adresse»).
- Später auf dem Schweizer Server: Übergabe per `response_mode=form_post` an eine registrierte Redirect-URI (Felder
  `state`, `service_account_username`, `service_account_password` …), Anmeldung per JWKS statt `client_secret`.

## Datenschutz

Unterschriebene Rapporte sind Personendaten. Echte Dokumente erst in der Produktion bei Infomaniak
(`INTRANET_SERVER_IN_SWITZERLAND=true`); auf dem Testserver nur Demo-Modus. Offen: Serverstandort von DeepCloud,
Vertrag zur Auftragsbearbeitung, Aufbewahrungsfrist der unterschriebenen Dokumente im Intranet.

## Offene Fragen an DeepCloud

1. API-Zugang (`development@deepcloud.swiss`), Service-Benutzer und Partner-Client einrichten; Kosten
2. Signaturstufe für Monatsrapporte (einfach oder fortgeschritten), Identifikation der Unterzeichnenden
3. ~~Gibt es ein Testsystem bzw. eine Sandbox?~~ Ja (`int.deepcloud.swiss`); offen: API-Adresse der Testumgebung
4. Serverstandort, Vertrag zur Auftragsbearbeitung, IDG Kanton Zürich
5. Verhalten bei abgelehnter Unterschrift, Sprache der Einladungen (Deutsch)
