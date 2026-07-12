<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Domain\Exception;

use App\SharedKernel\Domain\HttpExceptionInterface;
use RuntimeException;

final class SagaCompensatedException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 503;
    }
}
