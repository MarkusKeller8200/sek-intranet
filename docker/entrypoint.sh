#!/bin/sh
# Startskript des Containers. Läuft bei jedem Start (auch nach jedem Deployment).
set -e
cd /var/www/html

# storage/ ist ein Docker-Volume: Ordner sicherstellen (bleiben über Deployments erhalten).
mkdir -p storage/app/data storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# APP_KEY: aus der Umgebung oder einmalig erzeugen und im Volume speichern.
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s storage/app/.app_key ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > storage/app/.app_key
        echo "[intranet] Neuer APP_KEY erzeugt (storage/app/.app_key)."
    fi
    APP_KEY="$(cat storage/app/.app_key)"
    export APP_KEY
fi

# Konfiguration, Routen und Views für Produktion vorbereiten.
php artisan optimize --no-ansi

# Umbenannte Auswahlwerte in bestehenden Daten nachführen (idempotent).
php artisan intranet:upgrade --no-ansi

# Demo-Daten nur anlegen, wenn noch keine Daten vorhanden sind (überschreibt nie).
php artisan intranet:seed --no-interaction --no-ansi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
