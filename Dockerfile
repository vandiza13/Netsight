# =============================================================================
# NETSIGHT v2.1 - Multi-Stage Dockerfile
# =============================================================================

# ---------------------------------------------------------------------------
# Stage 1: Composer Dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# ---------------------------------------------------------------------------
# Stage 2: Node Build (Vite / Frontend Assets)
# ---------------------------------------------------------------------------
FROM node:20-alpine AS node-build

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY resources/ resources/
COPY vite.config.js ./

RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3: Final Production Image
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine

LABEL maintainer="NETSIGHT Team"
LABEL version="2.1"

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    gmp-dev \
    icu-dev \
    linux-headers \
    supervisor \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    pcntl \
    sockets \
    gmp \
    bcmath \
    intl

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Clean up build dependencies
RUN apk del $PHPIZE_DEPS linux-headers \
    && rm -rf /var/cache/apk/* /tmp/pear

WORKDIR /var/www/html

# Copy Composer vendor directory from stage 1
COPY --from=composer-deps /app/vendor ./vendor

# Copy compiled frontend assets from stage 2
COPY --from=node-build /app/public/build ./public/build

# Copy entire application
COPY . .

# Regenerate optimized autoloader with full application classes
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Copy supervisord configuration
COPY docker/supervisord.conf /etc/supervisord.conf

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
