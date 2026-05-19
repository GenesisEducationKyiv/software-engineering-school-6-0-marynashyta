<?php

declare(strict_types=1);

namespace App\Cache;

use Predis\Client as PredisClient;
use Throwable;

/**
 * Fail-safe Redis cache wrapper.
 *
 * All operations degrade silently to no-ops when Redis is unavailable,
 * so a Redis outage never affects the main application flow.
 */
class RedisCache implements CacheInterface
{
    private bool $connected = false;

    public function __construct(private ?RedisClientInterface $client)
    {
        if ($this->client !== null) {
            try {
                $this->client->ping();
                $this->connected = true;
            } catch (Throwable) {
                $this->connected = false;
            }
        }
    }

    /**
     * Factory: attempts to connect to Redis and returns a usable instance.
     * Never throws — returns an instance wrapping null on failure.
     */
    public static function create(string $host, int $port, int $db = 0): self
    {
        try {
            $client = new PredisClient([
                'scheme' => 'tcp',
                'host' => $host,
                'port' => $port,
                'database' => $db,
            ]);
            return new self(new PredisAdapter($client));
        } catch (Throwable $e) {
            error_log('[RedisCache] Connection failed: ' . $e->getMessage());
            return new self(null);
        }
    }

    public function get(string $key): ?string
    {
        if (!$this->connected || $this->client === null) {
            return null;
        }
        try {
            $value = $this->client->get($key);
            return is_string($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function set(string $key, string $value, int $ttl = 600): void
    {
        if (!$this->connected || $this->client === null) {
            return;
        }
        try {
            $this->client->setex($key, $ttl, $value);
        } catch (Throwable) {
        }
    }

    public function increment(string $key): void
    {
        if (!$this->connected || $this->client === null) {
            return;
        }
        try {
            $this->client->incr($key);
        } catch (Throwable) {
        }
    }

    public function hashIncrement(string $hash, string $field): void
    {
        if (!$this->connected || $this->client === null) {
            return;
        }
        try {
            $this->client->hincrby($hash, $field, 1);
        } catch (Throwable) {
        }
    }

    public function getInt(string $key): int
    {
        if (!$this->connected || $this->client === null) {
            return 0;
        }
        try {
            $raw = $this->client->get($key);
            return is_numeric($raw) ? (int)$raw : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getAllHash(string $hash): array
    {
        if (!$this->connected || $this->client === null) {
            return [];
        }
        try {
            return $this->client->hgetall($hash) ?? [];
        } catch (Throwable) {
            return [];
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }
}
