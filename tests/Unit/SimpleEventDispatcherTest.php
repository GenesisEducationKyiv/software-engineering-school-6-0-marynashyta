<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\SharedKernel\Domain\DomainEvent;
use App\SharedKernel\Infrastructure\Event\SimpleEventDispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SimpleEventDispatcherTest extends TestCase
{
    #[Test]
    public function dispatchInvokesListenersSubscribedToTheEventClass(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $received   = [];

        $dispatcher->subscribe(FakeDomainEvent::class, function (FakeDomainEvent $event) use (&$received): void {
            $received[] = $event->payload;
        });

        $dispatcher->dispatch(new FakeDomainEvent('first'));

        $this->assertSame(['first'], $received);
    }

    #[Test]
    public function dispatchDoesNothingWhenNoListenerIsSubscribed(): void
    {
        $dispatcher = new SimpleEventDispatcher();

        $dispatcher->dispatch(new FakeDomainEvent('unheard'));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function dispatchInvokesAllListenersSubscribedToTheSameEvent(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $calls      = 0;

        $dispatcher->subscribe(FakeDomainEvent::class, function () use (&$calls): void {
            $calls++;
        });
        $dispatcher->subscribe(FakeDomainEvent::class, function () use (&$calls): void {
            $calls++;
        });

        $dispatcher->dispatch(new FakeDomainEvent('payload'));

        $this->assertSame(2, $calls);
    }
}

final readonly class FakeDomainEvent implements DomainEvent
{
    public function __construct(public string $payload)
    {
    }
}
