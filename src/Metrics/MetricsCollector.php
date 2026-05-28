<?php

declare(strict_types=1);

namespace App\Metrics;

use App\Cache\CacheInterface;

final class MetricsCollector implements MetricsCollectorInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function recordHttpRequest(string $method, string $route, int $status): void
    {
        $this->cache->hashIncrement(MetricsKeys::HTTP, "{$method}:{$route}:{$status}");
    }

    public function recordGithubApiCall(string $endpoint, bool $cacheHit): void
    {
        $hit = $cacheHit ? 'hit' : 'miss';
        $this->cache->hashIncrement(MetricsKeys::GITHUB, "{$endpoint}:{$hit}");
    }

    public function recordNotificationSent(): void
    {
        $this->cache->increment(MetricsKeys::NOTIFY);
    }

    public function recordScannerCycle(): void
    {
        $this->cache->increment(MetricsKeys::SCANNER);
    }

    public function recordHttpRequestDuration(string $method, string $route, float $durationSeconds): void
    {
        $field = "{$method}:{$route}";

        $le = '+Inf';
        foreach (MetricsKeys::HISTOGRAM_BUCKETS as $bucket) {
            if ($durationSeconds <= (float) $bucket) {
                $le = $bucket;
                break;
            }
        }
        $this->cache->hashIncrement(MetricsKeys::HTTP_DURATION_HIST, "{$field}:{$le}");
        $this->cache->hashIncrementFloat(MetricsKeys::HTTP_DURATION_SUM, $field, $durationSeconds);
    }
}
