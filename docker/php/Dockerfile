FROM php:8.5-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev unzip libfcgi-bin \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data

WORKDIR /var/www/html

RUN php -r 'exit((int)!extension_loaded("openssl"));'

RUN pecl install apcu-5.1.28 && docker-php-ext-enable apcu && rm -rf /tmp/pear
