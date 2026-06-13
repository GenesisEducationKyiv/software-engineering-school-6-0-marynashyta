<?php

declare(strict_types=1);

namespace App\Modules\Observability\Domain;

interface ActiveSubscriptionCounterInterface
{
    public function countActive(): int;
}
