#!/usr/bin/env bash
# Grundeinrichtung des Infomaniak-Servers (#170) – Ubuntu 26.04 LTS (läuft auch auf 24.04).
# Einmal ausführen, kann wiederholt werden (überschreibt nur die eigenen Dateien).
#
#   sudo bash setup.sh --deploy-key 'ssh-ed25519 AAAA… github-deploy-intranet' --acme-email adresse@domain
#
# Richtet ein: Zeitzone, Updates, Docker (offizielles Repository), Benutzer «deploy» für GitHub Actions,
# SSH nur mit Schlüssel, ufw (22/80/443), fail2ban, automatische Sicherheitsupdates, Traefik mit Let's Encrypt
# und die Ordner der Instanzen. Zugangsdaten werden nicht ausgegeben und nicht protokolliert.
#
# Anleitung: docs/SERVER-INFOMANIAK.md

set -euo pipefail

DEPLOY_KEY=""
ACME_EMAIL=""
INSTANZEN="intranet-dev intranet-live"
DEV_PASSWORT=1

while [ $# -gt 0 ]; do
    case "$1" in
        --deploy-key) DEPLOY_KEY="$2"; shift 2 ;;
        --acme-email) ACME_EMAIL="$2"; shift 2 ;;
        --ohne-dev-passwort) DEV_PASSWORT=0; shift ;;
        -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
        *) echo "Unbekannte Option: $1" >&2; exit 2 ;;
    esac
done

schritt() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fehler() { printf '\033[31mFehler: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fehler "Bitte mit sudo ausführen."
. /etc/os-release
[ "${ID:-}" = "ubuntu" ] || fehler "Dieses Skript ist für Ubuntu geschrieben (gefunden: ${ID:-unbekannt})."
case "$DEPLOY_KEY" in
    ssh-ed25519\ *|ssh-rsa\ *|ecdsa-sha2-*) ;;
    *) fehler "--deploy-key fehlt oder ist kein öffentlicher SSH-Schlüssel (Inhalt der .pub-Datei)." ;;
esac
[[ "$ACME_EMAIL" == *@*.* ]] || fehler "--acme-email fehlt (für Let's Encrypt, z. B. Hinweise zu ablaufenden Zertifikaten)."

ADMIN="${SUDO_USER:-root}"

schritt "Zeitzone und Systemupdate"
timedatectl set-timezone Europe/Zurich
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get -yq -o Dpkg::Options::=--force-confold full-upgrade
apt-get install -yq ca-certificates curl gnupg ufw fail2ban python3-systemd unattended-upgrades restic jq

schritt "Docker aus dem offiziellen Repository"
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
cat > /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${UBUNTU_CODENAME:-$VERSION_CODENAME}
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF
rm -f /etc/apt/sources.list.d/docker.list
apt-get update -q
apt-get install -yq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
install -m 0755 -d /etc/docker
# Log-Rotation für alle Container (Treiber «local» begrenzt die Grösse selbst)
cat > /etc/docker/daemon.json <<'EOF'
{
  "log-driver": "local",
  "live-restore": true
}
EOF
systemctl enable docker >/dev/null
systemctl restart docker

schritt "Benutzer deploy (für GitHub Actions)"
if ! id deploy >/dev/null 2>&1; then
    useradd --create-home --shell /bin/bash deploy
fi
# Kein Passwort: Anmeldung nur mit dem Deploy-Schlüssel
passwd -l deploy >/dev/null
usermod -aG docker deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
printf 'no-port-forwarding,no-agent-forwarding,no-X11-forwarding %s\n' "$DEPLOY_KEY" > /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys

schritt "Ordner der Instanzen"
install -d -m 755 /docker
for i in $INSTANZEN; do
    install -d -m 750 -o deploy -g deploy "/docker/$i"
    touch "/docker/$i/.env"
    chown deploy:deploy "/docker/$i/.env"
    chmod 600 "/docker/$i/.env"
done
if [ "$DEV_PASSWORT" -eq 1 ] && ! grep -q '^INTRANET_ACCESS_PASSWORD=' /docker/intranet-dev/.env; then
    echo
    echo "Dev ist öffentlich erreichbar und hat das Demo-Login. Zugangspasswort für intranet-dev festlegen"
    echo "(Browser fragt danach; mindestens 16 Zeichen, wird nicht angezeigt):"
    while true; do
        read -rs -p "Passwort: " p1; echo
        read -rs -p "Wiederholen: " p2; echo
        if [ "${#p1}" -ge 16 ] && [ "$p1" = "$p2" ]; then break; fi
        echo "Zu kurz oder nicht gleich – nochmals."
    done
    printf 'INTRANET_ACCESS_PASSWORD=%s\n' "$p1" >> /docker/intranet-dev/.env
    unset p1 p2
fi

schritt "Traefik (HTTPS mit Let's Encrypt)"
install -d -m 750 /docker/traefik
printf 'ACME_EMAIL=%s\n' "$ACME_EMAIL" > /docker/traefik/.env
chmod 600 /docker/traefik/.env
cat > /docker/traefik/compose.yaml <<'EOF'
# Reverse Proxy für alle Intranet-Instanzen (#170). Erzeugt von docker/server/setup.sh – Änderungen dort machen.
# Traefik läuft im Netz des Servers und erreicht so die Container, ohne dass diese Ports öffnen.
# Veröffentlicht werden nur Container mit dem Label traefik.enable=true.
name: traefik

services:
  traefik:
    image: traefik:v3
    restart: unless-stopped
    network_mode: host
    command:
      - --providers.docker=true
      - --providers.docker.exposedbydefault=false
      - --entrypoints.web.address=:80
      - --entrypoints.web.http.redirections.entrypoint.to=websecure
      - --entrypoints.web.http.redirections.entrypoint.scheme=https
      - --entrypoints.websecure.address=:443
      - --certificatesresolvers.letsencrypt.acme.email=${ACME_EMAIL:?ACME_EMAIL in .env setzen}
      - --certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json
      - --certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web
      - --log.level=WARN
      - --global.sendanonymoususage=false
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - letsencrypt:/letsencrypt

volumes:
  letsencrypt:
EOF
(cd /docker/traefik && docker compose pull -q && docker compose up -d)

schritt "Firewall (ufw): nur SSH, HTTP, HTTPS"
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow OpenSSH >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null
ufw status verbose

schritt "fail2ban für SSH"
cat > /etc/fail2ban/jail.d/sekbuelach.local <<'EOF'
[sshd]
enabled = true
backend = systemd
maxretry = 5
findtime = 10m
bantime = 1h
EOF
systemctl enable fail2ban >/dev/null
systemctl restart fail2ban

schritt "Automatische Sicherheitsupdates (Neustart bei Bedarf um 04:00)"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
# Quellen: Ubuntu-Standard (Sicherheitsupdates). Docker bewusst nicht automatisch – neue Docker-Versionen können
# Traefik oder Compose brechen; Docker von Hand aktualisieren (apt upgrade) und danach den Deploy prüfen.
cat > /etc/apt/apt.conf.d/52sekbuelach-upgrades <<'EOF'
Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-Time "04:00";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
EOF
systemctl enable --now unattended-upgrades >/dev/null

schritt "SSH: nur mit Schlüssel"
ROOT_LOGIN="no"
if [ "$ADMIN" = "root" ]; then
    ROOT_LOGIN="prohibit-password"
    echo "Hinweis: Skript läuft direkt als root – root-Anmeldung mit Schlüssel bleibt erlaubt."
elif [ ! -s "$(getent passwd "$ADMIN" | cut -d: -f6)/.ssh/authorized_keys" ]; then
    ROOT_LOGIN="prohibit-password"
    echo "Hinweis: Für «$ADMIN» ist kein SSH-Schlüssel hinterlegt – root-Anmeldung mit Schlüssel bleibt erlaubt."
fi
cat > /etc/ssh/sshd_config.d/10-sekbuelach.conf <<EOF
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin $ROOT_LOGIN
PubkeyAuthentication yes
X11Forwarding no
MaxAuthTries 4
EOF
sshd -t || fehler "SSH-Konfiguration ungültig – nichts neu geladen. /etc/ssh/sshd_config.d/10-sekbuelach.conf prüfen."
systemctl reload ssh 2>/dev/null || systemctl reload sshd

schritt "Fertig"
cat <<EOF
Server eingerichtet: $(hostname), Docker $(docker --version | cut -d' ' -f3 | tr -d ,), Zeitzone $(timedatectl show -p Timezone --value).

Nächste Schritte (docs/SERVER-INFOMANIAK.md):
  1. Fingerabdruck für GitHub (Secret VPS_KNOWN_HOSTS im Environment «dev»), vom eigenen PC aus:
       ssh-keyscan -t ed25519 <IP des Servers>
  2. GitHub-Environment «dev»: VPS_HOST, VPS_USER=deploy, INTRANET_HOST=intranet-dev.sekbuelach.ch,
     Secrets VPS_SSH_KEY und VPS_KNOWN_HOSTS.
  3. Neue SSH-Sitzung testen, BEVOR diese geschlossen wird.
  4. Sicherung einrichten: sudo bash backup.sh install
EOF
