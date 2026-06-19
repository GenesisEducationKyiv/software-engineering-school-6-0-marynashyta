<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ScannerService\Infrastructure\CircuitBreaker;
use ScannerService\Infrastructure\CircuitOpenException;

final class CircuitBreakerTest extends TestCase
{
    #[Test]
    public function itPassesThroughOnSuccess(): void
    {
        $cb = new CircuitBreaker('test', threshold: 3, timeoutSeconds: 30);

        $result = $cb->call(fn () => 'ok');

        self::assertSame('ok', $result);
    }

    #[Test]
    public function itRethrowsExceptionsBelowThreshold(): void
    {
        $cb = new CircuitBreaker('test', threshold: 3, timeoutSeconds: 30);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upstream error');

        $cb->call(fn () => throw new \RuntimeException('upstream error'));
    }

    #[Test]
    public function itOpensAfterThresholdFailures(): void
    {
        $cb = new CircuitBreaker('test', threshold: 3, timeoutSeconds: 30);

        for ($i = 0; $i < 3; $i++) {
            try {
                $cb->call(fn () => throw new \RuntimeException('fail'));
            } catch (\RuntimeException) {
            }
        }

        $this->expectException(CircuitOpenException::class);

        $cb->call(fn () => 'should not reach here');
    }

    #[Test]
    public function itResetsFailureCountOnSuccess(): void
    {
        $cb = new CircuitBreaker('test', threshold: 3, timeoutSeconds: 30);

        for ($i = 0; $i < 2; $i++) {
            try {
                $cb->call(fn () => throw new \RuntimeException('fail'));
            } catch (\RuntimeException) {
            }
        }

        $cb->call(fn () => 'recovered');

        for ($i = 0; $i < 2; $i++) {
            try {
                $cb->call(fn () => throw new \RuntimeException('fail'));
            } catch (\RuntimeException) {
            }
        }

        $result = $cb->call(fn () => 'ok');
        self::assertSame('ok', $result);
    }

    #[Test]
    public function itIsNotOpenWhenBelowThreshold(): void
    {
        $cb = new CircuitBreaker('test', threshold: 3, timeoutSeconds: 30);

        self::assertFalse($cb->isOpen());

        try {
            $cb->call(fn () => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        self::assertFalse($cb->isOpen());
    }
}
