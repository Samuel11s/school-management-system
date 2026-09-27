# syntax=docker/dockerfile:1.7
#
# Multi-stage image for the School Management System.
#
#   base        PHP-FPM runtime with the extensions the application needs
#   dev         base + Composer + PCOV, used for local development and tooling
#   vendor      production Composer dependencies (no dev packages)
#   assets      compiled front-end assets (Vite)
#   production  hardened runtime image, runs as a non-root user
#   web         Nginx image carrying the public assets for production

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

############################
# base
############################
FROM php:${PHP_VERSION}-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        redis \
        zip \
    && apk add --no-cache fcgi tini

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-fpm.d/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php/healthcheck.sh /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

WORKDIR /var/www/html

ENTRYPOINT ["/sbin/tini", "--", "docker-php-entrypoint"]
CMD ["php-fpm"]

############################
# dev
############################
FROM base AS dev

RUN install-php-extensions pcov @composer \
    && apk add --no-cache git unzip

COPY docker/php/conf.d/dev.ini /usr/local/etc/php/conf.d/zz-dev.ini
COPY docker/php/php-fpm.d/zz-dev.conf /usr/local/etc/php-fpm.d/zz-dev.conf

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache

# -R allows the dev-only root worker pool (see php-fpm.d/zz-dev.conf).
CMD ["php-fpm", "-R"]

############################
# vendor
############################
FROM dev AS vendor

WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && rm -rf tests

############################
# assets
############################
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

############################
# production
############################
FROM base AS production

COPY docker/php/conf.d/production.ini /usr/local/etc/php/conf.d/zz-production.ini

COPY --chown=www-data:www-data --from=vendor /build /var/www/html
COPY --chown=www-data:www-data --from=assets /build/public/build /var/www/html/public/build

RUN rm -f .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint-production.sh /usr/local/bin/entrypoint-production
RUN chmod +x /usr/local/bin/entrypoint-production

USER www-data

ENTRYPOINT ["/sbin/tini", "--", "entrypoint-production"]
CMD ["php-fpm"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

############################
# web (nginx)
############################
FROM nginx:1.27-alpine AS web

ENV PHP_FPM_HOST=app
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY --from=vendor /build/public /var/www/html/public
COPY --from=assets /build/public/build /var/www/html/public/build

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1/nginx-health || exit 1
