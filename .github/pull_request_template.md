## Was ändert sich?

<!-- Kurz beschreiben, was der Pull Request tut und warum. -->

Refs #
<!-- bzw. Fixes # – verknüpftes Issue. Umgesetzt wird nur, was mit `_1 bereit` freigegeben ist. -->

## Art der Änderung

- [ ] Fehlerbehebung (`fehler`)
- [ ] Verbesserung bestehender Funktion (`verbesserung`)
- [ ] Neue Funktion (`funktion`)
- [ ] Plugin
- [ ] GUI-Baustein / Farbkonzept
- [ ] Dokumentation / Build / CI

## Wie getestet?

<!-- z. B. neue Tests in tests/Feature/…, Seiten im Browser mit welcher Rolle durchgeklickt -->

## Checkliste

- [ ] `composer ci:check` läuft lokal ohne Fehler (Format, TypeScript, Pint, PHPStan, PHPUnit, Übersetzungen, Versionen)
- [ ] Neue oder geänderte Funktionen haben Tests
- [ ] Nur erfundene Testdaten – keine echten Personendaten (IDG)
- [ ] Neue Texte in allen Sprachen (`resources/js/i18n/*.json`), Daten mit `translate="no"`
- [ ] Neue Felder im Schema (`config/intranet.php` → `schema`), Oberfläche aus freigegebenen GUI-Bausteinen
- [ ] Eintrag in `CHANGELOG.md` unter «Unveröffentlicht» (Neu / Geändert / Behoben)
- [ ] Bei Plugin-Änderung `version` in `plugin.json` erhöht
- [ ] Doku nachgeführt (README, AGENTS.md, PLUGINS.md, `docs/`)
- [ ] `VERSION` nicht geändert (Versionen setzt der Projektverantwortliche)

## Bildschirmfotos

<!-- bei Änderungen an der Oberfläche – nur mit Testdaten -->
