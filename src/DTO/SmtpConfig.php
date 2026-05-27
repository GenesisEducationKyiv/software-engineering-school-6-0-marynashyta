<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class SmtpConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public string $fromAddress,
        public string $fromName,
    ) {
    }
}
