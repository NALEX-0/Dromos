#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

key_file="storage/app/.docker-app-key"

if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$key_file" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$key_file"
        chown www-data:www-data "$key_file"
        chmod 600 "$key_file"
    fi

    APP_KEY="$(cat "$key_file")"
    export APP_KEY
fi

echo "Waiting for the Dromos database..."

attempt=0
until php -r '
    try {
        new PDO(
            sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE")),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
    } catch (Throwable $exception) {
        exit(1);
    }
'; do
    attempt=$((attempt + 1))

    if [ "$attempt" -ge 60 ]; then
        echo "The database did not become available in time."
        exit 1
    fi

    sleep 2
done

echo "Preparing Dromos..."
php artisan migrate --force
php artisan config:cache
php artisan view:cache

exec "$@"
