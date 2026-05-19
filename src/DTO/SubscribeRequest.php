<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class SubscribeRequest
{
    public function __construct(
        public string $email,
        public string $repo,
    ) {
    }
}
