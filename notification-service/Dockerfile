# ── Stage 1: Install PHP dependencies ─────────────────────────────────────────
FROM composer:2.7 AS deps

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
    --no-interaction \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ── Stage 2: Runtime image ─────────────────────────────────────────────────────
FROM php:8.2-apache AS runtime

RUN apt-get update \
    && apt-get upgrade -y --no-install-recommends \
    && apt-get install -y --no-install-recommends libzip-dev curl \
    && docker-php-ext-install zip \
    && apt-get purge -y linux-libc-dev \
    && apt-get autoremove -y \
    && find /etc/apache2/mods-enabled/ -name 'mpm_*.load' -delete \
    && find /etc/apache2/mods-enabled/ -name 'mpm_*.conf' -delete \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=deps /app/vendor ./vendor
COPY . .

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        >> /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -so /dev/null http://localhost/health || exit 1

EXPOSE 80
CMD ["apache2-foreground"]
