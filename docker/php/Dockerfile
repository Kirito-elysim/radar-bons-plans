# Image PHP 8.3 commune : php-fpm, worker Messenger et scheduler
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-libs libpq libzip gmp git unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev gmp-dev linux-headers \
    && docker-php-ext-install -j$(nproc) intl pdo_pgsql zip opcache bcmath gmp \
    && pecl install apcu && docker-php-ext-enable apcu \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
# Les variables d'environnement du conteneur (Coolify) doivent parvenir aux processus PHP-FPM.
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-radar.conf

ENV TZ=Europe/Paris COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

# Image de développement : ajoute pcov pour la couverture de tests
FROM base AS dev
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install pcov && docker-php-ext-enable pcov \
    && apk del .build-deps

# Image de production : dépendances sans les outils de dev, code embarqué, OPcache figé
FROM base AS prod
ENV APP_ENV=prod APP_DEBUG=0
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist

COPY backend/ ./
RUN rm -rf tests var/* .env.local .env.*.local config/jwt/*.pem \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd \
    && mkdir -p var/cache var/log config/jwt \
    && chown -R www-data:www-data var config/jwt

COPY docker/php/entrypoint.sh /usr/local/bin/radar-entrypoint
RUN chmod +x /usr/local/bin/radar-entrypoint

USER www-data
ENTRYPOINT ["radar-entrypoint"]
CMD ["php-fpm"]
