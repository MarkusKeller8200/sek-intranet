# Konzept: Issues, Versionen und Releases (Intranet)

Der allgemeine Ablauf – Issues mit Freigabe, Status-Labels, Meilensteine, Versionen und Releases – kommt aus dem
[GitHub-Blueprint](https://github.com/MarkusKeller8200/my-github-blueprint) und steht in
[GITHUB-ABLAUF.md](GITHUB-ABLAUF.md). Konfiguration: `.github/blueprint.toml`. Hier steht nur, was im Intranet
dazukommt oder abweicht.

## 1. Meldungen

- Zusätzliche Art-Labels: `plugin`, `gui` (eigene Vorlagen «Plugin-Idee», «GUI-Baustein»), `sicherheit`, `doku`,
  `barrierefreiheit`.
- Die Vorlagen unter `.github/ISSUE_TEMPLATE/`, die PR-Vorlage, `CONTRIBUTING.md` und `SECURITY.md` sind auf das
  Intranet zugeschnitten (IDG, Testdaten, Bereiche) und werden vom Blueprint nicht überschrieben.
- Meilensteine verwalten: per REST-API oder mit Kommentaren `/milestone …` auf Issue #1
  (`.github/blueprint/MILESTONE-ADMIN.md`).

### Brücke Intranet → GitHub

Lehrpersonen haben in der Regel kein GitHub-Konto, und das Repository ist privat. Darum bleiben Wünsche im Intranet
(GUI-Prototypen › Wünsche). Mit dem Recht `manage-users` erscheint der Knopf **«Als GitHub-Issue vorbereiten»**:
Er öffnet das passende Formular vorausgefüllt (Baustein, freigegebene Version, Wunschtext, Label `triage`).
Abgeschickt wird es von Hand. Es werden keine Namen übertragen. Auf der Seite «Versionen» füllen die Knöpfe
«Fehler melden» und «Idee vorschlagen» die laufende Version vor. Das Repository steht in `INTRANET_GITHUB_REPO`;
leer = Knöpfe ausblenden.

## 2. Versionen

- Solange 0.x: Prototyp. MAJOR 1.0.0 = erster Betrieb mit echten Daten auf Infomaniak.
- Bis Version 0.31.0 wurde bei jeder Änderung erhöht; ab 0.32.0 gilt der Blueprint-Ablauf.
- Module (`modules/*/module.json`) und Plugins (`plugins/*/plugin.json`) haben eigene Versionen. Wer sie ändert,
  erhöht `version`.

### Developer Release

Jeder Push auf `main` wird geprüft und ausgerollt. Ein Stand mit Commits nach dem Tag `v<VERSION>` ist ein
**Developer Release**: Seitenleiste und Seite «Versionen» zeigen z. B. «0.31.0 · Developer Release (+5)» und das
Ziel. Das Ziel ist die nächste Patch-Version, wenn unter «Unveröffentlicht» nur «Behoben» steht, sonst die nächste
Minor-Version. Das Deployment übergibt `git describe --tags --long` als `INTRANET_BUILD_DESCRIBE`. Die
Aktualisierungsmeldung im Browser zeigt bei einem Developer Release die unveröffentlichten Änderungen.

## 3. Release

Ablauf wie in [GITHUB-ABLAUF.md](GITHUB-ABLAUF.md), mit dem PHP-Werkzeug des Intranets:

```bash
php tools/release.php --check            # Version, CHANGELOG, Plugin-Versionen
php tools/release.php 0.33.0 --commit    # VERSION + CHANGELOG «[0.33.0] – Datum», Commit «Version 0.33.0»
git push
```

Der Workflow **release** wartet, bis **deploy** für diesen Commit grün ist (`release_nach`), und legt dann Tag und
GitHub-Release «Intranet 0.33.0» an. Ist deploy nicht grün (z. B. Formatfehler), holt der nächste grüne Push das
Release nach.

### Prüfungen

- `php tools/release.php --check` läuft in `composer ci:check`: `VERSION` ist gültiges SemVer, der CHANGELOG hat
  «Unveröffentlicht» und einen Abschnitt für die Version, jedes Plugin hat eine gültige `version` und eine höhere,
  wenn es seit dem letzten Tag geändert wurde.
- Der Release-Workflow (`.github/blueprint/release.py check`) prüft dasselbe zusätzlich für die Module.

### Bestehende Versionen

Die Tags `v0.1.0` und `v0.6.0` bis `v0.31.0` sind rückwirkend gesetzt (`.github/release-backfill.txt`). Der
`GITHUB_TOKEN` darf keine Tags auf alte Commits setzen, weil diese andere Workflow-Dateien enthalten. Darum ging das
Nachtragen in zwei Schritten: Tags lokal setzen und pushen, danach Actions → **release** → Run workflow mit
«nachtragen» (legt nur die Releases an).

```bash
grep -v '^#' .github/release-backfill.txt | while read v s; do git tag "v$v" "$s"; done; git push origin --tags
```

Die Versionen 0.1.0 und 0.6.0 bis 0.10.0 wurden erst mit 0.11.0 beschrieben. Ihre Tags sind rekonstruiert: jeweils
der letzte Commit vor den Funktionen der nächsten Version, nach Commit-Meldungen und CHANGELOG. Versionen 0.2 bis
0.5 gibt es nicht.
