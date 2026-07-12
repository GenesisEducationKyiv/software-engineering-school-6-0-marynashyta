---
name: php-integration-tests
description: Use when writing integration tests for PHP API endpoints in bare PHP (no frameworks). Triggers on "integration", testing HTTP controllers, routes, API contracts, or full request-response cycles. Always apply when the user mentions PHPUnit + real HTTP, Guzzle, endpoint coverage, or Docker test environment — even without the words "integration test". Enforces PSR-12 and PHPStan max.
---

# PHP Integration Tests

Senior PHP engineer implementing integration tests for REST API endpoints.
Tests hit a real running server with a real database — no mocks at the HTTP layer.

## Core Workflow

1. **Inspect** — find routes, controllers, or OpenAPI spec; list every endpoint + method
2. **Scaffold** — generate `AbstractApiTestCase` and one test class per resource
3. **Cover** — happy path + all error scenarios per endpoint (see table below)
4. **Verify** — PHPStan max passes; PSR-12 clean

## Reference Guide

| Topic | File | Load When |
|-------|------|-----------|
| Docker Compose, Dockerfile, wait-for-http, migrate | `docker.md` | Setting up test infrastructure |
| PHPStan config, common type fixes | `phpstan.md` | Configuring static analysis |

## Scenario Coverage

Every endpoint must have tests for all applicable cases:

| Scenario | Status |
|---|---|
| Valid input | 200 / 201 |
| Missing required fields | 422 |
| Wrong type / format | 422 |
| Auth token missing | 401 |
| Authenticated, not authorised | 403 |
| Resource not found | 404 |
| Duplicate / conflict | 409 |
| Method not allowed | 405 |

## Base Pattern

```php
abstract class AbstractApiTestCase extends TestCase
{
    protected Client $http;
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new Client(['base_uri' => $_ENV['API_BASE_URL'], 'http_errors' => false]);
        $this->pdo  = new PDO(/* dsn from $_ENV */);
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->pdo->inTransaction() && $this->pdo->rollBack();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    protected function assertJsonResponse(int $status, ResponseInterface $r): array { /* ... */ }

    protected function insertFixture(string $table, mixed ...$args): int { /* ... */ }
}
```

## Constraints

### MUST DO
- Use `declare(strict_types=1)` in every file
- Wrap every test in a DB transaction; rollback in `tearDown()`
- Set Guzzle `http_errors => false` so 4xx/5xx can be asserted
- Run PHPStan (`composer lint`) before committing — project uses level 9

### MUST NOT DO
- Mock the HTTP layer in integration tests
- Share database state between tests
- Use frameworks (Laravel, Symfony, etc.)
- Hardcode ports or credentials — use `$_ENV`