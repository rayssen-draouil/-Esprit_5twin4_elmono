FROM node:22-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.3-cli-bookworm AS php-dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libsqlite3-dev unzip \
    && docker-php-ext-install bcmath mbstring pdo_mysql pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction --no-scripts

FROM php:8.3-apache-bookworm AS runtime
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libonig-dev libsqlite3-dev \
    && docker-php-ext-install bcmath mbstring pdo_mysql pdo_sqlite \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
WORKDIR /var/www/html
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY --from=php-dependencies /var/www/html /var/www/html
COPY --from=frontend /build/public/build /var/www/html/public/build
RUN mkdir -p /data storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && php artisan package:discover --ansi

EXPOSE 80
