# Modul Beschaffung

Kataloge unter **Bestellen** (#197, #198). Alle Listen haben denselben Aufbau (Sammlung `bes_artikel`); fehlende
Angaben werden später ergänzt.

- **Hardware:** Zimmerausrüstung; persönliche Ausrüstung für Lehrpersonen, Schüler:innen, Schulverwaltung, Hausdienst
- **Plattformen:** Schulunterricht (inkl. Fachunterricht Musik), Schuladministration, Fachstelle ICT,
  Fachanwendungen Schulbetrieb und Schulverwaltung
- **Software – Apps:** Mobile Apps (Jamf)
- **Material – PICTS / TICTS**
- Lehrmittel und «Antrag neue HW/SW» bleiben Platzhalter.

## Felder

Bild, Gruppe/Kategorie, Bestellnummer, Bezeichnung, Modell, Beschreibung, Preis CHF, Kostenstufe, Hinweis zum Preis,
Zubehör, Standort, Menge, Zugang, Nutzerkreis, Verantwortung, Lizenzen, Webseite, ICT-Wiki, weitere Angaben, Status
(aktiv, Entwurf, gestrichen), **Prüfen** mit Hinweis. Je Katalog sind andere Spalten anfangs sichtbar
(`Kataloge::ALLE` → `spalten`); alle lassen sich in der Tabelle ein- und ausblenden.

## Rechte

| Recht              | Vorgabe                                                     |
| ------------------ | ----------------------------------------------------------- |
| `view-beschaffung` | Administration, Schulleitung, Verwaltung, ICT, Lehrpersonen |
| `edit-beschaffung` | Administration, ICT                                         |

## Startinhalt

`startinhalt/artikel.json` wird beim ersten Zugriff in den Speicher des Moduls geschrieben. Quelle: ICT-Wiki
(Confluence), ausgelesen am 11.11.2026, bereinigt gemäss Entscheid der Projektleitung:

1. Auffälligkeiten sind mit «Prüfen» und einem Hinweis markiert.
2. Hausdienst: nur noch Lenovo.
3. Schuladministration: doppelte Bestellnummer neu vergeben (VSA – Puls → SEKB-SW-5150), Abraxas FIS und doodle
   ergänzt (5160, 5170).
4. Schulunterricht: Tabelle «ab SJ26/27» samt Fachunterricht Musik; AirServer gestrichen.
5. Entwürfe (Administration, Fachstelle ICT) übernommen, Status «Entwurf».
6. Mobile Apps: alle Einträge aus Jamf, ohne Personenangaben; Bild und Preis aus dem App Store (Schweiz), wo
   eindeutig gefunden.
7. Material: alles übernommen, offene Angaben markiert; Namen der Zuständigen nicht übernommen.

Bilder des Startinhalts liegen unter `startinhalt/bilder` (App-Symbole, 128 px) und werden aus dem Intranet
ausgeliefert – nicht von fremden Servern. Hochgeladene Bilder liegen im Speicher `local` unter `beschaffung/bilder`.

Erzeugt mit `startinhalt/quellen/startinhalt.py` aus den ausgelesenen Listen und `apps.tsv` (Jamf-Export ohne
Personenangaben); App-Symbole mit `itunes.py`. Die Dateien dienen nur der Nachvollziehbarkeit – gepflegt wird im
Intranet.
