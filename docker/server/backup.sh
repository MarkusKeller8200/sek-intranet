#!/usr/bin/env bash
# Sicherung der Intranet-Instanzen auf dem Infomaniak-Server mit restic nach Swiss Backup (#172).
#
#   sudo bash backup.sh install     einmal: Skript, Timer und Vorlage /etc/sekbuelach/backup.env einrichten
#   sudo backup.sh init             einmal, nach dem Ausfüllen von backup.env: Repository anlegen
#   sudo backup.sh                  Sicherung jetzt (läuft sonst täglich um 02:30 per Timer)
#   sudo backup.sh pruefen          Wiederherstellungstest (läuft sonst sonntags 05:15 per Timer)
#   sudo backup.sh liste            Sicherungsstände anzeigen
#   sudo backup.sh zurueck <Instanz> [Stand]   Daten einer Instanz wiederherstellen (fragt nach)
#
# Ablauf pro Instanz: App kurz anhalten, Volumen lokal kopieren (einheitlicher Stand der JSON-Dateien), wieder
# starten, dann die Kopie und die .env verschlüsselt hochladen. Images werden nicht gesichert (baut GitHub neu).
# Zugangsdaten nur in /etc/sekbuelach/backup.env (nur root lesbar); sie werden nie ausgegeben.

set -euo pipefail

KONFIG=/etc/sekbuelach/backup.env
STATUS=/var/lib/sekbuelach/backup-status
ARBEIT=/var/backups/sekbuelach

fehler() { echo "Fehler: $*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || fehler "Bitte mit sudo ausführen."

install_() {
    install -m 0750 "$0" /usr/local/sbin/backup.sh
    install -d -m 700 /etc/sekbuelach "$ARBEIT"
    install -d -m 755 /var/lib/sekbuelach
    if [ ! -e "$KONFIG" ]; then
        umask 077
        cat > "$KONFIG" <<'EOF'
# Sicherung (#172) – nur root lesbar. Werte aus dem Infomaniak Manager → Swiss Backup → S3-Zugang.
# Repository: s3:https://<S3-Endpunkt aus dem Manager>/<Bucket>/<Server>
RESTIC_REPOSITORY=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
# restic-Passwort: in der Datei unten, zusätzlich im Passwort-Manager ablegen. Ohne sie ist nichts lesbar.
RESTIC_PASSWORD_FILE=/etc/sekbuelach/restic-passwort
# Welche Instanzen gesichert werden (Dev enthält nur Testdaten)
INSTANZEN="intranet-live"
# Warnung, wenn das Repository grösser wird (GB)
WARNEN_AB_GB=50
# Optional: URL, die nach jeder Sicherung aufgerufen wird (…/fail bei Fehlern), z. B. ein Überwachungsdienst
PING_URL=
EOF
        echo "Vorlage angelegt: $KONFIG – ausfüllen, dann: sudo backup.sh init"
    fi
    if [ ! -e /etc/sekbuelach/restic-passwort ]; then
        (umask 077; head -c 32 /dev/urandom | base64 > /etc/sekbuelach/restic-passwort)
        echo "restic-Passwort erzeugt: /etc/sekbuelach/restic-passwort"
        echo "  → einmal mit «sudo cat /etc/sekbuelach/restic-passwort» lesen und im Passwort-Manager ablegen."
    fi
    cat > /etc/systemd/system/sekbuelach-backup.service <<'EOF'
[Unit]
Description=Sicherung der Intranet-Instanzen (restic)
After=docker.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/backup.sh sichern
Nice=10
IOSchedulingClass=idle
EOF
    cat > /etc/systemd/system/sekbuelach-backup.timer <<'EOF'
[Unit]
Description=Tägliche Sicherung der Intranet-Instanzen

[Timer]
OnCalendar=*-*-* 02:30
RandomizedDelaySec=10m
Persistent=true

[Install]
WantedBy=timers.target
EOF
    cat > /etc/systemd/system/sekbuelach-backup-pruefen.service <<'EOF'
[Unit]
Description=Wiederherstellungstest der Sicherung
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/backup.sh pruefen
Nice=10
EOF
    cat > /etc/systemd/system/sekbuelach-backup-pruefen.timer <<'EOF'
[Unit]
Description=Wöchentlicher Wiederherstellungstest

[Timer]
OnCalendar=Sun *-*-* 05:15
Persistent=true

[Install]
WantedBy=timers.target
EOF
    systemctl daemon-reload
    echo "Timer und die Sicherung vor dem Live-Deploy werden mit «sudo backup.sh init» eingeschaltet."
}

laden() {
    [ -r "$KONFIG" ] || fehler "$KONFIG fehlt – zuerst: sudo bash backup.sh install"
    set -a; . "$KONFIG"; set +a
    [ -n "${RESTIC_REPOSITORY:-}" ] && [ -n "${AWS_ACCESS_KEY_ID:-}" ] && [ -n "${AWS_SECRET_ACCESS_KEY:-}" ] \
        || fehler "$KONFIG ist nicht vollständig ausgefüllt."
    [ -s "${RESTIC_PASSWORD_FILE:-}" ] || fehler "restic-Passwortdatei fehlt."
    export RESTIC_CACHE_DIR=/var/cache/restic
}

status() {
    # Für den Deploy-Workflow lesbar; enthält keine Zugangsdaten
    local ergebnis="$1" text="$2"
    printf 'zeit=%s\nergebnis=%s\ntext=%s\n' "$(date -Iseconds)" "$ergebnis" "$text" > "$STATUS.tmp"
    chmod 644 "$STATUS.tmp" && mv "$STATUS.tmp" "$STATUS"
    if [ -n "${PING_URL:-}" ]; then
        local url="$PING_URL"; [ "$ergebnis" = ok ] || url="$PING_URL/fail"
        curl -fsS -m 10 --retry 3 -o /dev/null "$url" || true
    fi
}

freigabe_deploy() {
    # Der Live-Deploy (GitHub, Benutzer deploy) sichert vor jedem Update – nur genau dieser Aufruf ist erlaubt
    if id deploy >/dev/null 2>&1; then
        printf 'deploy ALL=(root) NOPASSWD: /usr/local/sbin/backup.sh sichern\n' > /etc/sudoers.d/sekbuelach-backup.tmp
        chmod 440 /etc/sudoers.d/sekbuelach-backup.tmp
        if visudo -cf /etc/sudoers.d/sekbuelach-backup.tmp >/dev/null; then
            mv /etc/sudoers.d/sekbuelach-backup.tmp /etc/sudoers.d/sekbuelach-backup
        else
            rm -f /etc/sudoers.d/sekbuelach-backup.tmp
            echo "Hinweis: Freigabe für den Deploy konnte nicht eingerichtet werden."
        fi
    fi
}

init() {
    laden
    if restic cat config >/dev/null 2>&1; then
        echo "Repository besteht bereits."
    else
        restic init
    fi
    systemctl enable --now sekbuelach-backup.timer sekbuelach-backup-pruefen.timer
    freigabe_deploy
    echo "Timer eingeschaltet. Erste Sicherung jetzt: sudo backup.sh"
}

sichern() {
    laden
    local fehlerliste="" i ordner volume kopie
    trap 'status fehler "Abbruch in Zeile $LINENO"' ERR
    for i in $INSTANZEN; do
        ordner="/docker/$i"
        volume="${i}_intranet-storage"
        kopie="$ARBEIT/$i"
        if ! docker volume inspect "$volume" >/dev/null 2>&1; then
            echo "$i: Volumen $volume fehlt – übersprungen."
            fehlerliste="$fehlerliste $i(kein-volumen)"
            continue
        fi
        echo "== $i"
        rm -rf "$kopie" && install -d -m 700 "$kopie/storage"
        # Kurz anhalten für einen einheitlichen Stand; danach sofort wieder starten (auch bei Fehlern)
        (cd "$ordner" && docker compose -p "$i" stop -t 30 app) || true
        if ! docker run --rm -v "$volume:/quelle:ro" -v "$kopie/storage:/ziel" alpine:3 \
            sh -c 'cp -a /quelle/. /ziel/'; then
            fehlerliste="$fehlerliste $i(kopie)"
        fi
        (cd "$ordner" && docker compose -p "$i" start app) || fehlerliste="$fehlerliste $i(start)"
        [ -f "$ordner/.env" ] && install -m 600 "$ordner/.env" "$kopie/env"
        # Logs und Zwischenspeicher nicht sichern
        rm -rf "$kopie/storage/logs" "$kopie/storage/framework/cache" "$kopie/storage/framework/views" \
            "$kopie/storage/framework/sessions"
        restic backup --quiet --tag "$i" --host "$(hostname)" "$kopie" || fehlerliste="$fehlerliste $i(upload)"
        rm -rf "$kopie"
    done
    restic forget --quiet --prune --group-by host,tags \
        --keep-daily 14 --keep-weekly 8 --keep-monthly 24 --keep-yearly 5 || fehlerliste="$fehlerliste forget"
    local bytes gb
    bytes="$(restic stats --quiet --mode raw-data --json | jq -r '.total_size // 0')"
    gb=$(( bytes / 1024 / 1024 / 1024 ))
    trap - ERR
    if [ -n "$fehlerliste" ]; then
        status fehler "Fehler:$fehlerliste"
        fehler "Sicherung mit Fehlern:$fehlerliste"
    elif [ "$gb" -ge "${WARNEN_AB_GB:-50}" ]; then
        status ok "Repository ${gb} GB – grösser als erwartet, prüfen (Logs oder Images mitgesichert?)"
    else
        status ok "Repository ${gb} GB"
    fi
    echo "Fertig – Repository ${gb} GB."
}

pruefen() {
    laden
    local tmp i anzahl
    tmp="$(mktemp -d /var/tmp/restore-test.XXXXXX)"
    trap 'rm -rf "$tmp"' EXIT
    restic check --quiet --read-data-subset=5%
    for i in $INSTANZEN; do
        restic restore latest --quiet --tag "$i" --target "$tmp/$i"
        anzahl="$(find "$tmp/$i" -type f -path '*/storage/app/*' | wc -l)"
        [ "$anzahl" -gt 0 ] || { status fehler "Wiederherstellungstest $i: keine Daten"; fehler "$i: keine Daten im letzten Stand"; }
        [ -s "$(find "$tmp/$i" -path '*/storage/app/.app_key' | head -1)" ] \
            || echo "$i: Hinweis – kein .app_key im Stand (APP_KEY dann in der .env?)"
        echo "$i: $anzahl Dateien wiederhergestellt – ok"
    done
    echo "Wiederherstellungstest ok ($(date '+%d.%m.%Y %H:%M'))."
}

zurueck() {
    laden
    local i="${1:-}" stand="${2:-latest}" tmp volume quelle
    [ -n "$i" ] || fehler "Instanz angeben, z. B.: sudo backup.sh zurueck intranet-live"
    volume="${i}_intranet-storage"
    restic snapshots --tag "$i"
    echo
    read -r -p "Daten von $i durch Stand «$stand» ERSETZEN? Aktuelle Daten werden vorher gesichert. «ja» eintippen: " ok
    [ "$ok" = ja ] || fehler "Abgebrochen."
    sichern
    tmp="$(mktemp -d /var/tmp/zurueck.XXXXXX)"
    restic restore "$stand" --tag "$i" --target "$tmp"
    quelle="$(find "$tmp" -type d -path "*/$i/storage" | head -1)"
    [ -n "$quelle" ] || fehler "Stand enthält keine Daten von $i."
    (cd "/docker/$i" && docker compose -p "$i" stop app)
    docker run --rm -v "$volume:/ziel" -v "$quelle:/quelle:ro" alpine:3 \
        sh -c 'find /ziel -mindepth 1 -delete && cp -a /quelle/. /ziel/'
    (cd "/docker/$i" && docker compose -p "$i" start app)
    rm -rf "$tmp"
    echo "$i wiederhergestellt aus Stand $stand. Die .env liegt im Stand unter env (nicht automatisch übernommen)."
}

case "${1:-sichern}" in
    install) install_ ;;
    init) init ;;
    sichern) sichern ;;
    pruefen) pruefen ;;
    liste) laden; restic snapshots ;;
    zurueck) shift; zurueck "$@" ;;
    *) sed -n '2,12p' "$0"; exit 2 ;;
esac
