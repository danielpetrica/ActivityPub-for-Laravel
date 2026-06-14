# Stage 1: Assets (frontend build)
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json yarn.lock ./
RUN corepack enable && corepack prepare yarn@1.22.22 --activate && yarn install --frozen-lockfile

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

ENV NODE_ENV=production
RUN yarn build

# Stage 2: Vendor (PHP dependencies)
FROM composer:2 AS vendor
WORKDIR /app

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd bcmath intl pcntl pdo_pgsql curl mbstring

# Redis via predis Composer package (no C extension required)

COPY composer.json composer.lock ./

COPY --link app/ app/
COPY --link artisan .
COPY --link bootstrap/ bootstrap/
COPY --link routes/ routes/
COPY .env .env

ENV APP_ENV=production
RUN --mount=type=secret,id=composer_auth \
    mkdir -p /root/.composer && \
    if [ -f /run/secrets/composer_auth ]; then \
        cp /run/secrets/composer_auth /root/.composer/auth.json; \
    fi

RUN mkdir -p storage/bootstrap/cache \
             storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs && \
    chmod -R 775 storage bootstrap/cache

RUN mkdir -p database && touch database/database.sqlite

COPY --from=assets /app/public/build/ /app/public/build/

ENV COMPOSER_CACHE_DIR=/tmp/cache
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --prefer-dist --no-interaction

RUN rm -f /root/.composer/auth.json || true; \
    rm -f /app/.composer/auth.json || true; \
    rm -f /tmp/* /var/tmp/* || true

COPY --link config/ config/
COPY --link database/ database/
COPY --link storage/ storage/

# Stage 3: Worker image (CLI — runs Horizon / schedule:work)
FROM php:8.5-cli-alpine AS worker
COPY --from=vendor /usr/local/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath intl pcntl gd curl pdo_pgsql mbstring

# phpredis compiled from GitHub (no PECL release for PHP 8.5 yet)
RUN git clone --depth 1 --branch 6.3.0 https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && cd /tmp/phpredis \
    && phpize \
    && ./configure \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/phpredis

ARG APP_ENV=production
WORKDIR /app
COPY --link app/ app/
COPY --link bootstrap/ bootstrap/
COPY --link config/ config/
COPY --link routes/ routes/
COPY --link database/ database/
COPY --link artisan .
COPY .env .env
COPY --link composer.json .
COPY --link resources/ resources/
COPY --from=vendor /app/vendor /app/vendor

COPY --from=assets /app/public/build /app/public/build

RUN mkdir -p storage bootstrap/cache
RUN chown -R 82:82 /app
RUN chmod -R 775 storage bootstrap/cache

USER 82
CMD ["php", "artisan", "horizon"]

# Stage 4: Web image (FrankenPHP / Octane)
FROM dunglas/frankenphp:php8.5-alpine AS frankenphp
WORKDIR /app

ARG APP_ENV=production
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath intl pcntl gd curl pdo_pgsql mbstring

# phpredis compiled from GitHub (no PECL release for PHP 8.5 yet)
RUN git clone --depth 1 --branch 6.3.0 https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && cd /tmp/phpredis \
    && phpize \
    && ./configure \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/phpredis

COPY --link app/ app/
COPY --link bootstrap/ bootstrap/
COPY --link config/ config/
COPY --link routes/ routes/
COPY --link database/ database/
COPY --link artisan .
COPY --link composer.json .
COPY --link resources/ resources/

COPY .env .env
COPY --from=vendor /app/vendor /app/vendor

COPY --from=assets /app/public/build /app/public/build

COPY --from=vendor /usr/local/bin/install-php-extensions /usr/local/bin/install-php-extensions
COPY --from=vendor /usr/bin/composer /usr/bin/composer

COPY php-prod.ini /usr/local/etc/php/php.ini

COPY --link public/ public/

COPY entrypoint.sh .
RUN chmod +x /app/entrypoint.sh

RUN mkdir -p storage/bootstrap/cache \
             storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
    && chown -R 82:82 /app /data/caddy /config/caddy \
    && chmod -R 775 storage bootstrap/cache

USER 82

ENTRYPOINT ["/app/entrypoint.sh"]
