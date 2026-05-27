<?php

declare(strict_types=1);

namespace App\Scanner;

interface LoggerInterface
{
    public function log(string $level, string $message): void;
}
