<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Domain\Exception;

use App\SharedKernel\Domain\HttpExceptionInterface;
use RuntimeException;

final class TokenNotFoundException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 404;
    }

    public function __construct(string $token = '', int $code = 0, ?\Throwable $previous = null)
    {
        $message = $token !== ''
            ? "Token not found: {$token}"
            : 'Token not found';

        parent::__construct($message, $code, $previous);
    }
}
