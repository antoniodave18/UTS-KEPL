# FrankenPHP (Caddy + PHP) -> tanpa nginx terpisah
FROM dunglas/frankenphp:1.4-php8.3

RUN install-php-extensions pdo_mysql zip
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

ENV SERVER_NAME=:80
WORKDIR /app

# Cache layer dependency
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 80

# Migrate otomatis lalu jalankan server (docroot default: /app/public)
CMD ["sh", "-c", "php artisan migrate --force && frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile"]