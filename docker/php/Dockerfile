FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    git \
    unzip \
    && docker-php-ext-install opcache

WORKDIR /var/www/html

# Development stage: includes Composer for dependency management
FROM base AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Production stage: only application code, no dev tooling
FROM base AS prod

COPY ./app /var/www/html
