# Image PHP 8.3 commune : php-fpm, worker Messenger et scheduler
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-libs libpq libzip git unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev linux-headers \
    && docker-php-ext-install -j$(nproc) intl pdo_pgsql zip opcache bcmath \
    && pecl install apcu && docker-php-ext-enable apcu \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

ENV TZ=Europe/Paris COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

# Image de développement : ajoute pcov pour la couverture de tests
FROM base AS dev
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install pcov && docker-php-ext-enable pcov \
    && apk del .build-deps
