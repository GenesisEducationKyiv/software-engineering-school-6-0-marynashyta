<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Event;

use App\SharedKernel\Domain\DomainEvent;

interface EventDispatcherInterface
{
    public function dispatch(DomainEvent $event): void;
}
