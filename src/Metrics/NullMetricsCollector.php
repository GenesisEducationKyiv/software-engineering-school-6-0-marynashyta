<?php

declare(strict_types=1);

namespace App\Metrics;

final class NullMetricsCollector implements MetricsCollectorInterface
{
    public function recordHttpRequest(string $method, string $route, int $status): void
    {
    }

    public function recordGithubApiCall(string $endpoint, bool $cacheHit): void
    {
    }

    public function recordNotificationSent(): void
    {
    }

    public function recordScannerCycle(): void
    {
    }

    public function recordHttpRequestDuration(string $method, string $route, float $durationSeconds): void
    {
    }
}
