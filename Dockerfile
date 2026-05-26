# syntax=docker/dockerfile:1

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.4-fpm-bookworm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) intl opcache pdo_mysql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php-fpm/zz-env.conf /usr/local/etc/php-fpm.d/zz-env.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

# .env is not committed; Symfony console needs it during image build
RUN cp .env.example .env \
    && sed -i '1s/^\xEF\xBB\xBF//' .env \
    && sed -i 's/^APP_ENV=dev/APP_ENV=prod/' .env \
    && mkdir -p var/cache var/log config/jwt public/bundles \
    && chown -R www-data:www-data var config/jwt public .env

ENV APP_ENV=prod
ENV APP_SECRET=build-time-secret-change-in-production

USER www-data

# Uses committed assets/vendor/ when present; skips CDN when packages are already vendored
RUN php bin/console importmap:install --no-interaction \
    && php bin/console asset-map:compile
RUN php bin/console assets:install public

USER root
RUN chown -R www-data:www-data var public

USER www-data

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

FROM app AS php

FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

# Single container for Render/Railway: nginx + PHP-FPM, listens on $PORT
FROM app AS web

USER root

RUN apt-get update && apt-get install -y --no-install-recommends nginx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx/web.conf.template /etc/nginx/templates/web.conf.template
COPY docker/start-web.sh /usr/local/bin/start-web
RUN chmod +x /usr/local/bin/start-web

CMD ["/usr/local/bin/start-web"]
