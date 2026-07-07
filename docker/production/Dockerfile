FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --prefer-dist

FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo_pgsql \
        opcache \
        pcntl \
        bcmath \
        intl \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN php artisan package:discover --ansi \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/production/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
