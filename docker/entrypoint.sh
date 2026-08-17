#!/bin/bash
set -e

# Dynamically set Nginx port if PORT environment variable is injected by Render
PORT="${PORT:-8080}"
sed -i "s/listen 8080/listen ${PORT}/g" /etc/nginx/nginx.conf
sed -i "s/listen \[::\]:8080/listen \[::\]:${PORT}/g" /etc/nginx/nginx.conf

# Remove local Vite hot file if copied into container
rm -f /var/www/html/public/hot

# Ensure storage subdirectories exist with proper permissions
mkdir -p \
    /var/www/html/storage/app/public/audio \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    /var/www/html/database

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Setup SQLite database if DB_CONNECTION is sqlite (or fallback default)
rm -f /var/www/html/bootstrap/cache/packages.php /var/www/html/bootstrap/cache/services.php /var/www/html/bootstrap/cache/config.php /var/www/html/bootstrap/cache/routes.php
DB_CONN="${DB_CONNECTION:-sqlite}"
if [ "$DB_CONN" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
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

# Run production Laravel caches
echo "Optimizing Laravel config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Execute database migrations
echo "Running database migrations..."
php artisan migrate --force

echo "Starting PHP-FPM and Nginx..."
php-fpm -D
exec nginx
