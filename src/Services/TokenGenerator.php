<?php

declare(strict_types=1);

namespace App\Services;

final class TokenGenerator implements TokenGeneratorInterface
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
