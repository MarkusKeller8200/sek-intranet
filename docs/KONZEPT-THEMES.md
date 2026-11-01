# Konzept Themes (Farbkonzepte, ab 0.29.0)

Das Farbkonzept (`/farbkonzept`) hat eine **Theme-Auswahl**: Farbsätze für hell und dunkel speichern, ansehen und
für alle aktivieren. So lassen sich Varianten (z. B. Hoher Kontrast, Farben eines Schulhauses) ausprobieren, ohne den
Code zu ändern.

## Bedienung (nur Administration)

- **Themes** im Farbkonzept: Liste mit Farbmustern hell/dunkel, Kennzeichen «aktiv» und «Vorgabe».
  - **Vorschau:** färbt nur die Seite Farbkonzept und nur für dich (nichts gespeichert); unten Musterseite hell und dunkel.
  - **Für alle aktivieren:** gilt sofort im ganzen Intranet (alle Personen, hell und dunkel).
  - **Bearbeiten**, **Kopieren**, **Löschen** (mit Rückfrage). Wird das aktive Theme gelöscht, gilt wieder der Standard.
  - **Neues Theme:** leer oder aus einer Vorlage (Hoher Kontrast, Petrol, Violett).
- **Theme bearbeiten** (`/farbkonzept/themes/{id}/bearbeiten`): Name, Beschreibung, Farben in Gruppen (Marke &
  Hauptfarbe, Flächen & Text, Akzent & Neben, Status, Seitenleiste, Diagramme) je hell und dunkel mit Farbwähler oder
  `#rrggbb`. Leer = Vorgabe. **Kontrastprüfung** (WCAG AA, 4,5 : 1) für die wichtigen Schrift/Fläche-Paare und
  Live-Vorschau hell und dunkel.
- **GUI-Prototypen:** Leiste «Vorschau» über den Beispielen jedes Bausteins mit **Hell / Dunkel** und **Theme** –
  nur Anzeige, ändert nichts für andere. Dialoge, Popover und Menüs erscheinen in den Farben des Intranets.

## Regeln

- «SekBülach (Standard)» ist `resources/css/app.css` und lässt sich nicht ändern oder löschen; Änderungen daran nur im
  Code. Gespeicherte Themes enthalten nur Abweichungen.
- Nur bekannte Farbwerte (`App\Support\Themes::GROUPS`) und nur `#rrggbb` werden gespeichert und ausgegeben.
- Ein Theme gilt erst nach bestandener Kontrastprüfung als brauchbar (die Prüfung warnt, sperrt aber nicht).
- Farben in Seiten weiterhin nur über die Farbwerte (`bg-primary`, `text-muted-foreground` …), nie fest im Code –
  sonst wirkt ein Theme dort nicht.

## Technik

- Sammlung `themes` (name, description, light, dark, active), Logik `App\Support\Themes`, `ThemeController`.
- Vorgaben liest der Server aus `resources/css/app.css` (`:root` und `.dark`).
- Aktives Theme: `<style id="intranet-theme">` im `<head>` (`resources/views/app.blade.php`), übersteuert `:root` und
  `.dark`.
- Vorschau in einem Teilbereich (`components/theme-scope.tsx`): setzt `--name` und `--color-name` (Tailwind löst
  `--color-*` am `<html>` auf) und für dunkel die Klasse `dark`.
- Vorlagen: `config/intranet.php` → `theme_presets`.
