#!/bin/sh
set -e

# Only the web container (php-fpm) runs initialisation. Queue/scheduler use the
# same image but start their own command immediately.
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] Initialising..."

    # Refresh public assets onto the shared volume (for nginx) so an image
    # update also publishes new Filament / app assets.
    if [ -d /var/www/html/public-dist ]; then
        cp -a /var/www/html/public-dist/. /var/www/html/public/
    fi

    # Ensure writable directories exist.
    mkdir -p storage/framework/cache storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache

    # Wait until the database is reachable.
    echo "[entrypoint] Waiting for database..."
    until php -r "new PDO('pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:5432).';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
        sleep 2
    done
    echo "[entrypoint] Database reachable."

    # Run migrations (idempotent) and build production caches.
    php artisan migrate --force
    php artisan storage:link 2>/dev/null || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    echo "[entrypoint] Ready. Starting php-fpm."
fi

exec "$@"
