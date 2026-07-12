<?php

declare(strict_types=1);

namespace App\Modules\Scanner\Infrastructure;

use App\Modules\Scanner\Domain\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

final class MonologLogger implements LoggerInterface
{
    public function __construct(private readonly PsrLoggerInterface $logger)
    {
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log(strtolower($level), $message, $context);
    }
}
