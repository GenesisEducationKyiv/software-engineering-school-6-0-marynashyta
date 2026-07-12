# testing.md Template

Inspect the project before filling values — never paste placeholders into output.
Specifically check: `composer.json "scripts"`, `docker-compose.test.yml` ports,
and health endpoint (this project uses `/`, not `/health`).

---

````markdown
# Testing Guide

## Prerequisites

Only the following tools need to be installed on your machine:

| Tool | Purpose |
| --- | --- |
| [Git](https://git-scm.com/) | Source control |
| [Docker](https://docs.docker.com/get-docker/) + Compose v2 | Integration and E2E tests |
| [Node.js](https://nodejs.org/) 20+ | E2E tests (used internally by Playwright PHP) |

PHP and Composer do **not** need to be installed locally —
they run inside Docker for integration tests; unit tests require a local PHP install.

---

## Run All Tests

```bash
make test
```

Without Make:

```bash
composer test                 # unit — fast, no Docker
make test-integration         # integration — full stack
make test-e2e                 # E2E — Playwright PHP
```

---

## Unit Tests

Tests complex business logic in isolation — no database, no HTTP, no Docker.

```bash
composer test
```

Test files: `tests/Unit/`

---

## Integration Tests

Tests every API endpoint — real HTTP against a real MySQL database.
Docker Compose builds the PHP app, MySQL, Redis, and Mailpit automatically.

```bash
# Build + start stack, run tests, tear down
make test-integration

# Or step by step:
docker compose -f docker-compose.test.yml up -d --build
until curl -so /dev/null http://localhost:8080/; do sleep 2; done
API_BASE_URL=http://localhost:8080 DB_HOST=localhost DB_PORT=3306 \
DB_NAME=release_notifications DB_USER=app DB_PASS=secret API_KEY="" \
vendor/bin/phpunit -c phpunit.integration.xml --colors=always
docker compose -f docker-compose.test.yml down -v
```

Test files: `tests/Integration/`

> **First run** takes ~2–3 min while Docker builds the image.
> Subsequent runs use the layer cache and finish in ~30–60 s.

---

## E2E Tests

Tests complete browser flows using Playwright PHP (pure PHP — no Node project).
Chromium is controlled against the same Dockerised app stack.

```bash
# Install browser binaries (once per machine)
vendor/bin/playwright-install --with-deps

# Start app stack
docker compose -f docker-compose.test.yml up -d --build
until curl -so /dev/null http://localhost:8080/; do sleep 2; done

# Run tests
APP_URL=http://localhost:8080 API_KEY="" \
vendor/bin/phpunit -c phpunit.e2e.xml --colors=always

# Or with Make
make test-e2e

# Tear down
docker compose -f docker-compose.test.yml down -v
```

Test files: `tests/E2E/`

> Screenshots of failing tests are saved to `tests/E2E/screenshots/`.

---

## Code Quality

```bash
# PHPStan (level 9)
composer lint

# PSR-12 check
composer cs

# Auto-fix PSR-12
composer cs-fix
```

---

## CI

Each test type runs in its own GitHub Actions workflow:

| File | Trigger | Time |
| --- | --- | --- |
| `.github/workflows/unit.yml` | Every push / PR | ~1 min |
| `.github/workflows/integration.yml` | Every push / PR | ~3–5 min |
| `.github/workflows/e2e.yml` | Push to `main` / PRs to `main` | ~10 min |

See `.github/workflows/` for full definitions.
````
