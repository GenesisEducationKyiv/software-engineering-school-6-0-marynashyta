<?php

declare(strict_types=1);

namespace App\Metrics;

interface ActiveSubscriptionCounterInterface
{
    public function countActive(): int;
}
