#!/bin/bash
set -e

echo "Waiting for database connection..."
max_attempts=30
attempt=0
until php -r "
    try {
        \$pdo = new PDO(
            'mysql:host=${DB_HOST};port=${DB_PORT:-3306};dbname=${DB_NAME}',
            '${DB_USER}',
            '${DB_PASS}'
        );
        echo 'Connected';
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ $attempt -ge $max_attempts ]; then
        echo "Database connection failed after $max_attempts attempts"
        exit 1
    fi
    echo "Attempt $attempt/$max_attempts failed, retrying in 2s..."
    sleep 2
done

echo "Database connected. Running migrations..."
php /var/www/html/bin/migrate.php

find /etc/apache2/mods-enabled/ -name 'mpm_*.load' -delete 2>/dev/null || true
find /etc/apache2/mods-enabled/ -name 'mpm_*.conf' -delete 2>/dev/null || true
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

PORT="${PORT:-80}"
if [ "$PORT" != "80" ]; then
    sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-enabled/000-default.conf
fi

echo "Starting service on port ${PORT}..."
exec "$@"
