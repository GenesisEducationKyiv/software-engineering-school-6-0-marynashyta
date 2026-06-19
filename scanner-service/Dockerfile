# ── Stage 1: Install PHP dependencies ─────────────────────────────────────────
FROM composer:2.7 AS deps

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
    --no-interaction \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-sockets

# ── Stage 2: Runtime image ─────────────────────────────────────────────────────
FROM php:8.2-cli AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libzip5 \
    && docker-php-ext-install zip pcntl sockets \
    && apt-get purge -y libzip-dev linux-libc-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=deps /app/vendor ./vendor
COPY . .

CMD ["php", "/var/www/html/bin/scanner.php"]
