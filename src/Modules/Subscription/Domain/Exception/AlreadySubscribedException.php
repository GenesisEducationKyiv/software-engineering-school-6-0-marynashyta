<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Domain\Exception;

use App\SharedKernel\Domain\HttpExceptionInterface;
use RuntimeException;

final class AlreadySubscribedException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 409;
    }

    public function __construct(string $email, string $repo, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Email '{$email}' is already subscribed to '{$repo}'",
            $code,
            $previous
        );
    }
}
