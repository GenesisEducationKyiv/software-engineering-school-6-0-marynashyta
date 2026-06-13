<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Slim interface over the Redis commands used by RedisCache.
 *
 * Decouples RedisCache from the Predis client so tests can mock it
 * without relying on PHPUnit's deprecated addMethods() for __call-based clients.
 */
interface RedisClientInterface
{
    public function ping(): mixed;

    public function get(string $key): mixed;

    public function setex(string $key, int $seconds, string $value): mixed;

    public function incr(string $key): mixed;

    public function hincrby(string $key, string $field, int $increment): mixed;

    public function hincrbyfloat(string $key, string $field, float $value): mixed;

    /** @return array<string, string>|null */
    public function hgetall(string $key): array|null;
}
