<?php

declare(strict_types=1);

namespace NotificationService\Config;

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
