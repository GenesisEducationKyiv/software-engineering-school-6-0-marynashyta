<?php

declare(strict_types=1);

namespace App\Scanner;

final class EchoLogger implements LoggerInterface
{
    public function log(string $level, string $message): void
    {
        echo '[' . date('Y-m-d H:i:s') . "] [{$level}] {$message}" . PHP_EOL;
    }
}
