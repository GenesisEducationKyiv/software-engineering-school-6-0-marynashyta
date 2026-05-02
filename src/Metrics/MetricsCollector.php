<?php

declare(strict_types=1);

namespace App\Metrics;

use App\Cache\RedisCache;
use PDO;
use Throwable;

final class MetricsCollector
{
    private const KEY_HTTP = 'rna:http_requests';
    private const KEY_GITHUB = 'rna:github_api_calls';
    private const KEY_NOTIFY = 'rna:notifications_sent';
    private const KEY_SCANNER = 'rna:scanner_cycles';

    public function __construct(
        private RedisCache $cache,
        private ?PDO $db = null
    ) {
    }

    public function recordHttpRequest(string $method, string $route, int $status): void
    {
        $this->cache->hashIncrement(self::KEY_HTTP, "{$method}:{$route}:{$status}");
    }

    public function recordGithubApiCall(string $endpoint, bool $cacheHit): void
    {
        $hit = $cacheHit ? 'hit' : 'miss';
        $this->cache->hashIncrement(self::KEY_GITHUB, "{$endpoint}:{$hit}");
    }

    public function recordNotificationSent(): void
    {
        $this->cache->increment(self::KEY_NOTIFY);
    }

    public function recordScannerCycle(): void
    {
        $this->cache->increment(self::KEY_SCANNER);
    }

    public function render(): string
    {
        $lines = [];

        $lines[] = '# HELP rna_http_requests_total Total number of HTTP requests processed';
        $lines[] = '# TYPE rna_http_requests_total counter';
        foreach ($this->cache->getAllHash(self::KEY_HTTP) as $field => $count) {
            $parts = explode(':', $field, 3);
            $method = $parts[0];
            $route = $parts[1] ?? '';
            $status = $parts[2] ?? '';
            $lines[] = "rna_http_requests_total{method=\"{$method}\",route=\"{$route}\",status=\"{$status}\"} {$count}";
        }

        $lines[] = '';
        $lines[] = '# HELP rna_github_api_calls_total Total GitHub API calls made';
        $lines[] = '# TYPE rna_github_api_calls_total counter';
        foreach ($this->cache->getAllHash(self::KEY_GITHUB) as $field => $count) {
            $parts = explode(':', $field, 2);
            $endpoint = $parts[0];
            $cache = $parts[1] ?? '';
            $lines[] = "rna_github_api_calls_total{endpoint=\"{$endpoint}\",cache=\"{$cache}\"} {$count}";
        }

        $lines[] = '';
        $lines[] = '# HELP rna_notifications_sent_total Total release notification emails sent by the scanner';
        $lines[] = '# TYPE rna_notifications_sent_total counter';
        $lines[] = 'rna_notifications_sent_total ' . $this->cache->getInt(self::KEY_NOTIFY);

        $lines[] = '';
        $lines[] = '# HELP rna_scanner_cycles_total Total background scanner cycles completed';
        $lines[] = '# TYPE rna_scanner_cycles_total counter';
        $lines[] = 'rna_scanner_cycles_total ' . $this->cache->getInt(self::KEY_SCANNER);

        $lines[] = '';
        $lines[] = '# HELP rna_subscriptions_active Current number of active (confirmed) subscriptions';
        $lines[] = '# TYPE rna_subscriptions_active gauge';
        $lines[] = 'rna_subscriptions_active ' . $this->countActiveSubscriptions();

        $lines[] = '';
        $lines[] = '# HELP rna_redis_connected Whether the Redis connection is healthy (1=yes, 0=no)';
        $lines[] = '# TYPE rna_redis_connected gauge';
        $lines[] = 'rna_redis_connected ' . ($this->cache->isConnected() ? 1 : 0);

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function countActiveSubscriptions(): int
    {
        if ($this->db === null) {
            return 0;
        }
        try {
            $stmt = $this->db->query('SELECT COUNT(*) FROM subscriptions WHERE confirmed = 1');
            return $stmt !== false ? (int)$stmt->fetchColumn() : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
