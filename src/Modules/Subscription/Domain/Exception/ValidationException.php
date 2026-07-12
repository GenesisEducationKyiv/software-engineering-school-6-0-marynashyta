<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Domain\Exception;

use App\SharedKernel\Domain\HttpExceptionInterface;
use InvalidArgumentException;

final class ValidationException extends InvalidArgumentException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 400;
    }
}
