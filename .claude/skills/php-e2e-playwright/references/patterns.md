# Playwright PHP — Advanced Patterns

> **PHPUnit 11** — use `#[Test]` attributes, not `/** @test */` docblocks.
> Playwright PHP (`playwright-php/playwright`) — no Node.js, no TypeScript.

---

## Base Test Case

```php
<?php

declare(strict_types=1);

namespace Tests\E2E;

use Playwright\Testing\PlaywrightTestCaseTrait;
use PHPUnit\Framework\TestCase;

abstract class AbstractE2ETestCase extends TestCase
{
    use PlaywrightTestCaseTrait;

    protected string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        $this->setUpPlaywright();
    }

    protected function tearDown(): void
    {
        if ($this->status()->isFailure() || $this->status()->isError()) {
            $name = str_replace(['\\', '::', ' '], '_', $this->nameWithDataSet());
            $path = __DIR__ . '/screenshots/' . $name . '.png';
            @mkdir(dirname($path), 0755, true);
            $this->page->screenshot($path);
        }

        $this->tearDownPlaywright();
        parent::tearDown();
    }

    protected function visit(string $path): void
    {
        $this->page->goto($this->baseUrl . $path);
    }
}
```

---

## Locator Guide

Prefer accessible selectors in this order:

```php
// 1. By role (most resilient)
$this->page->locator('[role="button"]');

// 2. By label text
$this->page->locator('label:has-text("Email") + input');

// 3. By name attribute
$this->page->locator('input[name="email"]');

// 4. By test ID (explicit and stable)
$this->page->locator('[data-testid="submit-btn"]');

// 5. By visible text (links and buttons)
$this->page->locator('text=Subscribe');

// Avoid — fragile
$this->page->locator('.btn-primary');
$this->page->locator('#submit');
$this->page->locator('form > div:nth-child(2) > input');
```

---

## Waiting Strategy

Playwright PHP auto-waits for elements. Never use `sleep()`.

```php
// Wait for navigation
$this->page->waitForURL($this->baseUrl . '/confirmed');

// Wait for element to appear
$this->page->locator('[role="alert"]')->waitFor();

// Wait for network response
$this->page->waitForResponse(
    fn ($r) => str_contains($r->url(), '/api/subscribe'),
    fn () => $this->page->locator('button[type="submit"]')->click(),
);

// Never do this
sleep(2);
```

---

## Screenshot on Failure

Already included in `AbstractE2ETestCase::tearDown()` above. Screenshots land in
`tests/E2E/screenshots/` and are uploaded as CI artifacts.

---

## CI (GitHub Actions)

```yaml
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

- name: Start app stack
  run: docker compose -f docker-compose.test.yml up -d --build

- name: Wait for app
  run: |
    for i in $(seq 1 40); do
      curl -sf http://localhost:8080/ && break
      sleep 3
    done
    curl -sf http://localhost:8080/ || exit 1

- name: Run E2E tests
  run: vendor/bin/phpunit -c phpunit.e2e.xml --colors=always
  env:
    APP_URL: http://localhost:8080
    API_KEY: ""

- name: Tear down
  if: always()
  run: docker compose -f docker-compose.test.yml down -v
```

> PHP version is **8.2** (set in `.github/actions/php-setup`).
> No `setup-node`, no `npm ci`, no `npx` — Playwright is pure PHP.
