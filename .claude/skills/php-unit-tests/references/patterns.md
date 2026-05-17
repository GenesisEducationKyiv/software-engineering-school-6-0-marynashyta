# Unit Test Patterns — Worked Examples

> **PHPUnit 11** — use `#[Test]` and `#[DataProvider]` attributes, not docblock tags.
> Mock properties use intersection types: `Interface&MockObject $prop`.

## 1. Value Object

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\SubscribeRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SubscribeRequestTest extends TestCase
{
    #[Test]
    public function itHoldsEmailAndRepo(): void
    {
        $req = new SubscribeRequest('user@example.com', 'owner/repo');

        self::assertSame('user@example.com', $req->email);
        self::assertSame('owner/repo', $req->repo);
    }

    #[Test]
    #[DataProvider('emailProvider')]
    public function itStoresEmailAsGiven(string $email): void
    {
        $req = new SubscribeRequest($email, 'owner/repo');

        self::assertSame($email, $req->email);
    }

    /** @return array<string, array{string}> */
    public static function emailProvider(): array
    {
        return [
            'standard'     => ['user@example.com'],
            'subdomain'    => ['user@mail.example.com'],
            'plus address' => ['user+tag@example.com'],
        ];
    }
}
```

---

## 2. Service with Mocked Dependencies

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\SubscribeRequest;
use App\Repository\SubscriptionRepositoryInterface;
use App\Services\ConfirmationMailerInterface;
use App\Services\GitHubServiceInterface;
use App\Services\SubscriptionService;
use App\Services\TokenGeneratorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SubscriptionServiceTest extends TestCase
{
    // Intersection-type property — no @var annotation needed
    private SubscriptionRepositoryInterface&MockObject $repository;
    private GitHubServiceInterface&MockObject $github;
    private ConfirmationMailerInterface&MockObject $mailer;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository     = $this->createMock(SubscriptionRepositoryInterface::class);
        $this->github         = $this->createMock(GitHubServiceInterface::class);
        $this->mailer         = $this->createMock(ConfirmationMailerInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGeneratorInterface::class);

        $this->service = new SubscriptionService(
            $this->repository,
            $this->github,
            $this->mailer,
            $this->tokenGenerator,
        );
    }

    #[Test]
    public function itCreatesSubscriptionAndSendsConfirmation(): void
    {
        $this->github->expects(self::once())->method('validateRepository');
        $this->repository->method('existsByEmailAndRepo')->willReturn(false);
        $this->tokenGenerator->method('generate')->willReturn('tok');

        $this->repository->expects(self::once())->method('create');
        $this->mailer->expects(self::once())->method('sendConfirmation');

        $this->service->subscribe(new SubscribeRequest('user@example.com', 'owner/repo'));
    }

    #[Test]
    public function itNeverSendsEmailWhenRepositoryDoesNotExist(): void
    {
        $this->github->method('validateRepository')
            ->willThrowException(new \App\Exceptions\RepositoryNotFoundException('owner/repo'));

        $this->mailer->expects(self::never())->method('sendConfirmation');

        $this->expectException(\App\Exceptions\RepositoryNotFoundException::class);
        $this->service->subscribe(new SubscribeRequest('user@example.com', 'owner/repo'));
    }
}
```

---

## 3. Exception Assertions

```php
// Assert exception type only
$this->expectException(\InvalidArgumentException::class);

// Assert message substring
$this->expectExceptionMessage('must be positive');

// Assert exact code
$this->expectExceptionCode(422);

// All three — place before the line that throws
$this->expectException(\DomainException::class);
$this->expectExceptionMessage('cannot exceed');
$this->expectExceptionCode(0);
```

> `expectException*` calls must come **before** the line that throws.

---

## 4. `#[DataProvider]` Conventions (PHPUnit 11)

- Provider method must be `public static`
- Return type: `array<string, array{T1, T2, ...}>` (named keys = readable failure output)
- Reference via `#[DataProvider('methodName')]` attribute — **not** `@dataProvider` docblock

```php
#[Test]
#[DataProvider('invalidEmailProvider')]
public function itRejectsInvalidEmail(string $email): void
{
    $this->expectException(\App\Exceptions\ValidationException::class);

    $this->service->subscribe(new SubscribeRequest($email, 'owner/repo'));
}

/** @return array<string, array{string}> */
public static function invalidEmailProvider(): array
{
    return [
        'empty string'  => [''],
        'no @ symbol'   => ['userexample.com'],
        'missing domain'=> ['user@'],
        'double @'      => ['u@@example.com'],
    ];
}
```

---

## 5. Mock Quick Reference

| Need | Code |
| --- | --- |
| Replace dependency | `$this->createMock(Interface::class)` |
| Expect called once | `->expects(self::once())` |
| Return value | `->method('x')->willReturn($val)` |
| Throw from dependency | `->willThrowException(new \Exception())` |
| Never called | `->expects(self::never())` |
| Consecutive returns | `->willReturnOnConsecutiveCalls($a, $b)` |

---

## 6. State Machine / Complex Branching

```php
#[Test]
public function itSkipsNotificationWhenTagMatchesLastSeen(): void
{
    $this->repository->method('findAllConfirmed')->willReturn([
        new Subscription(1, 'user@example.com', 'owner/repo', true, 'v1.0.0', 'tok'),
    ]);
    $this->github->method('getLatestRelease')->willReturn('v1.0.0');

    $this->mailer->expects(self::never())->method('sendReleaseNotification');

    $this->scanner->scan();
}

#[Test]
public function itContinuesAfterFirstRepoFails(): void
{
    $this->github->expects(self::exactly(2))
        ->method('getLatestRelease')
        ->willReturnOnConsecutiveCalls(
            self::throwException(new \RuntimeException('network error')),
            'v1.0.0',
        );

    $this->mailer->expects(self::once())->method('sendReleaseNotification');

    $this->scanner->scan();
}
```
