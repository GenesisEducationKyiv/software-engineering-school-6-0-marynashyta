<?php

declare(strict_types=1);

namespace App\Metrics;

use App\Repository\SubscriptionRepositoryInterface;

final class DatabaseSubscriptionCounter implements ActiveSubscriptionCounterInterface
{
    public function __construct(private readonly SubscriptionRepositoryInterface $repository)
    {
    }

    public function countActive(): int
    {
        return $this->repository->countActive();
    }
}
