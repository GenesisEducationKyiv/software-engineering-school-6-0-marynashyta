# Docker Setup — Integration Tests

All integration test infrastructure starts via Docker Compose using the project's
`docker-compose.test.yml`. No local PHP or database installation required — only `docker` and `git`.

---

## docker-compose.test.yml

The project's existing `docker-compose.test.yml` runs the full stack:

```yaml
services:
  api:
    build: .
    ports:
      - "8080:80"
    environment:
      DB_HOST: db
      DB_PORT: 3306
      DB_NAME: release_notifications
      DB_USER: app
      DB_PASS: secret
      REDIS_HOST: redis
      REDIS_PORT: 6379
      REDIS_DB: 0
      MAIL_HOST: mailpit
      MAIL_PORT: 1025
      APP_URL: http://localhost:8080
      API_KEY: ""
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: release_notifications
      MYSQL_USER: app
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: rootsecret
    volumes:
      - ./migrations/001_initial.sql:/docker-entrypoint-initdb.d/001_initial.sql:ro
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -uapp -psecret --silent"]
      interval: 5s
      timeout: 5s
      retries: 10
      start_period: 10s

  redis:
    image: redis:7-alpine
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 10

  mailpit:
    image: axllent/mailpit:v1.21
    ports:
      - "8025:8025"
```

> The `api` container runs Apache 2 (not the built-in PHP server). The app is reachable
> on `http://localhost:8080` once healthy.

---

## Dockerfile

The project uses a single root-level `Dockerfile` (multi-stage build):

```dockerfile
FROM composer:2.7 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --no-scripts \
    --prefer-dist --optimize-autoloader

FROM php:8.2-apache AS runtime
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev curl \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=deps /app/vendor ./vendor
COPY . .
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
```

---

## Database connection in tests

Integration tests connect directly to MySQL using the credentials from the bootstrap:

```php
// tests/bootstrap.integration.php sets these from env with fallbacks:
$_ENV['DB_HOST'] = getenv('DB_HOST') ?: 'localhost';
$_ENV['DB_PORT'] = getenv('DB_PORT') ?: '3306';
$_ENV['DB_NAME'] = getenv('DB_NAME') ?: 'release_notifications';
$_ENV['DB_USER'] = getenv('DB_USER') ?: 'app';
$_ENV['DB_PASS'] = getenv('DB_PASS') ?: 'secret';
```

PDO connection string:

```php
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_PORT'],
    $_ENV['DB_NAME'],
);

$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_FETCH_MODE => PDO::FETCH_ASSOC,
]);
```

---

## Running Locally

```bash
# Build + start full stack in background
docker compose -f docker-compose.test.yml up -d --build

# Wait for API
until curl -so /dev/null http://localhost:8080/; do echo "Waiting..."; sleep 2; done

# Run integration tests (host network, so localhost:8080 reaches the container)
API_BASE_URL=http://localhost:8080 DB_HOST=localhost DB_PORT=3306 \
DB_NAME=release_notifications DB_USER=app DB_PASS=secret API_KEY="" \
vendor/bin/phpunit -c phpunit.integration.xml --colors=always

# Or with Make
make test-integration

# Tear down
docker compose -f docker-compose.test.yml down -v
```
