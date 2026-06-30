<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application;

use App\Modules\Subscription\Application\Acl\ConfirmationGatewayInterface;
use App\Modules\Subscription\Application\Acl\RepositoryGatewayInterface;
use App\Modules\Subscription\Domain\Exception\AlreadySubscribedException;
use App\Modules\Subscription\Domain\Exception\TokenNotFoundException;
use App\Modules\Subscription\Domain\Exception\ValidationException;
use App\Modules\Subscription\Domain\Subscription;
use App\Modules\Subscription\Domain\SubscriptionRepositoryInterface;
use App\SharedKernel\Domain\HttpExceptionInterface;

final class SubscriptionService implements SubscriptionServiceInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $repository,
        private readonly RepositoryGatewayInterface $repositoryGateway,
        private readonly ConfirmationGatewayInterface $confirmationGateway,
        private readonly TokenGeneratorInterface $tokenGenerator,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws HttpExceptionInterface
     * @throws AlreadySubscribedException
     */
    public function subscribe(SubscribeRequest $request): void
    {
        $this->assertValidEmail($request->email);

        $this->repositoryGateway->assertRepositoryExists($request->repo);

        if ($this->repository->existsByEmailAndRepo($request->email, $request->repo)) {
            throw new AlreadySubscribedException($request->email, $request->repo);
        }

        $confirmToken     = $this->tokenGenerator->generate();
        $unsubscribeToken = $this->tokenGenerator->generate();

        $this->repository->create($request->email, $request->repo, $confirmToken, $unsubscribeToken);
        $this->confirmationGateway->sendConfirmation($request->email, $request->repo, $confirmToken, $unsubscribeToken);
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

        // Snapshot the current latest release so the subscriber is not notified
        // about releases that already existed at the time of subscription.
        $latestTag = $this->repositoryGateway->findLatestReleaseTag($subscription->repo);

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
