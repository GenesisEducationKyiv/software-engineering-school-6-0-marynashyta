---
name: php-unit-tests
description: Use when writing PHPUnit unit tests for complex PHP business logic in bare PHP (no frameworks). Triggers on "unit test", testing a class in isolation, mocks, test doubles, value objects, or domain logic. Always apply when the user wants to test complex conditionals, calculations, transformations, or domain rules — even without the words "unit test". Enforces PSR-12 and PHPStan level 9.
---

# PHP Unit Tests

Senior PHP engineer writing unit tests for complex business logic in isolation.
No network, no database, no Docker — pure PHP.

## When To Write Unit Tests

Write unit tests only for **complex logic**:

| Write unit tests | Skip — use integration tests |
|---|------------------------------|
| Value objects with validation | Thin controllers             |
| Domain services with branching rules | Simple DTOs                  |
| Calculations / transformations | DB query classes             |
| State machines, parsers | Glue code                    |

## Core Workflow

1. **Identify** — find classes with complex logic worth isolating
2. **Scaffold** — one test class per production class, mirroring namespace
3. **Cover** — happy path, edge cases, exceptions, data providers for boundaries
4. **Verify** — PHPStan max passes; PSR-12 clean; no slow/network/FS dependencies

## Reference Guide

| Topic | File | Load When |
|-------|------|-----------|
| Full worked examples: value objects, mocked services, state machines, `#[DataProvider]` | `patterns.md` | Writing first test for a class type |

## Structure — Arrange → Act → Assert

PHPUnit 11: use `#[Test]` attribute, not `/** @test */` docblock.

```php
#[Test]
public function itDoesX(): void
{
    $svc = new MyService($dep);          // Arrange
    $result = $svc->doX('input');        // Act
    self::assertSame('expected', $result); // Assert
}
```

## Mock Quick Reference

| Need | Code |
| --- | --- |
| Replace dependency | `$this->createMock(Interface::class)` |
| Expect called once | `->expects(self::once())` |
| Return value | `->method('x')->willReturn($val)` |
| Throw from dependency | `->willThrowException(new \Exception())` |
| Never called | `->expects(self::never())` |

Mock property type: intersection type — no `@var` annotation needed:
```php
private MyInterface&MockObject $dep;
```

## Constraints

### MUST DO
- Use `declare(strict_types=1)` in every file
- Follow Arrange → Act → Assert in every test
- Name tests `itDoesX`, `itThrowsWhenY`, `itReturnsNullIfZ`
- Use `#[DataProvider('provider')]` attribute (PHPUnit 11) for boundary cases
- Provider return type: `array<string, array{T1, T2, ...}>`

### MUST NOT DO
- Call `sleep()`, make HTTP requests, or touch the filesystem
- Put assertions in `setUp()`
- Use frameworks (Laravel, Symfony, etc.)
- Write unit tests for thin controllers or simple data classes