# API-Recorder

Stand: Version 0.17.0. Prototyp → API-Recorder (nur Administration).

## Zweck

Eine Website (z. B. eine Fachanwendung) im eigenen Fenster bedienen, die dabei gesendeten Anfragen und Antworten
mitschneiden, ausgewählte Endpunkte – auf Rückfrage – in eine API-Dokumentation aufnehmen und am Ende eine
OpenAPI-3.1-Datei (Swagger, JSON oder YAML) erzeugen.

## Ablauf

1. Adresse eingeben → «Fenster öffnen» (eigenes Browserfenster).
2. Im Fenster einmal das Lesezeichen **«SBI-Recorder»** anklicken (vorher einmalig aus dem Intranet in die
   Lesezeichenleiste ziehen). Unten rechts erscheint ein roter Zähler.
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
  E-Mails ersetzt, Felder wie Passwort/Token/PIN als `***`).
- Kopfzeilen wie `Authorization`, `Cookie`, Tokens werden in der Anzeige maskiert und nie gespeichert.
- Nur eigene oder freigegebene Websites aufzeichnen; Nutzungsbedingungen beachten.

## Technik

- `resources/js/lib/api-recorder/capture.ts`: Lesezeichen-Skript, HAR-Import, Prüfung der Meldungen.
- `resources/js/lib/api-recorder/openapi.ts`: Pfad-Vorlagen (Zahlen, UUIDs, lange IDs → `{id}`), Schema-Ableitung
  und -Zusammenführung, Maskierung, OpenAPI-3.1-Aufbau, YAML-Ausgabe.
- `App\Http\Controllers\ApiRecorderController`: Seite, Speichern/Löschen der Dokumentationen (Recht `manage-users`).
