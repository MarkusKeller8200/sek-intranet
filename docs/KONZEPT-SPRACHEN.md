# Konzept Mehrsprachigkeit

Stand: Version 0.11.0 (Prototyp). Vorbild: TagStudio (Übersetzungsdateien je Sprache, Text als Schlüssel,
Sprachwahl in den Einstellungen, Fallback auf die Ausgangssprache).

## Sprachen

| Sprache       | Code (BCP 47)   | HTML `lang` | Hinweise                                                       |
| ------------- | --------------- | ----------- | -------------------------------------------------------------- |
| Deutsch       | `de-CH`         | `de-CH`     | Standard. «ss» statt «ß», Datum TT.MM.JJJJ. Text = Schlüssel.  |
| Englisch      | `en`            | `en`        | Britisches Englisch, schulnahe Begriffe.                       |
| Züritüütsch   | `gsw-u-sd-chzh` | `gsw-CH`    | Dieth-Schreibung, locker aber verständlich. Fachbegriffe bleiben. |

## Funktionsweise

- Im Code stehen nur deutsche Texte. Übersetzt wird beim Anzeigen durch eine Schicht über dem DOM
  (`resources/js/lib/i18n.ts`): Ein `MutationObserver` ersetzt Textknoten und die Attribute `title`,
  `placeholder`, `aria-label`, `alt`. Der Ausgangstext wird gemerkt; zurück auf Deutsch geht ohne Neuladen.
- Kataloge: `resources/js/i18n/<bereich>.json`, Eintrag `"Deutsch": ["English", "Züritüütsch"]`.
  Fehlt eine Übersetzung, bleibt der deutsche Text (Fallback).
- Zahlen werden beim Nachschlagen zu `{n}` und danach wieder eingesetzt («12 Einträge» → `"{n} Einträge"`).
- Zusammengesetzte Texte («Teil · Teil», «Titel: Rest», «… …») werden auch in Teilen übersetzt.
- Für Texte ausserhalb des DOM (Seitentitel, `confirm`) gibt es `t('…')`.
- Daten werden nicht übersetzt: Zellen der Tabellen (ausser Auswahlwerten und Ja/Nein), Detailwerte, Titel und
  Texte von Mitteilungen und Terminen, Changelog-Einträge stehen in `translate="no"`. Das verhindert auch,
  dass Browser-Übersetzer Namen verändern.
- Sprache je Browser in `localStorage` (`lang`). Ist sie nicht Deutsch, verbirgt `app.blade.php` die Seite bis
  zur ersten Übersetzung (höchstens 1,5 s), damit kein Deutsch aufblitzt.

## Bedienung

- Einstellungen → Darstellung → Sprache (mit Flaggen, Namen in der eigenen Sprache).
- Unten in der Seitenleiste eine kompakte Auswahl.
- Prototyp → Sprachkonzept: Abdeckung je Sprache, Beispiele, in diesem Browser fehlende Texte.

## Pflege

1. Neuen Text im Code auf Deutsch schreiben.
2. Im selben Commit in den passenden Katalog eintragen (Englisch und Zürichdeutsch).
3. `node tools/i18n-extract.mjs` meldet fehlende Einträge (`--all` zeigt alle, `--json` maschinenlesbar).
   Wörter, die nie übersetzt werden (Eigennamen), kommen in `ignore.json`.
4. `I18nCatalogTest` prüft: gültiges JSON, je Eintrag zwei nicht leere Übersetzungen, gleich viele `{n}`.

## Grenzen und später

- Inhalte (Mitteilungen, Dokumente) bleiben einsprachig. Bei Bedarf später Felder je Sprache im Schema.
- Serverseitige Texte (Validierungsmeldungen, E-Mails) sind noch deutsch; bei Bedarf Laravel-`lang/`-Dateien.
- Für ein Produktivsystem eine Fachperson die Zürichdeutsch- und Englischtexte gegenlesen lassen.
