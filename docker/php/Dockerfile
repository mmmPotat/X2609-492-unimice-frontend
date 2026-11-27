# syntax=docker/dockerfile:1

########## Зависимости composer (отдельный слой — кешируется) ##########
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

########## Итоговый образ: nginx + php-fpm + supervisord в одном контейнере ##########
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        nginx \
        supervisor \
        icu-dev \
        libzip-dev \
        sqlite-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_sqlite \
        zip \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

# Код и vendor (vendor — из кеш-слоя выше, .dockerignore исключает его из контекста)
COPY composer.json composer.lock ./
COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-unimice.ini
COPY docker/php/supervisord.conf /etc/supervisord.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
