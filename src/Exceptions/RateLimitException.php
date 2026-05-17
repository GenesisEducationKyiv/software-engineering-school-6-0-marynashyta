<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class RateLimitException extends RuntimeException implements HttpExceptionInterface
{
    private readonly int $retryAfter;

    public function __construct(int $retryAfter = 60, int $code = 0, ?\Throwable $previous = null)
    {
        $this->retryAfter = $retryAfter;
        parent::__construct(
            "GitHub API rate limit exceeded. Retry after {$retryAfter} seconds.",
            $code,
            $previous
        );
    }

    public function getStatusCode(): int
    {
        return 429;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
