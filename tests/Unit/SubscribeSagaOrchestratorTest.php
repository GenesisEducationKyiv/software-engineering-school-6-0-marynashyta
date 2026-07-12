<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Notification\Application\ConfirmationMailerInterface;
use App\Modules\Subscription\Application\Saga\SubscribeSagaOrchestrator;
use App\Modules\Subscription\Application\SubscribeRequest;
use App\Modules\Subscription\Application\TokenGenerator;
use App\Modules\Subscription\Application\TransactionManagerInterface;
use App\Modules\Subscription\Domain\Exception\SagaCompensatedException;
use App\Modules\Subscription\Domain\Saga\SagaRepositoryInterface;
use App\Modules\Subscription\Domain\Saga\SagaState;
use App\Modules\Subscription\Domain\Saga\SubscribeSaga;
use App\Modules\Subscription\Domain\SubscriptionRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SubscribeSagaOrchestratorTest extends TestCase
{
    private SubscriptionRepositoryInterface&MockObject $subscriptionRepository;
    private SagaRepositoryInterface&MockObject $sagaRepository;
    private ConfirmationMailerInterface&MockObject $mailer;
    private TransactionManagerInterface&MockObject $transactions;
    private SubscribeSagaOrchestrator $orchestrator;

    #[Test]
    public function itCreatesSubscriptionAndDispatchesConfirmationEmail(): void
    {
        $request = new SubscribeRequest('user@example.com', 'owner/repo');

        $this->subscriptionRepository->expects($this->once())
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

        $this->sagaRepository->expects($this->exactly(3))->method('save');

        $this->orchestrator->run($request);
    }

    #[Test]
    public function itPersistsCorrectSagaStateTransitionsOnSuccess(): void
    {
        /** @var list<SagaState> $savedStates */
        $savedStates = [];

        $this->sagaRepository->method('save')
            ->willReturnCallback(static function (SubscribeSaga $saga) use (&$savedStates): void {
                $savedStates[] = $saga->state;
            });

        $this->orchestrator->run(new SubscribeRequest('user@example.com', 'owner/repo'));

        $this->assertSame(
            [SagaState::Started, SagaState::SubscriptionCreated, SagaState::Completed],
            $savedStates,
        );
    }

    #[Test]
    public function itCompensatesAndRethrowsOnEmailFailure(): void
    {
        $this->mailer->method('sendConfirmation')
            ->willThrowException(new \RuntimeException('AMQP connection refused'));

        $this->subscriptionRepository->expects($this->once())->method('create');
        $this->subscriptionRepository->expects($this->once())
            ->method('deleteByEmailAndRepo')
            ->with('user@example.com', 'owner/repo');

        $this->expectException(SagaCompensatedException::class);

        $this->orchestrator->run(new SubscribeRequest('user@example.com', 'owner/repo'));
    }

    #[Test]
    public function itPersistsSagaCompensationStatesOnEmailFailure(): void
    {
        /** @var list<SagaState> $savedStates */
        $savedStates = [];

        $this->sagaRepository->method('save')
            ->willReturnCallback(static function (SubscribeSaga $saga) use (&$savedStates): void {
                $savedStates[] = $saga->state;
            });

        $this->mailer->method('sendConfirmation')
            ->willThrowException(new \RuntimeException('fail'));

        try {
            $this->orchestrator->run(new SubscribeRequest('user@example.com', 'owner/repo'));
        } catch (SagaCompensatedException) {
        }

        $this->assertSame(
            [
                SagaState::Started,
                SagaState::SubscriptionCreated,
                SagaState::Compensating,
                SagaState::Compensated,
            ],
            $savedStates,
        );
    }

    #[Test]
    public function itStoresCompensationReasonInFinalSagaState(): void
    {
        /** @var list<SubscribeSaga> $savedSagas */
        $savedSagas = [];

        $this->sagaRepository->method('save')
            ->willReturnCallback(static function (SubscribeSaga $saga) use (&$savedSagas): void {
                $savedSagas[] = $saga;
            });

        $this->mailer->method('sendConfirmation')
            ->willThrowException(new \RuntimeException('timeout'));

        try {
            $this->orchestrator->run(new SubscribeRequest('user@example.com', 'owner/repo'));
        } catch (SagaCompensatedException) {
        }

        $final = end($savedSagas);
        $this->assertInstanceOf(SubscribeSaga::class, $final);
        $this->assertSame(SagaState::Compensated, $final->state);
        $this->assertSame('timeout', $final->compensationReason);
    }

    #[Test]
    public function compensationRunsInsideASingleTransaction(): void
    {
        $this->mailer->method('sendConfirmation')
            ->willThrowException(new \RuntimeException('fail'));

        $this->transactions = $this->createMock(TransactionManagerInterface::class);
        $this->transactions->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation());

        $orchestrator = new SubscribeSagaOrchestrator(
            $this->subscriptionRepository,
            $this->sagaRepository,
            $this->mailer,
            new TokenGenerator(),
            $this->transactions,
        );

        try {
            $orchestrator->run(new SubscribeRequest('user@example.com', 'owner/repo'));
        } catch (SagaCompensatedException) {
        }
    }

    #[Test]
    public function transactionalIsNeverInvokedOnTheHappyPath(): void
    {
        $this->transactions = $this->createMock(TransactionManagerInterface::class);
        $this->transactions->expects($this->never())->method('transactional');

        $orchestrator = new SubscribeSagaOrchestrator(
            $this->subscriptionRepository,
            $this->sagaRepository,
            $this->mailer,
            new TokenGenerator(),
            $this->transactions,
        );

        $orchestrator->run(new SubscribeRequest('user@example.com', 'owner/repo'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = $this->createMock(SubscriptionRepositoryInterface::class);
        $this->sagaRepository         = $this->createMock(SagaRepositoryInterface::class);
        $this->mailer                 = $this->createMock(ConfirmationMailerInterface::class);
        $this->transactions           = $this->createMock(TransactionManagerInterface::class);
        $this->transactions->method('transactional')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation());

        $this->orchestrator = new SubscribeSagaOrchestrator(
            $this->subscriptionRepository,
            $this->sagaRepository,
            $this->mailer,
            new TokenGenerator(),
            $this->transactions,
        );
    }
}
