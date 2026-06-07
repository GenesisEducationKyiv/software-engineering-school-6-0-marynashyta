<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Cache;

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
