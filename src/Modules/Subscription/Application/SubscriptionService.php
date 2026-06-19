<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application;

use App\Modules\GitHub\Domain\Exception\InvalidRepositoryFormatException;
use App\Modules\GitHub\Domain\Exception\RateLimitException;
use App\Modules\GitHub\Domain\Exception\RepositoryNotFoundException;
use App\Modules\GitHub\Domain\GitHubServiceInterface;
use App\Modules\Subscription\Application\Saga\SubscribeSagaOrchestratorInterface;
use App\Modules\Subscription\Domain\Exception\AlreadySubscribedException;
use App\Modules\Subscription\Domain\Exception\SagaCompensatedException;
use App\Modules\Subscription\Domain\Exception\TokenNotFoundException;
use App\Modules\Subscription\Domain\Exception\ValidationException;
use App\Modules\Subscription\Domain\Subscription;
use App\Modules\Subscription\Domain\SubscriptionRepositoryInterface;

final class SubscriptionService implements SubscriptionServiceInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $repository,
        private readonly GitHubServiceInterface $github,
        private readonly SubscribeSagaOrchestratorInterface $orchestrator,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws InvalidRepositoryFormatException
     * @throws RepositoryNotFoundException
     * @throws RateLimitException
     * @throws AlreadySubscribedException
     * @throws SagaCompensatedException
     */
    public function subscribe(SubscribeRequest $request): void
    {
        $this->assertValidEmail($request->email);

        $this->github->validateRepository($request->repo);

        if ($this->repository->existsByEmailAndRepo($request->email, $request->repo)) {
            throw new AlreadySubscribedException($request->email, $request->repo);
        }

        $this->orchestrator->run($request);
    }

    /**
     * Idempotent — confirming an already-confirmed subscription is a no-op.
     *
     * @throws TokenNotFoundException
     */
    public function confirm(string $token): void
    {
        $subscription = $this->repository->findByConfirmToken($token);

        if ($subscription === null) {
            throw new TokenNotFoundException($token);
        }

        if ($subscription->confirmed) {
            return;
        }

        $latestTag = $this->github->getLatestRelease($subscription->repo);

        $this->repository->confirm($subscription->id, $latestTag);
    }

    /**
     * @throws TokenNotFoundException
     */
    public function unsubscribe(string $token): void
    {
        $subscription = $this->repository->findByUnsubscribeToken($token);

        if ($subscription === null) {
            throw new TokenNotFoundException($token);
        }

        $this->repository->delete($subscription->id);
    }

    /**
     * @throws ValidationException
     * @return list<Subscription>
     */
    public function getSubscriptions(string $email): array
    {
        $this->assertValidEmail($email);
        return $this->repository->findConfirmedByEmail($email);
    }

    /**
     * @throws ValidationException
     */
    private function assertValidEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email');
        }
    }
}
