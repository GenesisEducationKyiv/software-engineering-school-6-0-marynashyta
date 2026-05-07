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

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $repository,
        private readonly GitHubServiceInterface $github,
        private readonly EmailServiceInterface $email,
    ) {
    }

    /**
     * Subscribe an email address to release notifications for a GitHub repository.
     *
     * @throws ValidationException              if email is invalid
     * @throws InvalidRepositoryFormatException if repo format is invalid
     * @throws RepositoryNotFoundException      if repo doesn't exist on GitHub
     * @throws RateLimitException               if GitHub rate limit is hit
     * @throws AlreadySubscribedException       if already subscribed
     */
    public function subscribe(string $email, string $repo): void
    {
        $this->assertValidEmail($email);

        $this->github->validateRepository($repo);

        if ($this->repository->existsByEmailAndRepo($email, $repo)) {
            throw new AlreadySubscribedException($email, $repo);
        }

        $confirmToken     = bin2hex(random_bytes(32));
        $unsubscribeToken = bin2hex(random_bytes(32));

        $this->repository->create($email, $repo, $confirmToken, $unsubscribeToken);
        $this->email->sendConfirmation($email, $repo, $confirmToken, $unsubscribeToken);
    }

    /**
     * Confirm a pending subscription using the confirmation token.
     * Idempotent — confirming an already-confirmed subscription is a no-op.
     *
     * @throws TokenNotFoundException if token is not found
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
     * Permanently delete a subscription using the unsubscribe token.
     *
     * @throws TokenNotFoundException if token is not found
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
     * Return all confirmed subscriptions for an email address.
     *
     * @throws ValidationException if email is invalid
     * @return list<array{email: string, repo: string, confirmed: bool, last_seen_tag: string|null}>
     */
    public function getSubscriptions(string $email): array
    {
        $this->assertValidEmail($email);
        return $this->repository->findConfirmedByEmail($email);
    }

    /**
     * @throws ValidationException if the address does not pass RFC validation
     */
    private function assertValidEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email');
        }
    }
}
