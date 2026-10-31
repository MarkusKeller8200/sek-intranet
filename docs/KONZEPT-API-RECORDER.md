# API-Recorder

Stand: Version 0.18.0. Prototyp → API-Recorder (nur Administration).

## Zweck

Eine Website (z. B. eine Fachanwendung) im eigenen Fenster bedienen, die dabei gesendeten Anfragen und Antworten
mitschneiden, ausgewählte Endpunkte – auf Rückfrage – in eine API-Dokumentation aufnehmen und am Ende eine
OpenAPI-3.1-Datei (Swagger, JSON oder YAML) erzeugen.

## Verwaltung

- Prototyp → API-Recorder zeigt alle aufgezeichneten APIs (Name, URL, Endpunkte, Plugin, zuletzt aufgezeichnet).
- «Neue API aufzeichnen» (`/api-recorder/neu`): Name, URL, Plugin, Basis-URL, Beschreibung, dann aufzeichnen.
  Sobald ein Name gesetzt ist, speichert der Recorder Änderungen automatisch.
- «Öffnen & weiter aufzeichnen» (`/api-recorder/{id}`) lädt die gespeicherten Endpunkte. Neue Aufrufe bekannter
  Endpunkte ergänzen nur das Schema; nur wirklich neue Endpunkte lösen eine Rückfrage aus. So lassen sich fehlende
  Befehle jederzeit nachträglich aufzeichnen.
- Zuweisung zu einem Plugin und Weg zum Plugin: siehe [KONZEPT-API-ZU-PLUGIN.md](KONZEPT-API-ZU-PLUGIN.md).

## Ablauf

1. Adresse eingeben → «In neuem Tab öffnen». Bewusst ein normaler Tab und kein Pop-up: Pop-ups zeigen keine
   Lesezeichenleiste. Die Verbindung zum Recorder (`window.opener`) bleibt auch im Tab bestehen.
2. Im Tab einmal das Lesezeichen **«SBI-Recorder»** anklicken (vorher einmalig aus dem Intranet in die
   Lesezeichenleiste ziehen; Leiste mit Ctrl+Shift+B einblenden). Unten rechts erscheint ein roter Zähler.
   Ohne Lesezeichen: «Code kopieren», im Tab F12 → Konsole, einfügen, Enter (Chrome verlangt beim ersten Mal
   «allow pasting»).
3. Funktionen der Website bedienen. Jede `fetch`-/XHR-Anfrage erscheint mit Methode, Adresse, Status, Typ, Dauer;
   aufgeklappt mit Anfrage (Kopfzeilen, Inhalt) und Antwort.
4. Bei jedem **neuen Endpunkt** fragt der Recorder nach: Pfad (IDs als `{id}`), Beschreibung, Gruppe →
   Aufnehmen, Ignorieren oder Später. Weitere Aufrufe desselben Endpunkts ergänzen das Schema automatisch.
5. Dokumentation speichern (Titel, Basis-URL, Beschreibung) und **OpenAPI-Datei** herunterladen.

Alternative: **HAR-Datei importieren** (Entwicklertools → Netzwerk → «Alle als HAR speichern»). Nötig, wenn eine
Website Lesezeichen-Skripte blockiert (Content-Security-Policy) oder ganze Seitenaufrufe/Formulare interessieren.

## Warum ein Lesezeichen?

Browser erlauben einer Seite nicht, den Netzwerkverkehr einer fremden Website (anderer Ursprung) mitzulesen –
weder im Rahmen noch im Fenster. Das Lesezeichen-Skript läuft in der Website selbst, hängt sich an `fetch` und
`XMLHttpRequest` und meldet jede Anfrage per `postMessage` an das Recorder-Fenster im Intranet. Der Recorder nimmt
nur Meldungen des selbst geöffneten Fensters an. Ein Server-Browser (Proxy, Fernsteuerung) wurde bewusst nicht
gewählt: Anmeldedaten würden über den Server laufen.

## Datenschutz

- Die Aufzeichnung bleibt im Browser; nichts davon geht an den Server.
- Gespeichert (Sammlung `api_docs`) werden nur Methode, Pfad, Abfrageparameter, abgeleitete Schemas, Status und
  Beschreibungen. Beispielwerte nur, wenn «Beispielwerte übernehmen» gewählt ist – dann maskiert (Texte gekürzt,
  E-Mails ersetzt, Felder wie Passwort/Token/PIN als `***`). Kurze Werte bleiben nur als Codes (z. B. `ASC`, `CH`);
  Felder mit Personenbezug (Name, Benutzer, E-Mail, Telefon, Adresse, Ort, PLZ) werden immer gekürzt – auch kurze
  Nachnamen (seit 0.21.0; ältere Aufzeichnungen mit Beispielwerten neu speichern).
- Kopfzeilen wie `Authorization`, `Cookie`, Tokens werden in der Anzeige maskiert und nie gespeichert.
- Nur eigene oder freigegebene Websites aufzeichnen; Nutzungsbedingungen beachten.

## Technik

- `resources/js/lib/api-recorder/capture.ts`: Lesezeichen-Skript, HAR-Import, Prüfung der Meldungen.
- `resources/js/lib/api-recorder/openapi.ts`: Pfad-Vorlagen (Zahlen, UUIDs, lange IDs → `{id}`), Schema-Ableitung
  und -Zusammenführung, Maskierung, OpenAPI-3.1-Aufbau, YAML-Ausgabe.
- `App\Http\Controllers\ApiRecorderController`: Liste, Editor, Speichern/Löschen, Plugin-Entwurf (Recht `manage-users`).
- `App\Support\ApiDocs`: Sammlung `api_docs`, Plugin-Katalog, Abgleich mit dem Manifest, Endpunkt-Entwurf.
