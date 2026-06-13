<?php

declare(strict_types=1);

namespace App\Modules\Scanner\Domain;

interface LoggerInterface
{
    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void;
}
