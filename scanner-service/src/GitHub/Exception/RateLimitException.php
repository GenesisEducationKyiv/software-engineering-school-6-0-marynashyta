<?php

declare(strict_types=1);

namespace ScannerService\GitHub\Exception;

final class RateLimitException extends \RuntimeException
{
    public function __construct(private readonly int $retryAfter, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("GitHub rate limit exceeded. Retry after {$retryAfter}s.", $code, $previous);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
