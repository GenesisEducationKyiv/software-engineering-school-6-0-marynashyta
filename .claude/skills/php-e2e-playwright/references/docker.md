# Docker Setup — E2E Tests

The PHP app runs via `docker-compose.test.yml` (the same stack used for integration tests).
There is no separate `docker-compose.e2e.yml`. Playwright runs locally against the exposed
`http://localhost:8080` port.

---

## docker-compose.test.yml

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

  mailpit:
    image: axllent/mailpit:v1.21
    ports:
      - "8025:8025"
```

> The app is reachable at `http://localhost:8080` — no `/health` endpoint exists;
> poll the root `/` instead.

---

## Health check pattern

```bash
# In scripts / CI
until curl -so /dev/null http://localhost:8080/; do
  echo "Waiting for app..."
  sleep 2
done
```

```php
// In PHP test setup if needed
for ($i = 0; $i < 30; $i++) {
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    if (@file_get_contents('http://localhost:8080/', false, $ctx) !== false) {
        break;
    }
    sleep(2);
}
```

---

## Composer (Playwright PHP — no Node.js)

```bash
# Already in composer.json require-dev
composer install

# Install browser binaries (once per machine / CI runner)
vendor/bin/playwright-install --with-deps
```

> `playwright-php/playwright` runs a Node Playwright server internally but the test code
> is pure PHP. There is no `package.json`, no `npm`, and no `e2e/` subdirectory.

---

## Running Locally

```bash
# Start app stack
docker compose -f docker-compose.test.yml up -d --build

# Wait for app
until curl -so /dev/null http://localhost:8080/; do echo "Waiting..."; sleep 2; done

# Run E2E tests
APP_URL=http://localhost:8080 API_KEY="" \
vendor/bin/phpunit -c phpunit.e2e.xml --colors=always

# Or with Make
make test-e2e

# Tear down
docker compose -f docker-compose.test.yml down -v
```

---

## Seeding test data

E2E tests that need specific DB state should insert rows directly via PDO before navigating,
or use the Mailpit API (`http://localhost:8025/api/v1/messages`) to inspect sent emails:

```php
protected function getLastEmailBody(): string
{
    $ctx  = stream_context_create(['http' => ['timeout' => 5]]);
    $json = file_get_contents('http://localhost:8025/api/v1/messages', false, $ctx);
    /** @var array<string, mixed> $data */
    $data     = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    /** @var list<array<string, mixed>> $messages */
    $messages = $data['messages'] ?? [];
    $id       = $messages[0]['ID'] ?? '';

    $body = file_get_contents("http://localhost:8025/api/v1/message/{$id}", false, $ctx);
    return (string) $body;
}
```
