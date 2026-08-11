# syntax=docker/dockerfile:1

# ---------- Frontend build ----------
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY public/ public/
COPY vite.config.ts tsconfig.json components.json ./
RUN npm run build

# ---------- PHP dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
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
