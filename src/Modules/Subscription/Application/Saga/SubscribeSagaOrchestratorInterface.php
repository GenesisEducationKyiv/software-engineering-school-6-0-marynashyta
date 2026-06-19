<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application\Saga;

use App\Modules\Subscription\Application\SubscribeRequest;
use App\Modules\Subscription\Domain\Exception\SagaCompensatedException;

interface SubscribeSagaOrchestratorInterface
{
    /** @throws SagaCompensatedException */
    public function run(SubscribeRequest $request): void;
}
