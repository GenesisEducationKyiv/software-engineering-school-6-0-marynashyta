<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

interface HttpExceptionInterface
{
    public function getStatusCode(): int;
}
