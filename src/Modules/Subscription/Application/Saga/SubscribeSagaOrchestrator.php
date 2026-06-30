<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application\Saga;

use App\Modules\Notification\Application\ConfirmationMailerInterface;
use App\Modules\Subscription\Application\SubscribeRequest;
use App\Modules\Subscription\Application\TokenGeneratorInterface;
use App\Modules\Subscription\Domain\Exception\SagaCompensatedException;
use App\Modules\Subscription\Domain\Saga\SagaRepositoryInterface;
use App\Modules\Subscription\Domain\Saga\SubscribeSaga;
use App\Modules\Subscription\Domain\SubscriptionRepositoryInterface;
use App\SharedKernel\Infrastructure\Database\TransactionManagerInterface;

final class SubscribeSagaOrchestrator implements SubscribeSagaOrchestratorInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SagaRepositoryInterface $sagaRepository,
        private readonly ConfirmationMailerInterface $mailer,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    /** @throws SagaCompensatedException */
    public function run(SubscribeRequest $request): void
    {
        $saga = SubscribeSaga::start($request->email, $request->repo);
        $this->sagaRepository->save($saga);

        $confirmToken     = $this->tokenGenerator->generate();
        $unsubscribeToken = $this->tokenGenerator->generate();

        // Step 1 — create subscription in the API service (local DB commit)
        $this->subscriptionRepository->create(
            $request->email,
            $request->repo,
            $confirmToken,
            $unsubscribeToken,
        );
        $saga = $saga->withSubscriptionCreated($confirmToken, $unsubscribeToken);
        $this->sagaRepository->save($saga);

        // Step 2 — dispatch to the Notification service via AMQP
        try {
            $this->mailer->sendConfirmation(
                $request->email,
                $request->repo,
                $confirmToken,
                $unsubscribeToken,
            );
            $this->sagaRepository->save($saga->withCompleted());
        } catch (\Throwable $e) {
            $this->compensate($saga, $e->getMessage());
            throw new SagaCompensatedException(
                "Subscription saga compensated: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    private function compensate(SubscribeSaga $saga, string $reason): void
    {
        $this->transactions->transactional(function () use ($saga, $reason): void {
            $this->sagaRepository->save($saga->withCompensating());
            $this->subscriptionRepository->deleteByEmailAndRepo($saga->email, $saga->repo);
            $this->sagaRepository->save($saga->withCompensated($reason));
        });
    }
}
