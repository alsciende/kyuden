# syntax=docker/dockerfile:1
# the production image, built from the root of the repository (docker/production/compose.yaml): FrankenPHP with the
# application (its dependencies without the development ones, a warmed cache), the web client (front/dist) and the
# Mercure hub, terminating TLS (app/Caddyfile)

# the web client (front/): vue-tsc and Vite are development dependencies
FROM node:24-alpine AS front

WORKDIR /app/front
COPY front/package.json front/package-lock.json ./
RUN npm ci
COPY front/ ./
RUN npm run build

FROM dunglas/frankenphp:1-php8.5-alpine AS app

RUN install-php-extensions intl pdo_pgsql

# the container runs as www-data: Caddy listens on ports 80 and 443 and keeps its data (the certificates, the history
# of Mercure) in /data/caddy
RUN apk add --no-cache libcap \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R www-data:www-data /data/caddy /config/caddy

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/production/app/app.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/production/app/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /var/www/html

ENV APP_ENV=prod \
    APP_DEBUG=0

# the dependencies first, so that a change of the code does not install them again
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY --exclude=front --exclude=docker . ./
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd \
    && bin/console asset-map:compile \
    && bin/console cache:warmup \
    && chown -R www-data:www-data var

# one root: the front controller of Symfony, the assets of the development console (AssetMapper) and the web client
COPY --from=front /app/front/dist public/

COPY --chmod=755 docker/production/app/entrypoint.sh /usr/local/bin/app-entrypoint

USER www-data

# migrations, card database (fixtures/) and house bot, then FrankenPHP
ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
