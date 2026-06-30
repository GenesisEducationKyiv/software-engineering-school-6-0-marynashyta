<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

use Throwable;

interface HttpExceptionInterface extends Throwable
{
    public function getStatusCode(): int;
}
