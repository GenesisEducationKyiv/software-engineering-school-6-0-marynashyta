<?php

declare(strict_types=1);

namespace App\Cache;

use Predis\Client as PredisClient;

/**
 * Adapter that wraps a Predis\Client and exposes it through RedisClientInterface.
 *
 * Predis dispatches Redis commands via __call, so this adapter forwards each
 * named method explicitly to give callers (and static analysis) a typed surface.
 */
final class PredisAdapter implements RedisClientInterface
{
    public function __construct(private readonly PredisClient $client)
    {
    }

    public function ping(): mixed
    {
        return $this->client->ping();
    }

    public function get(string $key): mixed
    {
        return $this->client->get($key);
    }

    public function setex(string $key, int $seconds, string $value): mixed
    {
        return $this->client->setex($key, $seconds, $value);
    }

    public function incr(string $key): mixed
    {
        return $this->client->incr($key);
    }

    public function hincrby(string $key, string $field, int $increment): mixed
    {
        return $this->client->hincrby($key, $field, $increment);
    }

    /** @return array<string, string>|null */
    public function hgetall(string $key): array|null
    {
        /** @var array<string, string>|null $result */
        $result = $this->client->hgetall($key);
        return $result;
    }
}
