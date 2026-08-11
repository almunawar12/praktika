# syntax=docker/dockerfile:1

# ---------- PHP dependencies ----------
FROM php:8.3-cli-alpine AS vendor
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip
COPY database/ database/
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

# ---------- Frontend build (the wayfinder vite plugin shells out to `php artisan`) ----------
FROM node:22-alpine AS frontend
WORKDIR /app
RUN apk add --no-cache \
    php83 php83-ctype php83-curl php83-dom php83-fileinfo php83-mbstring \
    php83-openssl php83-pdo php83-pdo_pgsql php83-phar php83-session \
    php83-simplexml php83-tokenizer php83-xml php83-xmlwriter php83-gd php83-zip \
    && ln -sf /usr/bin/php83 /usr/bin/php
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN cp .env.example .env && php artisan key:generate --ansi
RUN npm run build

# ---------- Runtime ----------
FROM serversideup/php:8.3-fpm-nginx AS app

USER root

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

RUN php artisan package:discover --ansi

USER www-data

EXPOSE 8080
