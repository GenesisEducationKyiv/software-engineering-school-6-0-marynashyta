# PHPStan — Project Configuration

## phpstan.neon

The project runs PHPStan at **level 9** (not `max`). Do not raise this without coordinating
with the team. The config covers `src`, `bin`, and `public` — tests are excluded.

```neon
parameters:
    level: 9
    paths:
        - src
        - bin
        - public
    excludePaths:
        - vendor
```

---

## Running PHPStan

```bash
# Via Composer script (matches CI)
composer lint

# Direct invocation
vendor/bin/phpstan analyse
```

---

## Common Errors & Fixes

### `json_decode` returns `mixed`

```php
// ✗ PHPStan complains: mixed
$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

// ✓ Annotate with array shape
/** @var array<string, mixed> $data */
$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
```

### PDO `fetch()` / `fetchAll()` annotation

```php
$stmt->execute([$token]);

/** @var array<string, mixed>|false $row */
$row = $stmt->fetch();

/** @var list<array<string, mixed>> $rows */
$rows = $stmt->fetchAll();
```

### Mock intersection types in tests

PHPUnit 11 — declare mock properties with intersection types directly; no `@var` needed:

```php
private SubscriptionRepositoryInterface&MockObject $repository;

protected function setUp(): void
{
    parent::setUp();
    $this->repository = $this->createMock(SubscriptionRepositoryInterface::class);
}
```

### `PDO::fetchColumn()` returns `mixed`

```php
$stmt = $this->db->query('SELECT COUNT(*) FROM subscriptions WHERE confirmed = 1');
return $stmt !== false ? (int) $stmt->fetchColumn() : 0;
```
