FROM php:8.4-fpm-alpine

# System deps + PHP extensions needed by Laravel/Filament + Postgres
RUN apk add --no-cache \
        bash git icu-dev libzip-dev zip unzip \
        postgresql-dev oniguruma-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_pgsql pgsql intl zip bcmath gd opcache exif pcntl

# Composer (from official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Production opcache settings
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Run as non-root for security
RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/bash -D app

WORKDIR /var/www/html
COPY --chown=app:app . /var/www/html

# Install PHP deps (no dev in prod image; overridden in dev via compose command)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev || true \
    && chown -R app:app /var/www/html/storage /var/www/html/bootstrap/cache

USER app

EXPOSE 9000
CMD ["php-fpm"]
