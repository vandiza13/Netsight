# =============================================================================
# NETSIGHT v2.1 - Multi-Stage Dockerfile
# =============================================================================

# ---------------------------------------------------------------------------
# Stage 1: Composer Dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

ARG GITHUB_TOKEN
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config github-oauth.github.com $GITHUB_TOKEN; fi

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --ignore-platform-reqs



# ---------------------------------------------------------------------------
# Stage 3: Final Production Image
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine

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

# PHP-FPM Static Tuning for production (RAM Saving)
RUN echo "[www]" > /usr/local/etc/php-fpm.d/zz-netsight.conf \
    && echo "pm = static" >> /usr/local/etc/php-fpm.d/zz-netsight.conf \
    && echo "pm.max_children = 4" >> /usr/local/etc/php-fpm.d/zz-netsight.conf \
    && echo "pm.max_requests = 500" >> /usr/local/etc/php-fpm.d/zz-netsight.conf

WORKDIR /var/www/html

# Copy Composer vendor directory from stage 1
COPY --from=composer-deps /app/vendor ./vendor

# Copy compiled frontend assets from build context
COPY public/build ./public/build

# Copy entire application
COPY . .

# Ensure local .env is never included in the production image
RUN rm -f .env

# Copy composer binary from composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Regenerate optimized autoloader with full application classes
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Copy supervisord configuration
COPY docker/supervisord.conf /etc/supervisord.conf

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
