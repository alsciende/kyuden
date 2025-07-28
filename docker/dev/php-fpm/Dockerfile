FROM php:8.3-fpm

ARG http_proxy
ARG https_proxy
ARG no_proxy

# Install required php extensions
RUN apt-get update

RUN apt-get install -y  \
    libzip-dev \
    libbz2-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxpm-dev \
    libpq-dev \
    wkhtmltopdf \
    librabbitmq-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

RUN docker-php-ext-install \
    calendar \
    zip \
    bz2 \
    xml \
    curl \
    mbstring \
    intl \
    gd \
    fileinfo \
    pdo \
    pdo_pgsql \
    opcache \
    ftp

# Install php extensions with PECL
RUN pecl channel-update pecl.php.net \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && pecl install redis \
    && docker-php-ext-enable redis

