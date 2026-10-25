FROM php:8.4-fpm-alpine

RUN apk update && apk add --no-cache \
    libzip-dev \
    libpng-dev \
    libpq-dev \
    linux-headers

RUN docker-php-ext-install pdo_pgsql gd zip

# Xdebug, for the code coverage only (make coverage sets XDEBUG_MODE=coverage): off otherwise, since it slows PHP down
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug-3.5.3 \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

ENV XDEBUG_MODE=off

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache bash

COPY docker/dev/symfony/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html
