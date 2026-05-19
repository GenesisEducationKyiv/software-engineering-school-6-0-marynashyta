<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\SubscribeRequest;
use App\DTO\Subscription;
use App\Exceptions\AlreadySubscribedException;
use App\Exceptions\InvalidRepositoryFormatException;
use App\Exceptions\RateLimitException;
use App\Exceptions\RepositoryNotFoundException;
use App\Exceptions\TokenNotFoundException;
use App\Exceptions\ValidationException;
use App\Repository\SubscriptionRepositoryInterface;

final class SubscriptionService implements SubscriptionServiceInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $repository,
        private readonly GitHubServiceInterface $github,
        private readonly ConfirmationMailerInterface $mailer,
        private readonly TokenGeneratorInterface $tokenGenerator,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws InvalidRepositoryFormatException
     * @throws RepositoryNotFoundException
     * @throws RateLimitException
     * @throws AlreadySubscribedException
     */
    public function subscribe(SubscribeRequest $request): void
    {
        $this->assertValidEmail($request->email);

        $this->github->validateRepository($request->repo);

        if ($this->repository->existsByEmailAndRepo($request->email, $request->repo)) {
            throw new AlreadySubscribedException($request->email, $request->repo);
        }

        $confirmToken     = $this->tokenGenerator->generate();
        $unsubscribeToken = $this->tokenGenerator->generate();

        $this->repository->create($request->email, $request->repo, $confirmToken, $unsubscribeToken);
        $this->mailer->sendConfirmation($request->email, $request->repo, $confirmToken, $unsubscribeToken);
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
