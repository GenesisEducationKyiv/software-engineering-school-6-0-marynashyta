---
name: php-e2e-playwright
description: Use when writing E2E browser tests with Playwright for a PHP application. Triggers on "e2e", "playwright", "browser test", "end-to-end", or testing pages, user flows, or UI interactions. Always apply when testing involves a real browser — login flows, forms, navigation, visual checks — even without the words "Playwright". Tests are pure PHP using playwright-php/playwright + PHPUnit. No TypeScript.
---

# PHP E2E Tests — Playwright PHP

Uses `playwright-php/playwright` with `PlaywrightTestCaseTrait` inside PHPUnit.

> **Note**: Node.js 20+ must be installed (used internally by the Playwright server — invisible to tests).

## Core Workflow

1. **Start app** — PHP app runs in Docker Compose (see `docker.md`)
2. **Scaffold** — `AbstractE2ETestCase`, one-Page Object per page, one test class per flow
3. **Cover** — page loads, happy paths, validation errors, auth guards (see table)
4. **Verify** — PHPStan max passes; PSR-12 clean

## Reference Guide

| Topic | File | Load When |
|-------|------|-----------|
| docker-compose.e2e.yml, Dockerfile, seed endpoint | `.claude/skills/php-e2e-playwright/references/docker.md` | Setting up app infrastructure |
| Storage state, auth reuse, screenshots on failure, locator guide, CI YAML | `.claude/skills/php-e2e-playwright/references/patterns.md` | Advanced test patterns |

## Scenario Coverage

| Scenario | What to assert |
|---|---|
| Page loads | Visible key elements, correct title |
| Happy path | URL change, success message |
| Validation error | Error text visible, no navigation |
| Auth guard | Redirect to `/login` when unauthenticated |
| Authorisation | 403 or redirect for wrong role |
| Empty state | Empty list message visible |

## Base Pattern

```php
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
        $this->tearDownPlaywright();
        parent::tearDown();
    }

    protected function visit(string $path): void
    {
        $this->page->goto($this->baseUrl . $path);
    }
}
```

## Composer

```bash
composer require --dev playwright-php/playwright
vendor/bin/playwright-install --with-deps   # once per machine
```

## Constraints

### MUST DO
- Use `declare(strict_types=1)` in every file
- One-Page Object class per page — keep locators out of test methods
- Call `setUpPlaywright()` / `tearDownPlaywright()` in every test case
- Prefer accessible locators: `[role]`, `[name]`, `[data-testid]` over CSS classes

### MUST NOT DO
- Call `sleep()` — use `waitForURL()`, `waitFor()`, or auto-wait locators
- Share browser context between tests without explicit storage state
- Use frameworks (Laravel, Symfony, etc.)