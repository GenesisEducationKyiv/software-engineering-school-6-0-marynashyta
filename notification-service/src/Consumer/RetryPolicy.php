<?php

declare(strict_types=1);

namespace NotificationService\Consumer;

final readonly class RetryPolicy
{
    public function __construct(private int $maxAttempts = 3)
    {
    }

    public function isRetryable(\Throwable $e): bool
    {
        return !$e instanceof \InvalidArgumentException && !$e instanceof \JsonException;
    }

    public function shouldRetry(\Throwable $e, int $attempt): bool
    {
        return $this->isRetryable($e) && $attempt < $this->maxAttempts;
    }
}
