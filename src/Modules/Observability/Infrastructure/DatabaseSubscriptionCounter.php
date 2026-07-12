<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure;

use App\Modules\Observability\Domain\ActiveSubscriptionCounterInterface;
use App\Modules\Subscription\Domain\SubscriptionRepositoryInterface;

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
