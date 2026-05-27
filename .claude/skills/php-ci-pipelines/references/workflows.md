# Complete GitHub Actions Workflows

The project uses a shared composite action `.github/actions/php-setup` for PHP + Composer
setup, and a single `docker-compose.test.yml` for all Docker-based tests.

---

## unit.yml

```yaml
# .github/workflows/unit.yml
name: Unit Tests

on:
  push:
    branches: ["**"]
  pull_request:
    branches: ["**"]

jobs:
  unit:
    name: Unit Tests
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: ./.github/actions/php-setup
        with:
          php-extensions: pdo_sqlite, zip

      - name: Run unit tests
        run: vendor/bin/phpunit --colors=always
```

> Unit tests use `phpunit.xml` (default config — no `-c` flag needed).
> PHPStan and phpcs run in CI via the main `ci.yml`, not per-suite workflows.

---

## integration.yml

```yaml
# .github/workflows/integration.yml
name: Integration Tests

on:
  push:
    branches: ["**"]
  pull_request:
    branches: ["**"]

jobs:
  integration:
    name: Integration Tests
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: ./.github/actions/php-setup
        with:
          php-extensions: pdo_mysql, zip

      - name: Start test stack
        run: docker compose -f docker-compose.test.yml up -d --build

      - name: Wait for API to be healthy
        run: |
          for i in $(seq 1 40); do
            curl -sf http://localhost:8080/ && break
            echo "Waiting for API... ($i/40)"
            sleep 3
          done
          curl -sf http://localhost:8080/ || { echo "API never became healthy"; exit 1; }

      - name: Run integration tests
        env:
          API_BASE_URL: http://localhost:8080
          DB_HOST: localhost
          DB_PORT: 3306
          DB_NAME: release_notifications
          DB_USER: app
          DB_PASS: secret
          API_KEY: ""
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: vendor/bin/phpunit -c phpunit.integration.xml --colors=always

      - name: Tear down
        if: always()
        run: docker compose -f docker-compose.test.yml down -v
```

---

## e2e.yml

The E2E suite uses `playwright-php/playwright` (pure PHP) — no Node.js, no `npm`.
The app runs via `docker-compose.test.yml` (same stack as integration tests).

```yaml
# .github/workflows/e2e.yml
name: E2E Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  e2e:
    name: E2E Tests (Playwright)
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: ./.github/actions/php-setup
        with:
          php-extensions: zip

      - name: Cache Playwright browsers
        uses: actions/cache@v4
        with:
          path: ~/.cache/ms-playwright
          key: playwright-${{ runner.os }}-${{ hashFiles('composer.lock') }}

      - name: Install Playwright browsers
        run: vendor/bin/playwright-install --with-deps

      - name: Start test stack
        run: docker compose -f docker-compose.test.yml up -d --build

      - name: Wait for API to be healthy
        run: |
          for i in $(seq 1 40); do
            curl -sf http://localhost:8080/ && break
            echo "Waiting for API... ($i/40)"
            sleep 3
          done
          curl -sf http://localhost:8080/ || { echo "API never became healthy"; exit 1; }

      - name: Run E2E tests
        env:
          APP_URL: http://localhost:8080
          API_KEY: ""
        run: vendor/bin/phpunit -c phpunit.e2e.xml --colors=always

      - name: Upload test screenshots
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: e2e-report-${{ github.run_id }}
          path: tests/E2E/screenshots/
          if-no-files-found: ignore

      - name: Tear down
        if: always()
        run: docker compose -f docker-compose.test.yml down -v
```

---

## .github/actions/php-setup/action.yml

Shared composite action used by all three workflows:

```yaml
name: PHP + Composer Setup
description: Sets up PHP 8.2 and installs Composer dependencies with caching

inputs:
  php-extensions:
    description: Comma-separated PHP extensions to install
    required: false
    default: ''

runs:
  using: composite
  steps:
    - name: Set up PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: "8.2"
        extensions: ${{ inputs.php-extensions }}
        coverage: none

    - name: Cache Composer dependencies
      uses: actions/cache@v4
      with:
        path: vendor
        key: ${{ runner.os }}-composer-${{ hashFiles('composer.lock') }}
        restore-keys: ${{ runner.os }}-composer-

    - name: Install dependencies
      run: composer install --no-interaction --prefer-dist
      shell: bash
```
