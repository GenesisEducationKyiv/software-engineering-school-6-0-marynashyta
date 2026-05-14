<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\Subscription;

interface SubscriptionRepositoryInterface
{
    public function existsByEmailAndRepo(string $email, string $repo): bool;

    public function create(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken,
    ): void;

    public function findByConfirmToken(string $token): ?Subscription;

    public function confirm(int $id, ?string $lastSeenTag): void;

    public function findByUnsubscribeToken(string $token): ?Subscription;

    public function delete(int $id): void;

    /** @return list<Subscription> */
    public function findConfirmedByEmail(string $email): array;

    /** @return list<Subscription> */
    public function findAllConfirmed(): array;

    public function updateLastSeenTag(int $id, string $tag): void;
}
