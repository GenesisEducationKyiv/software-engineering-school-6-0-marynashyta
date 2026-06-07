<?php

declare(strict_types=1);

namespace App\Modules\GitHub\Domain\Exception;

use App\SharedKernel\Domain\HttpExceptionInterface;
use InvalidArgumentException;

final class InvalidRepositoryFormatException extends InvalidArgumentException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 400;
    }

    public function __construct(string $repo, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Invalid repository format '{$repo}'. Expected format: owner/repo",
            $code,
            $previous
        );
    }
}
