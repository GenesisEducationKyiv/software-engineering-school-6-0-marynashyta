<?php

declare(strict_types=1);

namespace App\Scanner;

final class EchoLogger implements LoggerInterface
{
    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        if ($context !== []) {
            try {
                $ctx = ' ' . json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (\JsonException) {
                $ctx = ' ' . print_r($context, true);
            }
        } else {
            $ctx = '';
        }
        echo '[' . date('Y-m-d H:i:s') . "] [{$level}] {$message}{$ctx}" . PHP_EOL;
    }
}
