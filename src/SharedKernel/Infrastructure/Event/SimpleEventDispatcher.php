<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Event;

use App\SharedKernel\Domain\DomainEvent;

final class SimpleEventDispatcher implements EventDispatcherInterface
{
    /** @var array<class-string<DomainEvent>, list<callable(DomainEvent): void>> */
    private array $listeners = [];

    /** @param class-string<DomainEvent> $eventClass */
    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(DomainEvent $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }
    }
}
