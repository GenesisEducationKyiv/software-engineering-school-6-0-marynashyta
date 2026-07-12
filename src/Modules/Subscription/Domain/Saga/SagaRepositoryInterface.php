<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Domain\Saga;

interface SagaRepositoryInterface
{
    public function save(SubscribeSaga $saga): void;

    public function findById(string $id): ?SubscribeSaga;
}
