# PWA – Intranet als App

Stand: Version 0.15.0.

## Was es kann

- Installieren auf Computer (Chrome, Edge), Android und iPhone/iPad: eigenes Fenster, Symbol auf dem
  Startbildschirm, Kurzbefehle Mitteilungen, Termine, Personen.
- Knopf «App installieren» unten in der Seitenleiste (nur sichtbar, wenn der Browser es anbietet und die App noch
  nicht installiert ist) und unter Einstellungen → Darstellung → App. Auf iPhone/iPad erscheint eine Anleitung
  (Teilen → «Zum Home-Bildschirm»), weil Safari keinen Installationsdialog kennt.
- Ohne Verbindung erscheint eine Offline-Seite.

## Datenschutz

- Der Service Worker (`public/sw.js`) speichert **keine Seiten und keine API-Antworten**. Zwischengespeichert
  werden nur die unveränderlichen Build-Dateien (`/build/…`, mit Hash im Namen), Symbole, Schriften und die
  Offline-Seite. Personendaten liegen also nie im Gerätespeicher der App.
- Seitenaufrufe gehen immer an den Server; Anmeldung und Rechte gelten unverändert.

## Technik

- `public/manifest.webmanifest` (Name, `lang: de-CH`, `display: standalone`, Farben, Symbole 192/512 inkl.
  «maskable»), verlinkt in `resources/views/app.blade.php` mit `theme-color` und iOS-Angaben.
- Symbole in `public/icons/` aus `favicon.svg` erzeugt.
- `resources/js/lib/pwa.ts`: registriert `/sw.js?v=<build>`; jede neue Version (Build aus dem Deployment)
  bekommt einen eigenen Cache, alte werden beim Aktivieren gelöscht. Zusammen mit der Aktualisierungsmeldung lädt
  die App nach einem Deployment die neuen Dateien.
- Apache: `.webmanifest` als `application/manifest+json`, `sw.js` mit `Cache-Control: no-cache`.
- Statische Dateien liefert Apache direkt aus; das Zugangspasswort des Prototyps (Laravel) gilt für sie nicht.
  Sie enthalten keine Daten.

## Später

- Push-Mitteilungen (Web Push) für wichtige Mitteilungen – braucht Einwilligung und Server-Schlüssel.
- Offline lesbar nur für öffentliche Inhalte (z. B. Notfallnummern), nach Klärung Datenschutz.
