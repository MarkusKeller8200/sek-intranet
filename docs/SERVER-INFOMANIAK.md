# Infomaniak-Server: Einrichtung und Betrieb

Eigener Server bei Infomaniak (Schweiz) für das Intranet, bis Live später auf ein eigenes VPS umzieht.
Meilenstein «Hosting Infomaniak»: #170 (Grundeinrichtung), #171 (Dev), #172 (Sicherung), #173 (Live).

| Was                | Wert                                                                     |
| ------------------ | ------------------------------------------------------------------------ |
| Server             | Infomaniak VPS Lite `sekbuelach-dev`, 4 vCPU, 8 GB, Ubuntu 26.04 LTS     |
| Dev                | `https://intranet-dev.sekbuelach.ch` – Ordner `/docker/intranet-dev`     |
| Live               | `https://intranet.sekbuelach.ch` – Ordner `/docker/intranet-live`        |
| Reverse Proxy      | Traefik in `/docker/traefik` (Let's Encrypt, HTTP → HTTPS)               |
| Deploy             | GitHub Actions per SSH als Benutzer `deploy` (Environment `dev`)         |
| Sicherung          | restic nach Swiss Backup, täglich 02:30, Test sonntags 05:15             |
| Hostinger (bisher) | `https://hostinger.sekbuelach.ch` – läuft weiter, bis Live umgezogen ist |

```text
git push ─► GitHub Actions: CI ─┬─► Hostinger  (Environment production) ─► hostinger.sekbuelach.ch
                                └─► Infomaniak (Environment dev)        ─► intranet-dev.sekbuelach.ch
```

**Regeln:** Auf Dev nur erfundene Testdaten und ein Zugangspasswort; `INTRANET_SERVER_IN_SWITZERLAND=true` und echte
Plugin-Zugangsdaten nur auf Live (der Deploy bricht ab, wenn der Schalter auf Dev gesetzt ist). Zugangsdaten nie
ins Repository oder in einen Chat.

## Einrichtung (einmal, ca. 45 Minuten)

### 1. Firewall im Infomaniak-Manager

Eingehend nur **22, 80 und 443** (TCP) erlauben. Das Skript richtet zusätzlich ufw auf dem Server ein.

### 2. DNS bei Cloudflare

Zone `sekbuelach.ch` → DNS:

| Typ | Name           | Inhalt                    | Proxy-Status |
| --- | -------------- | ------------------------- | ------------ |
| A   | `intranet-dev` | IP des Infomaniak-Servers | **Nur DNS**  |
| A   | `hostinger`    | `76.13.156.138`           | **Nur DNS**  |

Bei IPv6 zusätzlich einen AAAA-Eintrag. «Nur DNS», damit Traefik das Zertifikat selbst holt.

### 3. Deploy-Schlüssel erzeugen

Ein eigener Schlüssel nur für GitHub Actions, nicht der persönliche. Am einfachsten in **1Password**: Neues Objekt →
SSH-Schlüssel → Neuen Schlüssel generieren → **Ed25519**, z. B. «GitHub Deploy Infomaniak».

- Öffentlicher Schlüssel → beim Skript als `--deploy-key` (Schritt 5)
- Privater Schlüssel → nur in GitHub (Schritt 6): im Eintrag «Privaten Schlüssel kopieren → **OpenSSH-Format**»
  (das Standardformat PKCS#8 kann SSH nicht lesen)

Ohne 1Password (PowerShell): `ssh-keygen -t ed25519 -C github-deploy-infomaniak -f $HOME\.ssh\infomaniak-deploy`
(Passphrase leer lassen – GitHub Actions kann keine eingeben).

Der persönliche Schlüssel für die eigene Anmeldung (Benutzer `ubuntu`) wird beim Erstellen bzw. Neuinstallieren
des VPS im Infomaniak-Manager angegeben: eigenen öffentlichen Schlüssel einfügen, nicht generieren lassen. Ein
nachträglich im Manager hinzugefügter Schlüssel kommt nicht auf einen bestehenden Server. Mit dem SSH-Agent von
1Password genügt danach `ssh ubuntu@<IP des Servers>`.

### 4. Skripte auf den Server bringen

Die Dateien `docker/server/setup.sh` und `docker/server/backup.sh` aus dem Repository herunterladen
(GitHub → Datei → «Download raw file») und kopieren:

```powershell
scp setup.sh backup.sh <Benutzer>@<IP des Servers>:~
```

`<Benutzer>` ist der Standardbenutzer des Infomaniak-Images (z. B. `ubuntu`).

### 5. Grundeinrichtung ausführen

```bash
ssh <Benutzer>@<IP des Servers>
sudo bash setup.sh --deploy-key 'ssh-ed25519 AAAA… github-deploy-infomaniak' --acme-email <Adresse für Let's Encrypt>
```

Das Skript fragt nach dem **Zugangspasswort für Dev** (mindestens 16 Zeichen, wird nicht angezeigt). Danach:
Zeitzone Europe/Zurich, Updates, Docker, Benutzer `deploy`, ufw, fail2ban, automatische Sicherheitsupdates
(Neustart bei Bedarf um 04:00), Traefik, SSH nur mit Schlüssel.

**Wichtig:** Vor dem Schliessen der Sitzung in einem zweiten Fenster prüfen, dass die Anmeldung noch geht.

Das Skript kann wiederholt werden. Docker wird bewusst **nicht** automatisch aktualisiert: ab und zu von Hand
`sudo apt update && sudo apt upgrade` und danach einen Deploy laufen lassen.

### 6. GitHub-Environment «dev»

Repository → Settings → Environments → **New environment** `dev`
(Deployment branches: nur `main`):

| Typ      | Name              | Inhalt                                                            |
| -------- | ----------------- | ----------------------------------------------------------------- |
| Variable | `VPS_HOST`        | IP des Infomaniak-Servers                                         |
| Variable | `VPS_USER`        | `deploy`                                                          |
| Variable | `INTRANET_HOST`   | `intranet-dev.sekbuelach.ch`                                      |
| Secret   | `VPS_SSH_KEY`     | ganzer Inhalt von `infomaniak-deploy`                             |
| Secret   | `VPS_KNOWN_HOSTS` | Zeile vom Ende des Einrichtungsskripts (`<IP> ssh-ed25519 AAAA…`) |

Die Zeile für `VPS_KNOWN_HOSTS` gibt das Skript am Ende aus; sonst auf dem Server:
`awk '{print "<IP>", $1, $2}' /etc/ssh/ssh_host_ed25519_key.pub`. (`ssh-keyscan` unter Windows ist oft zu alt
für Ubuntu 26.04.) Der Deploy vergleicht den Fingerabdruck und zeigt bei einer Abweichung beide an.

Solange `VPS_HOST` fehlt, überspringt der Deploy das Ziel «dev» (Hinweis im Lauf).

**Hostinger:** Im Environment `production` bzw. in den Repository-Variablen `INTRANET_HOST` auf
`hostinger.sekbuelach.ch` setzen. Der Deploy trägt den Hostnamen bei jedem Lauf neu in die `.env` ein.

### 7. Erster Deploy

GitHub → Actions → **deploy** → «Run workflow». Nach einigen Minuten ist Dev unter
<https://intranet-dev.sekbuelach.ch> erreichbar (der Browser fragt nach dem Zugangspasswort).

### 8. Sicherung

```bash
sudo bash backup.sh install
sudo nano /etc/sekbuelach/backup.env      # Swiss-Backup-Zugang eintragen
sudo cat /etc/sekbuelach/restic-passwort  # einmal lesen → Passwort-Manager
sudo backup.sh init
sudo backup.sh                            # erste Sicherung
sudo backup.sh pruefen                    # Wiederherstellungstest
```

- **Swiss Backup:** im Infomaniak Manager einen Zugang (S3) anlegen; Endpunkt, Bucket und Schlüssel in
  `backup.env`. Bietet der Zugang nur Swift oder rclone, `RESTIC_REPOSITORY` entsprechend setzen
  (`swift:…` bzw. `rclone:…`, siehe restic-Dokumentation).
- **Was gesichert wird:** `INSTANZEN` in `backup.env`, Standard `intranet-live`. Zum Ausprobieren vorher
  `INSTANZEN="intranet-dev"` setzen (nur Testdaten), nach dem Umzug von Live wieder umstellen.
- **Ablauf:** App kurz anhalten (wenige Sekunden), Volumen lokal kopieren, wieder starten, dann verschlüsselt
  hochladen; ohne Logs und Zwischenspeicher, mit der `.env` der Instanz.
- **Aufbewahrung:** 14 tägliche, 8 wöchentliche, 24 monatliche, 5 jährliche Stände.
- **Überwachung:** Status in `/var/lib/sekbuelach/backup-status`; jeder Deploy zeigt eine Warnung, wenn die
  letzte Sicherung fehlgeschlagen oder älter als 36 Stunden ist. Optional `PING_URL` für einen
  Überwachungsdienst. Ab 50 GB (einstellbar) meldet der Status, dass das Repository prüfen ist.
- **Ohne das restic-Passwort ist keine Wiederherstellung möglich** – darum zusätzlich im Passwort-Manager.

## Betrieb

| Aufgabe                    | Befehl                                                                                    |
| -------------------------- | ----------------------------------------------------------------------------------------- |
| Status Dev                 | `cd /docker/intranet-dev && docker compose ps`                                            |
| Logs Dev                   | `cd /docker/intranet-dev && docker compose logs -f app`                                   |
| Traefik-Logs               | `cd /docker/traefik && docker compose logs --tail=100`                                    |
| Letzte Version zurückholen | `cd /docker/intranet-dev && rm -rf src && mv src.old src && docker compose up -d --build` |
| Sicherungsstände           | `sudo backup.sh liste`                                                                    |
| Wiederherstellen           | `sudo backup.sh zurueck intranet-live [Stand]` (fragt nach, sichert vorher)               |
| Gesperrte IPs (fail2ban)   | `sudo fail2ban-client status sshd`                                                        |
| Firewall                   | `sudo ufw status`                                                                         |

Die Daten liegen im Docker-Volumen `<Instanz>_intranet-storage` (z. B. `intranet-dev_intranet-storage`);
ein Deploy überschreibt sie nie.

## Live (#173)

`https://intranet.sekbuelach.ch`, Instanz `intranet-live`, Ordner `/docker/intranet-live` – auf demselben Server
wie Dev, mit eigenen Daten, eigener `.env` und eigenem Container. Workflow `deploy-live.yml`:

- **Nur Releases:** läuft, sobald `release.yml` einen Tag `v<Version>` anlegt, und deployt genau diesen Stand.
  Von Hand: Actions → **deploy-live** → «Run workflow» mit einer Version (auch zum Zurückgehen).
- **Freigabe:** Das Environment `live` verlangt die Freigabe durch Markus; ohne sie wartet der Lauf.
- **Sicherung vorher:** Ist `backup.sh` eingerichtet (`init`), sichert der Deploy zuerst; schlägt das fehl, bricht er
  ab. Mit echten Daten deployt er nie ohne Sicherung.
- **Schutz:** Mit `INTRANET_SERVER_IN_SWITZERLAND=true` muss `INTRANET_DEMO_LOGIN=false` gesetzt sein, sonst bricht
  der Deploy ab. Ohne echte Daten warnt er, wenn kein Zugangspasswort gesetzt ist.

### Einrichtung

1. **Zugangspasswort** für die Demo-Phase (in 1Password erzeugen), auf dem Server:
   `sudo -u deploy nano /docker/intranet-live/.env` → Zeile `INTRANET_ACCESS_PASSWORD=…`
2. **GitHub-Environment `live`** (Settings → Environments → New environment):

    | Typ      | Name              | Inhalt                                  |
    | -------- | ----------------- | --------------------------------------- |
    | Variable | `VPS_HOST`        | IP des Infomaniak-Servers               |
    | Variable | `VPS_USER`        | `deploy`                                |
    | Variable | `INTRANET_HOST`   | `intranet.sekbuelach.ch`                |
    | Secret   | `VPS_SSH_KEY`     | derselbe Deploy-Schlüssel wie bei `dev` |
    | Secret   | `VPS_KNOWN_HOSTS` | dieselbe Zeile wie bei `dev`            |

    Dazu **Required reviewers**: Markus; **Deployment branches and tags**: nur `main` (der Lauf startet auf `main`
    und holt dann den Stand des Tags).

3. **DNS bei Cloudflare:** `intranet` → IP des Infomaniak-Servers, «Nur DNS».
4. **Erster Deploy:** Actions → **deploy-live** → «Run workflow» mit der aktuellen Version → Freigabe erteilen.
   Live startet zuerst mit Demo-Daten (wie Hostinger).

### Vor echten Daten

1. Auftragsverarbeitungsvertrag mit Infomaniak.
2. Sicherung eingerichtet (`sudo backup.sh init`) und einmal wiederhergestellt (`sudo backup.sh pruefen`).
3. Demo-Daten ersetzen bzw. Live neu aufsetzen (Vorgehen beim Wechsel festlegen).
4. In `/docker/intranet-live/.env`: `INTRANET_SERVER_IN_SWITZERLAND=true`, `INTRANET_DEMO_LOGIN=false`.
5. Danach Hostinger abschalten.
