<?php

declare(strict_types=1);

namespace App\Services;

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
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    /**
     * @throws ValidationException
     * @throws InvalidRepositoryFormatException
     * @throws RepositoryNotFoundException
     * @throws RateLimitException
     * @throws AlreadySubscribedException
     */
    public function subscribe(string $email, string $repo): void
    {
        $this->assertValidEmail($email);

        $this->github->validateRepository($repo);

        if ($this->repository->existsByEmailAndRepo($email, $repo)) {
            throw new AlreadySubscribedException($email, $repo);
        }

        $confirmToken     = $this->tokenGenerator->generate();
        $unsubscribeToken = $this->tokenGenerator->generate();

        $this->repository->create($email, $repo, $confirmToken, $unsubscribeToken);
        $this->mailer->sendConfirmation($email, $repo, $confirmToken, $unsubscribeToken);
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

        if ($subscription['confirmed'] === 1) {
            return;
        }

        // Snapshot the current latest release so the subscriber is not notified
        // about releases that already existed at the time of subscription.
        $latestTag = $this->github->getLatestRelease($subscription['repo']);

        $this->repository->confirm($subscription['id'], $latestTag);
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

        $this->repository->delete($subscription['id']);
    }

    /**
     * @throws ValidationException
     * @return list<array{email: string, repo: string, confirmed: bool, last_seen_tag: string|null}>
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
