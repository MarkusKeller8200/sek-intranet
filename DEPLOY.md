# Deployment: intranet.markussx.ch

Jeder Push auf `main` wird automatisch geprüft und auf den Hostinger-VPS gebracht
(GitHub Actions → SSH → Docker Compose). Traefik auf dem VPS liefert HTTPS (Let's Encrypt).

Zusätzlich geht jeder Push auf den Infomaniak-Server (`intranet-dev.sekbuelach.ch`), sobald das Environment
«dev» eingerichtet ist – Anleitung: [docs/SERVER-INFOMANIAK.md](docs/SERVER-INFOMANIAK.md). Live zieht später
unter `intranet.sekbuelach.ch` dorthin um. Hostinger bleibt davon getrennt als privater Entwicklungsserver unter
`intranet.markussx.ch` (eigene Daten, die bei keinem Deploy überschrieben werden).

```text
git push ─► GitHub Actions: composer ci:check ─► Code per SSH auf VPS ─► docker compose up --build
                                                                    │
Browser ─► intranet.markussx.ch ─► Traefik (HTTPS) ─► Container «intranet» ─► Volume (JSON-Daten, Uploads)
```

| Was                   | Wert                                                                             |
| --------------------- | -------------------------------------------------------------------------------- |
| Server                | Hostinger VPS srv1807739, IP `76.13.156.138`                                     |
| Ordner auf dem Server | `/docker/intranet` (Compose-Datei, `.env`, Quellcode in `src/`)                  |
| Daten                 | Docker-Volume `intranet_intranet-storage` – bleibt bei jedem Deployment erhalten |
| Prüfadresse           | `https://intranet.markussx.ch/up`                                                |

## Einmalige Einrichtung (ca. 30–60 Minuten)

### 1. Lokal vorbereiten (PowerShell im Projektordner)

```powershell
composer setup          # erzeugt composer.lock und package-lock.json
npm run check:fix       # Format/Lint wie in CI
composer ci:check       # muss grün sein, sonst bricht das Deployment ab
```

### 2. GitHub-Repository anlegen

Repository: **`MarkusKeller8200/sek-intranet`** (privat). Für einen neuen Klon ein leeres Repository anlegen
(ohne README). Dann:

```powershell
git init -b main
git add .
git commit -m "IDA-Stack Intranet-Prototyp"
git remote add origin https://github.com/MarkusKeller8200/sek-intranet.git
git push -u origin main
```

Der erste Lauf schlägt beim Schritt «deploy» fehl, bis Schritt 3–5 erledigt sind. Das ist normal.

### 3. Deploy-Schlüssel erstellen

**Variante ohne eigenen PC:** hPanel → VPS → Browser-Terminal:

```bash
ssh-keygen -t ed25519 -N "" -C github-deploy-intranet -f /root/.ssh/intranet-deploy
cat /root/.ssh/intranet-deploy.pub >> /root/.ssh/authorized_keys
cat /root/.ssh/intranet-deploy                      # → Secret VPS_SSH_KEY
ssh-keyscan -t ed25519 76.13.156.138 2>/dev/null    # → Secret VPS_KNOWN_HOSTS
```

Danach kann die private Schlüsseldatei auf dem Server gelöscht werden.

**Variante mit Windows-PC:**

```powershell
ssh-keygen -t ed25519 -C "github-deploy-intranet" -f $HOME\.ssh\intranet-deploy
# Passphrase leer lassen (2× Enter)
ssh-keyscan 76.13.156.138 > $HOME\.ssh\intranet-known-hosts
```

- **Öffentlichen** Schlüssel (`intranet-deploy.pub`) auf dem VPS hinterlegen:
  hPanel → VPS → Einstellungen → SSH-Schlüssel → hinzufügen.
- Der **private** Schlüssel (`intranet-deploy`, ohne .pub) kommt nur in GitHub (Schritt 4),
  nirgends sonst hin.

### 4. GitHub-Einstellungen

Repository → Settings → Secrets and variables → Actions:

| Typ      | Name              | Inhalt                                   |
| -------- | ----------------- | ---------------------------------------- |
| Secret   | `VPS_SSH_KEY`     | ganzer Inhalt von `intranet-deploy`      |
| Secret   | `VPS_KNOWN_HOSTS` | ganzer Inhalt von `intranet-known-hosts` |
| Variable | `VPS_HOST`        | `76.13.156.138`                          |
| Variable | `INTRANET_HOST`   | `intranet.markussx.ch`                   |

### 5. DNS bei Cloudflare

Zone `markussx.ch` → DNS → Eintrag hinzufügen (privater Entwicklungsserver, getrennt von `sekbuelach.ch`):

| Typ | Name       | Inhalt          | Proxy-Status              |
| --- | ---------- | --------------- | ------------------------- |
| A   | `intranet` | `76.13.156.138` | **Nur DNS** (graue Wolke) |

«Nur DNS», damit Traefik das Let's-Encrypt-Zertifikat direkt beziehen kann.
Später kann auf Cloudflare Tunnel/Access umgestellt werden (IDA ADR-005).

### 6. Deployment starten

GitHub → Actions → «deploy» → «Re-run all jobs» (oder einfach erneut pushen).
Nach wenigen Minuten ist die Seite unter <https://intranet.markussx.ch> online.

## Neue Version veröffentlichen

```powershell
git add .
git commit -m "Was geändert wurde"
git push
```

Der Fortschritt ist unter GitHub → Actions sichtbar. Schlägt «ci» fehl, wird nichts veröffentlicht.
Änderungen nur an Markdown-Dateien (`*.md`) lösen kein Deployment aus.

**Schnell ohne Tests** (für Experimente): GitHub → Actions → **deploy-quick** → «Run workflow»,
im Feld «ja» eintippen. Baut und veröffentlicht den aktuellen Stand von `main` ohne `ci`.

| Workflow           | Auslöser                                    | Prüfung             | Zweck                          |
| ------------------ | ------------------------------------------- | ------------------- | ------------------------------ |
| `deploy.yml`       | Push auf `main`, oder von Hand              | `composer ci:check` | Standardweg                    |
| `deploy-quick.yml` | nur von Hand, mit «ja»                      | keine               | schnelle Experimente           |
| `tests.yml`        | Pull Request                                | `composer ci:check` | Prüfung vor dem Zusammenführen |
| `_deploy.yml`      | wird von beiden Deploy-Workflows aufgerufen | –                   | gemeinsame Deployment-Schritte |

## Gemeinsames Passwort aktivieren

Solange kein Passwort gesetzt ist, kann sich **jede Person mit der Adresse als Administration
anmelden** (Demo-Login). Passwort setzen, bevor der Link breit gestreut wird:

hPanel → VPS → Browser-Terminal (oder `ssh root@76.13.156.138`):

```bash
cd /docker/intranet
echo 'INTRANET_ACCESS_PASSWORD=HierEinLangesPasswort' >> .env
docker compose up -d
```

Der Browser fragt danach nach Benutzername (beliebig) und Passwort. Wieder ausschalten:
Zeile in `.env` löschen, `docker compose up -d`.

## Betrieb

| Aufgabe                                         | Befehl auf dem Server (`cd /docker/intranet`)                                                                 |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Status                                          | `docker compose ps`                                                                                           |
| Logs                                            | `docker compose logs -f app`                                                                                  |
| Neu starten                                     | `docker compose up -d`                                                                                        |
| Demo-Daten zurücksetzen (**löscht alle Daten**) | `docker compose exec app php artisan intranet:seed --force`                                                   |
| Datensicherung                                  | `docker run --rm -v intranet_intranet-storage:/d -v $PWD:/b alpine tar czf /b/backup-$(date +%F).tgz -C /d .` |
| Letzte Version zurückholen                      | `rm -rf src && mv src.old src && docker compose up -d --build`                                                |

Die Daten (JSON-Dateien, Uploads, `APP_KEY`) liegen im Volume, nicht im Quellcode.
Ein Deployment überschreibt sie nie.

## Zeitplan und Webhook (Unterschriften)

- Der Container startet `php artisan schedule:work` im Hintergrund (u. a. Abfrage der Unterschriften alle
  15 Minuten). Ausschalten mit `INTRANET_SCHEDULER=false` in `.env`.
- Webhook von DeepSign: `INTRANET_SIGNATURE_WEBHOOK=true` erst, wenn `/api/signaturen/deepsign/callback` von aussen
  erreichbar ist (Schweizer Server). Ohne Webhook genügt die regelmässige Abfrage.

## Hinweise

- Der VPS steht nicht in der Schweiz (IDA ADR-005). Nur erfundene Testdaten verwenden;
  der produktive Betrieb mit Personendaten gehört auf Infomaniak.
- Suchmaschinen sind ausgesperrt (`robots.txt`, `X-Robots-Tag: noindex`).
