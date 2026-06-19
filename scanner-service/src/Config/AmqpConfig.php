<?php

declare(strict_types=1);

namespace ScannerService\Config;

final readonly class AmqpConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $user,
        public string $password,
    ) {
    }
}
