# FrankenPHP: PHP in the Caddy web server, which also embeds the Mercure hub and sends the web client to Vite (Caddyfile)
FROM dunglas/frankenphp:1-php8.5-alpine

RUN install-php-extensions pdo_pgsql gd zip

# Xdebug, for the code coverage only (make coverage sets XDEBUG_MODE=coverage): off otherwise, since it slows PHP down
RUN install-php-extensions xdebug-3.5.3

ENV XDEBUG_MODE=off

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache bash libcap

# the container runs as www-data: Caddy listens on port 80 and keeps its data (the history of Mercure) in /data/caddy
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R www-data:www-data /data/caddy /config/caddy

COPY docker/dev/symfony/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /var/www/html
