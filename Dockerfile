# ── Stage 1: Install PHP dependencies ─────────────────────────────────────────
# Use a pinned Composer image to install vendor/ outside the runtime image.
# This keeps the final layer smaller and avoids shipping Composer itself.
FROM composer:2.7 AS deps

WORKDIR /app

# Copy the lock file first so this layer is only rebuilt when dependencies change.
COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ── Stage 2: Runtime image ─────────────────────────────────────────────────────
FROM php:8.2-apache AS runtime

ARG MPM_RESET=v4

# Install only the extensions the application actually needs.
# --no-install-recommends keeps the layer lean.
RUN apt-get update \
    && apt-get upgrade -y --no-install-recommends \
    && apt-get install -y --no-install-recommends libzip-dev curl \
    && docker-php-ext-install pdo_mysql zip \
    && apt-get purge -y linux-libc-dev \
    && apt-get autoremove -y \
    && find /etc/apache2/mods-enabled/ -name 'mpm_*.load' -delete \
    && find /etc/apache2/mods-enabled/ -name 'mpm_*.conf' -delete \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy pre-built vendor from the deps stage (no Composer binary in prod).
COPY --from=deps /app/vendor ./vendor

# Copy application source (vendor/ is excluded via .dockerignore).
COPY . .

# Point Apache at the Slim front-controller.
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        >> /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Any HTTP response (even 401/404 from the router) proves Apache + PHP-FPM are alive.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -so /dev/null http://localhost/ || exit 1

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
