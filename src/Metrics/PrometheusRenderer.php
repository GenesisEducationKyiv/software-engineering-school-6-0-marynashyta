<?php

declare(strict_types=1);

namespace App\Metrics;

use App\Cache\CacheInterface;
use Throwable;

final class PrometheusRenderer implements MetricsRendererInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ?ActiveSubscriptionCounterInterface $counter = null,
    ) {
    }

    public function render(): string
    {
        $lines = [];

        $lines[] = '# HELP rna_http_requests_total Total number of HTTP requests processed';
        $lines[] = '# TYPE rna_http_requests_total counter';
        foreach ($this->cache->getAllHash(MetricsKeys::HTTP) as $field => $count) {
            $parts  = explode(':', $field, 3);
            $method = $parts[0];
            $route  = $parts[1] ?? '';
            $status = $parts[2] ?? '';
            $lines[] = "rna_http_requests_total{method=\"{$method}\",route=\"{$route}\",status=\"{$status}\"} {$count}";
        }

        $lines[] = '';
        $lines[] = '# HELP rna_github_api_calls_total Total GitHub API calls made';
        $lines[] = '# TYPE rna_github_api_calls_total counter';
        foreach ($this->cache->getAllHash(MetricsKeys::GITHUB) as $field => $count) {
            $parts       = explode(':', $field, 2);
            $endpoint    = $parts[0];
            $cacheStatus = $parts[1] ?? '';
            $lines[] = "rna_github_api_calls_total{endpoint=\"{$endpoint}\",cache=\"{$cacheStatus}\"} {$count}";
        }

        $lines[] = '';
        $lines[] = '# HELP rna_notifications_sent_total Total release notification emails sent by the scanner';
        $lines[] = '# TYPE rna_notifications_sent_total counter';
        $lines[] = 'rna_notifications_sent_total ' . $this->cache->getInt(MetricsKeys::NOTIFY);

        $lines[] = '';
        $lines[] = '# HELP rna_scanner_cycles_total Total background scanner cycles completed';
        $lines[] = '# TYPE rna_scanner_cycles_total counter';
        $lines[] = 'rna_scanner_cycles_total ' . $this->cache->getInt(MetricsKeys::SCANNER);

        $lines[] = '';
        $lines[] = '# HELP rna_subscriptions_active Current number of active (confirmed) subscriptions';
        $lines[] = '# TYPE rna_subscriptions_active gauge';
        $lines[] = 'rna_subscriptions_active ' . $this->countActiveSubscriptions();

        $lines[] = '';
        $lines[] = '# HELP rna_redis_connected Whether the Redis connection is healthy (1=yes, 0=no)';
        $lines[] = '# TYPE rna_redis_connected gauge';
        $lines[] = 'rna_redis_connected ' . ($this->cache->isConnected() ? 1 : 0);

        $lines[] = '';
        $lines[] = '# HELP rna_http_request_duration_seconds HTTP request latency histogram';
        $lines[] = '# TYPE rna_http_request_duration_seconds histogram';

        $histHash = $this->cache->getAllHash(MetricsKeys::HTTP_DURATION_HIST);
        $sumHash  = $this->cache->getAllHash(MetricsKeys::HTTP_DURATION_SUM);

        $series = [];
        foreach ($histHash as $field => $count) {
            $parts  = explode(':', $field, 3);
            $method = $parts[0];
            $route  = $parts[1] ?? '';
            $le     = $parts[2] ?? '';
            $series["{$method}:{$route}"][$le] = (int) $count;
        }

        foreach ($series as $key => $bucketCounts) {
            $parts  = explode(':', $key, 2);
            $method = $parts[0];
            $route  = $parts[1] ?? '';
            $cumulative = 0;
            foreach (MetricsKeys::HISTOGRAM_BUCKETS as $le) {
                $cumulative += ($bucketCounts[$le] ?? 0);
                $lines[] = sprintf(
                    'rna_http_request_duration_seconds_bucket{method="%s",route="%s",le="%s"} %d',
                    $method,
                    $route,
                    $le,
                    $cumulative,
                );
            }
            $cumulative += ($bucketCounts['+Inf'] ?? 0);
            $lines[] = sprintf(
                'rna_http_request_duration_seconds_bucket{method="%s",route="%s",le="+Inf"} %d',
                $method,
                $route,
                $cumulative,
            );
            $lines[] = sprintf(
                'rna_http_request_duration_seconds_sum{method="%s",route="%s"} %.6f',
                $method,
                $route,
                (float) ($sumHash[$key] ?? '0'),
            );
            $lines[] = "rna_http_request_duration_seconds_count{method=\"{$method}\",route=\"{$route}\"} {$cumulative}";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function countActiveSubscriptions(): int
    {
        try {
            return $this->counter?->countActive() ?? 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
