<?php

declare(strict_types=1);

namespace App\Cache;

final class NullCache implements CacheInterface
{
    public function get(string $key): ?string
    {
        return null;
    }

    public function set(string $key, string $value, int $ttl = 600): void
    {
    }

    public function increment(string $key): void
    {
    }

    public function hashIncrement(string $hash, string $field): void
    {
    }

    public function getInt(string $key): int
    {
        return 0;
    }

    /** @return array<string, string> */
    public function getAllHash(string $hash): array
    {
        return [];
    }

    public function isConnected(): bool
    {
        return false;
    }
}
