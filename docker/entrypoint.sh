#!/bin/bash
set -e

# ---------------------------------------------------------------------------
# APP_URL / ASSET_URL fallback
#
# Render injects the service's public HTTPS URL as RENDER_EXTERNAL_URL.
# Use it as an automatic fallback so config:cache never bakes in "localhost".
# Explicit values set in the Render dashboard always win.
# ---------------------------------------------------------------------------
if [ -z "${APP_URL}" ] && [ -n "${RENDER_EXTERNAL_URL}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
    echo "APP_URL not set — falling back to RENDER_EXTERNAL_URL: ${APP_URL}"
fi
if [ -z "${ASSET_URL}" ] && [ -n "${RENDER_EXTERNAL_URL}" ]; then
    export ASSET_URL="${RENDER_EXTERNAL_URL}"
    echo "ASSET_URL not set — falling back to RENDER_EXTERNAL_URL: ${ASSET_URL}"
fi

# Dynamically set Nginx port if PORT environment variable is injected by Render
PORT="${PORT:-8080}"
sed -i "s/listen 8080/listen ${PORT}/g" /etc/nginx/nginx.conf
sed -i "s/listen \[::\]:8080/listen \[::\]:${PORT}/g" /etc/nginx/nginx.conf

# Remove local Vite hot file if copied into container
rm -f /var/www/html/public/hot

# Ensure storage subdirectories exist with proper permissions
#
# NOTE: turn audio (voice recordings) lives on the PRIVATE disk
# (storage/app/private/audio) and is streamed back through an
# ownership-checked controller. The public disk holds no private media.
mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/app/private/audio \
    /var/www/html/storage/database \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    /var/www/html/database

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
# Lock the private disk down — only the web user may read it.
chmod -R 750 /var/www/html/storage/app/private

# ---------------------------------------------------------------------------
# SQLite database bootstrap
#
# DB_DATABASE is set to /var/www/html/storage/database/database.sqlite in
# render.yaml so the file lives on the persistent disk.  The fallback below
# ensures the entrypoint is also self-contained for local Docker runs.
# ---------------------------------------------------------------------------
DB_CONN="${DB_CONNECTION:-sqlite}"
if [ "$DB_CONN" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    if [ ! -f "$DB_FILE" ]; then
        echo "Creating SQLite database at $DB_FILE..."
        touch "$DB_FILE"
        chown www-data:www-data "$DB_FILE"
        chmod 664 "$DB_FILE"
    fi
fi

# Ensure storage link exists for public access to audio files
echo "Linking storage directory..."
php artisan storage:link --force || true

# Clear any stale bootstrap cache files so config:cache always starts fresh.
# Without this, a cached config from a previous deploy can shadow new env vars.
rm -f \
    /var/www/html/bootstrap/cache/packages.php \
    /var/www/html/bootstrap/cache/services.php \
    /var/www/html/bootstrap/cache/config.php \
    /var/www/html/bootstrap/cache/routes.php

# Run production Laravel caches
echo "Optimizing Laravel config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Execute database migrations and seed default data
echo "Running database migrations & seeders..."
php artisan migrate --force --seed

echo "Starting PHP-FPM and Nginx..."
php-fpm -D
exec nginx
