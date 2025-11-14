FROM composer:2 AS build

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    libzip-dev \
    libwebp-dev
WORKDIR /src
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd \
    && composer create-project drupal/cms /src \
       --no-interaction --no-progress \
    && cd /src \
    && composer require drush/drush --no-interaction --no-progress

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
        libwebp-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
         gd intl pdo_mysql zip opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/drupal
COPY --from=build /src/ /opt/drupal/
COPY drupal/modules/custom/temporal_cms /opt/drupal/modules/custom/temporal_cms

RUN rm -rf /var/www/html \
    && ln -s /opt/drupal/web /var/www/html \
    && printf "memory_limit=256M\ndisplay_errors=Off\nerror_reporting=E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED\n" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && chown -R www-data:www-data /opt/drupal

COPY drupal-cms/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
