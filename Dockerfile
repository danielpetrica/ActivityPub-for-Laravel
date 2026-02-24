# Stage 1: Vendor
FROM composer:2 AS vendor
WORKDIR /app

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd bcmath intl pcntl redis pdo_pgsql

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-progress

# Stage 2: Assets
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json yarn.lock ./
RUN corepack enable && corepack prepare yarn@1.22.22 --activate && yarn install --frozen-lockfile

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

ENV NODE_ENV=production
RUN yarn build

# Stage 3: Worker (CLI)
FROM php:8.5-cli-alpine AS worker
COPY --from=vendor /usr/local/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath intl pcntl gd curl pdo_pgsql mbstring redis

ARG APP_ENV=production
WORKDIR /app
COPY . /app
COPY ".env.${APP_ENV:-production}" .env
COPY --from=vendor /app/vendor /app/vendor
COPY --from=assets /app/public/build /app/public/build

RUN mkdir -p storage bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

USER www-data
CMD ["php", "artisan", "queue:work", "--tries=3", "--sleep=1"]

# Stage 4: FrankenPHP (Web)
FROM dunglas/frankenphp:php8.5-alpine AS frankenphp
WORKDIR /app

ARG APP_ENV=production
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath intl pcntl gd curl pdo_pgsql mbstring redis

COPY . /app
COPY ".env.${APP_ENV:-production}" .env
COPY --from=vendor /app/vendor /app/vendor
COPY --from=assets /app/public/build /app/public/build

RUN mkdir -p storage bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80", "--workers=6"]
