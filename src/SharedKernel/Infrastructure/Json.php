<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure;

use JsonException;

final class Json
{
    /**
     * @return array<string, mixed>
     * @throws JsonException on malformed input
     */
    public static function decode(string $json): array
    {
        $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @throws JsonException on un-encodable value
     */
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
