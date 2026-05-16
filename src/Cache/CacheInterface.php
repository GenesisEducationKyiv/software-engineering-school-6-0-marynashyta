<?php

declare(strict_types=1);

namespace App\Cache;

interface CacheInterface
{
    public function get(string $key): ?string;

    public function set(string $key, string $value, int $ttl = 600): void;

    public function increment(string $key): void;

    public function hashIncrement(string $hash, string $field): void;

    public function getInt(string $key): int;

    /** @return array<string, string> */
    public function getAllHash(string $hash): array;

    public function isConnected(): bool;
}
