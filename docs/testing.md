# Testing Guide

## Prerequisites

| Tool | Purpose |
|---|---|
| [Git](https://git-scm.com/) | Source control |
| [Docker](https://docs.docker.com/get-docker/) + Compose v2 | Integration and E2E app stack |

PHP and Composer must also be installed locally to run any test suite directly.
Integration and E2E tests additionally require Docker to run the application stack.

---

## Run All Tests

```bash
composer test                    # unit tests (fast, no Docker)
composer test:integration        # integration tests (Docker stack)
composer test:e2e                # E2E browser tests (Docker stack + Playwright)
```

---

## Unit Tests

Tests complex business logic in isolation — no database, no HTTP, no Docker.

```bash
composer test
```

Test files: `tests/Unit/`

Runs in under 1 second. No external services required.

### Coverage (118 tests)

| Class | What is tested |
| --- | --- |
| `ApiKeyMiddleware` | Auth bypass (empty key, OPTIONS, `/metrics`), valid key forwarded, 401 with JSON body |
| `Env` | `string()`, `int()`, `bool()` with absent keys, non-string values, truthy/falsy strings |
| `GitHubService` | Format validation, 404/429 from GitHub API, `Retry-After` header, Bearer token header |
| `MetricsMiddleware` | `recordHttpRequest` called per request with correct method, route, and status; route normalisation for all known paths |
| `PrometheusRenderer` | All metric sections rendered, correct labels, null/active subscription counter, Redis health |
| `RedisCache` | Fail-safe degradation when Redis is unavailable, all cache operations |
| `ReleaseScanner` | Grouping by repo, new-release detection, rate limit / generic exception recovery |
| `SubscriptionService` | Email validation, duplicate detection, confirm idempotency, token not found |

---

## Integration Tests

Tests every API endpoint with real HTTP requests against a real MySQL database.
Uses `docker-compose.test.yml` which provides the test stack (API, MySQL, Redis, Mailpit).

> **Note:** The MySQL container is internal to the Docker network. To allow the test process on
> the host to reach MySQL directly, expose port 3306 when starting the stack.
> Then add `DB_HOST=localhost` and `DB_PORT=3306` to your environment, or run the tests
> inside a container that shares the Docker network.

```bash
# Start the test stack
docker compose -f docker-compose.test.yml up -d --build

# Wait for the API to be ready
until curl -so /dev/null http://localhost:8080/; do echo "waiting..."; sleep 2; done

# Run tests
vendor/bin/phpunit -c phpunit.integration.xml --colors=always

# Tear down
docker compose -f docker-compose.test.yml down -v
```

Or via Composer script (assumes the stack is already up):

```bash
composer test:integration
```

Test files: `tests/Integration/`

> **First run** takes ~2–3 min while Docker builds the image.
> Subsequent runs use the layer cache and finish in ~30–60 s.

### Environment variables

The bootstrap reads these from the shell environment before falling back to defaults:

| Variable | Default | Description |
|---|---|---|
| `API_BASE_URL` | `http://localhost:8080` | URL of the running API |
| `DB_HOST` | `localhost` | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_NAME` | `release_notifications` | Database name |
| `DB_USER` | `app` | Database user |
| `DB_PASS` | `secret` | Database password |
| `API_KEY` | _(empty)_ | Set to enable auth middleware tests |
| `GITHUB_TOKEN` | _(empty)_ | GitHub personal token — prevents rate limiting |

### API contract notes

| Scenario | HTTP status |
|---|---|
| Missing or wrong `X-API-Key` | 401 |
| Invalid email or missing fields | 400 |
| Invalid repository format | 400 |
| Repository not found on GitHub | 404 |
| **GitHub rate limit hit** | **429** |
| Email already subscribed | 409 |
| Token not found (confirm/unsubscribe) | 404 |

---

## E2E Tests

Tests complete browser flows (subscribe form) using Playwright PHP against the live app.
`docker-compose.test.yml` builds the app stack; Playwright controls a real Chromium browser.

```bash
# Install Playwright browsers (first time only — ~200 MB download)
vendor/bin/playwright-install --with-deps

# Start the E2E stack
docker compose -f docker-compose.test.yml up -d --build

# Wait for the API to be ready
until curl -so /dev/null http://localhost:8080/; do echo "waiting..."; sleep 2; done

# Run tests
composer test:e2e

# Tear down
docker compose -f docker-compose.test.yml down -v
```

Test files: `tests/E2E/`

> **First run** takes ~5 min to build the image and download Playwright browsers.
> Browser binaries are cached in `~/.cache/ms-playwright` after the first install.

### What is tested

| Test | Flow |
|---|---|
| Page loads | `subscribe.html` title and form elements visible |
| Happy path | Fill email + repo → submit → success alert → form resets |
| Button state | Submit disabled while pending, re-enabled after response |
| Validation errors | Empty email, empty repo, invalid repo format → error alert |
| Server errors | Non-existent GitHub repository → error alert |

---

## Code Quality

```bash
# PHPStan level 9
composer lint

# PSR-12 check
composer cs

# PSR-12 auto-fix
composer cs-fix
```

---

## CI

Each test type runs in its own GitHub Actions workflow:

| File | Trigger | Approx. time |
|---|---|---|
| `.github/workflows/ci.yml` | Every push / PR | ~2 min (lint + Docker build) |
| `.github/workflows/unit.yml` | Every push / PR | ~30 s |
| `.github/workflows/integration.yml` | Every push / PR | ~3–5 min |
| `.github/workflows/e2e.yml` | Push to `main` / PRs to `main` | ~10 min |

Separate workflow files mean a failing integration run does not block unit or E2E feedback.

The E2E workflow uses `${{ secrets.GITHUB_TOKEN }}` automatically.
For integration tests that hit the real GitHub API, add a `GITHUB_TOKEN` repository secret with a personal access token to avoid anonymous rate-limiting.
