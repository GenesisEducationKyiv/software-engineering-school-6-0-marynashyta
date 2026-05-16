<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\Subscription;

interface SubscriptionScanRepositoryInterface
{
    /** @return list<Subscription> */
    public function findAllConfirmed(): array;

    public function updateLastSeenTag(int $id, string $tag): void;
}
