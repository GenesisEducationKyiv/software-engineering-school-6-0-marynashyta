<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Subscription\Application\Acl\ConfirmationGatewayInterface;
use App\Modules\Subscription\Application\Acl\RepositoryGatewayInterface;
use App\Modules\Subscription\Application\SubscribeRequest;
use App\Modules\Subscription\Application\SubscriptionService;
use App\Modules\Subscription\Application\TokenGenerator;
use App\Modules\Subscription\Domain\Exception\AlreadySubscribedException;
use App\Modules\Subscription\Domain\Exception\TokenNotFoundException;
use App\Modules\Subscription\Domain\Exception\ValidationException;
use App\Modules\Subscription\Domain\Subscription;
use App\Modules\Subscription\Domain\SubscriptionRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SubscriptionServiceTest extends TestCase
{
    private SubscriptionRepositoryInterface&MockObject $repository;
    private RepositoryGatewayInterface&MockObject $github;
    private ConfirmationGatewayInterface&MockObject $mailer;
    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $this->github     = $this->createMock(RepositoryGatewayInterface::class);
        $this->mailer     = $this->createMock(ConfirmationGatewayInterface::class);
        $this->service    = new SubscriptionService(
            $this->repository,
            $this->github,
            $this->mailer,
            new TokenGenerator(),
        );
    }

    #[Test]
    public function subscribeValidatesRepositoryAndPersistsSubscription(): void
    {
        $this->repository->method('existsByEmailAndRepo')->willReturn(false);

        $this->github->expects($this->once())
            ->method('assertRepositoryExists')
            ->with('owner/repo');

        $this->repository->expects($this->once())
            ->method('create')
            ->with(
                'user@example.com',
                'owner/repo',
                $this->matchesRegularExpression('/^[0-9a-f]{64}$/'),
                $this->matchesRegularExpression('/^[0-9a-f]{64}$/')
            );

        $this->mailer->expects($this->once())
            ->method('sendConfirmation')
            ->with(
                'user@example.com',
                'owner/repo',
                $this->matchesRegularExpression('/^[0-9a-f]{64}$/'),
                $this->matchesRegularExpression('/^[0-9a-f]{64}$/')
            );

        $this->service->subscribe(new SubscribeRequest('user@example.com', 'owner/repo'));
    }

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function subscribeWithInvalidEmailThrowsValidationException(string $email): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid email');

        $this->github->expects($this->never())->method('assertRepositoryExists');
        $this->repository->expects($this->never())->method('create');
        $this->mailer->expects($this->never())->method('sendConfirmation');

        $this->service->subscribe(new SubscribeRequest($email, 'owner/repo'));
    }

    #[Test]
    public function subscribeDuplicateThrowsAlreadySubscribedException(): void
    {
        $this->expectException(AlreadySubscribedException::class);

        $this->github->expects($this->once())->method('assertRepositoryExists');
        $this->repository->expects($this->once())
            ->method('existsByEmailAndRepo')
            ->with('user@example.com', 'owner/repo')
            ->willReturn(true);

        $this->repository->expects($this->never())->method('create');
        $this->mailer->expects($this->never())->method('sendConfirmation');

        $this->service->subscribe(new SubscribeRequest('user@example.com', 'owner/repo'));
    }

    // ─── confirm ─────────────────────────────────────────────────────────────

    #[Test]
    public function confirmSetsLastSeenTagAndMarksSubscriptionConfirmed(): void
    {
        $token = str_repeat('a', 64);

        $this->repository->expects($this->once())
            ->method('findByConfirmToken')
            ->with($token)
            ->willReturn(new Subscription(1, 'user@example.com', 'owner/repo', false, null, 'tok'));

        $this->github->expects($this->once())
            ->method('findLatestReleaseTag')
            ->with('owner/repo')
            ->willReturn('v1.0.0');

        $this->repository->expects($this->once())
            ->method('confirm')
            ->with(1, 'v1.0.0');

        $this->service->confirm($token);
    }

    #[Test]
    public function confirmIsIdempotentWhenAlreadyConfirmed(): void
    {
        $token = str_repeat('b', 64);

        $this->repository->expects($this->once())
            ->method('findByConfirmToken')
            ->with($token)
            ->willReturn(new Subscription(2, 'user@example.com', 'owner/repo', true, 'v1.0.0', 'tok'));

        $this->github->expects($this->never())->method('findLatestReleaseTag');
        $this->repository->expects($this->never())->method('confirm');

        $this->service->confirm($token);
    }

    #[Test]
    public function confirmWithUnknownTokenThrowsTokenNotFoundException(): void
    {
        $this->expectException(TokenNotFoundException::class);

        $token = str_repeat('c', 64);

        $this->repository->expects($this->once())
            ->method('findByConfirmToken')
            ->with($token)
            ->willReturn(null);

        $this->service->confirm($token);
    }

    #[Test]
    public function unsubscribeDeletesSubscription(): void
    {
        $token = str_repeat('d', 64);

        $this->repository->expects($this->once())
            ->method('findByUnsubscribeToken')
            ->with($token)
            ->willReturn(new Subscription(7, 'user@example.com', 'owner/repo', true, null, $token));

        $this->repository->expects($this->once())
            ->method('delete')
            ->with(7);

        $this->service->unsubscribe($token);
    }

    #[Test]
    public function unsubscribeWithUnknownTokenThrowsTokenNotFoundException(): void
    {
        $this->expectException(TokenNotFoundException::class);

        $token = str_repeat('e', 64);

        $this->repository->expects($this->once())
            ->method('findByUnsubscribeToken')
            ->with($token)
            ->willReturn(null);

        $this->service->unsubscribe($token);
    }

    #[Test]
    public function getSubscriptionsDelegatesToRepositoryAndReturnsResult(): void
    {
        $expected = [
            new Subscription(1, 'user@example.com', 'owner/repo1', true, 'v1.0.0', 'tok'),
        ];

        $this->repository->expects($this->once())
            ->method('findConfirmedByEmail')
            ->with('user@example.com')
            ->willReturn($expected);

        $result = $this->service->getSubscriptions('user@example.com');

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function getSubscriptionsReturnsEmptyArrayWhenNoneExist(): void
    {
        $this->repository->method('findConfirmedByEmail')->willReturn([]);

        $result = $this->service->getSubscriptions('nobody@example.com');

        $this->assertSame([], $result);
    }

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function getSubscriptionsWithInvalidEmailThrowsValidationException(string $email): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid email');

        $this->repository->expects($this->never())->method('findConfirmedByEmail');

        $this->service->getSubscriptions($email);
    }


    /**
     * @return array<string, array{string}>
     */
    public static function invalidEmailProvider(): array
    {
        return [
            'empty string' => [''],
            'no @ symbol' => ['notanemail'],
            'missing domain' => ['user@'],
            'missing user' => ['@example.com'],
            'spaces' => ['user @example.com'],
            'double @' => ['user@@example.com'],
        ];
    }
}
