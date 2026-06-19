<?php

declare(strict_types=1);

namespace ScannerService\Infrastructure;

final class CircuitBreaker
{
    private int $failures = 0;
    private float $openedAt = 0.0;

    public function __construct(
        private readonly string $name,
        private readonly int $threshold = 5,
        private readonly int $timeoutSeconds = 30,
    ) {
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     * @throws CircuitOpenException when the circuit is open
     */
    public function call(callable $fn): mixed
    {
        if ($this->isOpen()) {
            throw new CircuitOpenException(
                "Circuit '{$this->name}' is open — upstream is unavailable. " .
                "Retry after {$this->timeoutSeconds}s."
            );
        }

        try {
            $result = $fn();
            $this->reset();
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function isOpen(): bool
    {
        if ($this->failures < $this->threshold) {
            return false;
        }

        // Allow one probe request once the timeout has elapsed (half-open)
        if ((microtime(true) - $this->openedAt) >= $this->timeoutSeconds) {
            $this->reset();
            return false;
        }

        return true;
    }

    private function recordFailure(): void
    {
        $this->failures++;
        if ($this->failures === $this->threshold) {
            $this->openedAt = microtime(true);
        }
    }

    private function reset(): void
    {
        $this->failures = 0;
        $this->openedAt = 0.0;
    }
}
