# syntax=docker/dockerfile:1
# the production images, built from the root of the repository (docker/production/compose.yaml):
# - php: PHP-FPM with the application, its dependencies without the development ones and a warmed cache
# - nginx: the web client (front/dist) and the public files of Symfony, in front of php and the Mercure hub

# the web client (front/): vue-tsc and Vite are development dependencies
FROM node:24-alpine AS front

WORKDIR /app/front
COPY front/package.json front/package-lock.json ./
RUN npm ci
COPY front/ ./
RUN npm run build

FROM php:8.5-fpm-alpine AS php

RUN apk add --no-cache icu-libs libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev linux-headers \
    && docker-php-ext-install intl opcache pdo_pgsql \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/production/php/app.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/production/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

ENV APP_ENV=prod \
    APP_DEBUG=0

# the dependencies first, so that a change of the code does not install them again
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY --exclude=front --exclude=docker . ./
# public/bundles: assets:install creates it only for a bundle with public files (none without the development
# dependencies), and the nginx image copies it
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd \
    && mkdir -p public/bundles \
    && bin/console asset-map:compile \
    && bin/console cache:warmup \
    && chown -R www-data:www-data var

COPY --chmod=755 docker/production/php/entrypoint.sh /usr/local/bin/app-entrypoint

USER www-data

# migrations, card database (fixtures/) and house bot, then PHP-FPM on port 9000, behind the nginx service
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]

FROM nginx:1.29-alpine AS nginx

COPY docker/production/nginx/default.conf /etc/nginx/conf.d/default.conf
# one root: the web client, the assets of the development console (AssetMapper) and the public files of the bundles
COPY --from=front /app/front/dist /var/www/html/public
COPY --from=php /var/www/html/public/assets /var/www/html/public/assets
COPY --from=php /var/www/html/public/bundles /var/www/html/public/bundles
