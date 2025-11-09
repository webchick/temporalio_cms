FROM composer:2 AS build

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    libzip-dev
WORKDIR /src
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd \
    && composer create-project drupal/cms /src \
       --no-interaction --no-progress

FROM drupal:11-apache

RUN apt-get update \
    && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libicu-dev \
        libzip-dev \
        libxml2-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
         gd intl pdo_mysql zip opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/drupal
COPY --from=build /src/ /opt/drupal/

RUN rm -rf /var/www/html \
    && ln -s /opt/drupal/web /var/www/html \
    && chown -R www-data:www-data /opt/drupal
