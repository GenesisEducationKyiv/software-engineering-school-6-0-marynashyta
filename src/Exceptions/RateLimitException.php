<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class RateLimitException extends RuntimeException
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

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
