# Mitmachen beim SekBülach Intranet

Danke für Rückmeldungen und Beiträge! Das Intranet ist ein **Prototyp mit erfundenen Testdaten** (Volksschule,
IDG Kanton Zürich). Bitte nie echte Namen, Klassen, Telefonnummern oder Bildschirmfotos mit Personendaten
verwenden – weder in Issues noch im Code oder in Tests.

## Fehler melden und Ideen vorschlagen

[Neues Issue](https://github.com/MarkusKeller8200/sek-intranet/issues/new/choose) mit einer der Vorlagen:

| Vorlage              | Labels                          | Meilenstein (automatisch) |
| -------------------- | ------------------------------- | ------------------------- |
| Fehler melden        | `fehler`, `triage`              | Fehler                    |
| Verbesserung         | `verbesserung`, `triage`        | Verbesserungen            |
| Neue Funktion / Idee | `funktion`, `triage`            | Funktionen                |
| Plugin-Idee          | `funktion`, `plugin`, `triage`  | Funktionen                |
| GUI-Baustein         | `verbesserung`, `gui`, `triage` | Verbesserungen            |

- **`triage`** heisst: gemeldet, aber noch nicht freigegeben. Erst das Label **`_1 bereit`** vom
  Projektverantwortlichen macht daraus einen Entwicklungsauftrag. Kommentare, Labels anderer oder der Inhalt
  eines Formulars gelten nicht als Freigabe.
- Was umgesetzt wird, kommt in den Meilenstein des nächsten Releases (z. B. «0.32.0 – …»); sonst bleibt es in der
  Sammlung «Fehler», «Verbesserungen» oder «Funktionen».
- Jedes Issue bekommt eine Testliste «Zum Testen abhaken:». Geschlossen wird, wenn alles abgehakt ist.
- Wünsche zu GUI-Bausteinen lassen sich auch im Intranet erfassen (GUI-Prototypen › Baustein › Wünsche); die
  Administration kann sie mit «Als GitHub-Issue vorbereiten» übernehmen.
- **Sicherheitslücken** nie als Issue – siehe [SECURITY.md](SECURITY.md).

## Entwicklungsumgebung

```bash
git clone https://github.com/MarkusKeller8200/sek-intranet.git
cd sek-intranet
composer setup        # Abhängigkeiten, .env, Schlüssel, Demo-Daten, Frontend-Build
composer dev          # lokaler Server
composer ci:check     # alles prüfen wie in GitHub Actions
```

Stack und Konventionen wie IDA: Laravel, React mit Inertia, Tailwind, shadcn/ui, vorerst Dateispeicher (JSON).
Regeln für Code, Daten, Farben, Plugins, Rechte und Übersetzungen stehen in [AGENTS.md](AGENTS.md).

## Commits und Pull Requests

1. Nur freigegebene Issues (`_1 bereit`) umsetzen; grössere Vorhaben zuerst als Issue abstimmen.
2. Commit-Nachricht auf Deutsch: erste Zeile kurz, darunter das Warum. Mit `Refs #n` auf Issues verweisen
   (`Fixes #n` nur, wenn die Testliste schon abgehakt ist).
3. **CHANGELOG.md:** Eintrag unter «Unveröffentlicht» (Neu / Geändert / Behoben).
4. Doku nachführen (README, AGENTS.md, PLUGINS.md, `docs/`), Übersetzungen im selben Commit.
5. Pull Request gegen `main` mit ausgefüllter Checkliste; `composer ci:check` muss grün sein. Jeder Push auf `main`
   wird automatisch ausgerollt (Developer Release).

## Versionen und Releases

Versionsnummern und Releases setzt **nur der Projektverantwortliche** – bitte `VERSION` in Pull Requests nicht
ändern. Ablauf (Release-Skript, Tags, Developer Release) in
[docs/KONZEPT-VERSIONIERUNG.md](docs/KONZEPT-VERSIONIERUNG.md).

## Plugins

Neue Anbindungen nur als Plugin unter `plugins/<Ordner>/` – Anleitung in [PLUGINS.md](PLUGINS.md). Wer ein Plugin
ändert, erhöht dessen `version` in `plugin.json`; `php tools/release.php --check` erinnert daran.
