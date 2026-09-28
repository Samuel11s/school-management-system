# syntax=docker/dockerfile:1.7
#
# Single-container image for platforms that run one web container listening
# on $PORT (Render, Koyeb, Railway...). Nginx, PHP-FPM, the queue worker and
# the scheduler run side by side under supervisord, as the unprivileged
# www-data user. The multi-container setup in docker/php/Dockerfile remains
# the recommended option when separate services are available.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

FROM php:${PHP_VERSION}-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions bcmath exif gd intl opcache pcntl pdo_pgsql redis zip \
    && apk add --no-cache nginx supervisor tini

############################
# Composer dependencies
############################
FROM base AS vendor

RUN install-php-extensions @composer
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && rm -rf tests

############################
# Front-end assets
############################
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

############################
# Runtime
############################
FROM base AS runtime

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/conf.d/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/render/php-fpm.conf /usr/local/etc/php-fpm.d/zz-render.conf
COPY docker/render/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/render/supervisord.conf /etc/supervisord.conf
COPY docker/render/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=vendor /build /var/www/html
COPY --chown=www-data:www-data --from=assets /build/public/build /var/www/html/public/build

RUN rm -f .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && mkdir -p /var/lib/nginx /var/log/nginx /run/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx /run/nginx

USER www-data

ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["start"]
