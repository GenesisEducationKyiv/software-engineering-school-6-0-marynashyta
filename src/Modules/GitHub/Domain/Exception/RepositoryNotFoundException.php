<?php

declare(strict_types=1);

namespace App\Modules\GitHub\Domain\Exception;

use App\SharedKernel\Domain\HttpExceptionInterface;
use RuntimeException;

final class RepositoryNotFoundException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 404;
    }

    public function __construct(string $repo, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Repository not found: {$repo}", $code, $previous);
    }
}
