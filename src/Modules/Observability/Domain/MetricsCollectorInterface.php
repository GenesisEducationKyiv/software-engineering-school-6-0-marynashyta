<?php

declare(strict_types=1);

namespace App\Modules\Observability\Domain;

interface MetricsCollectorInterface
{
    public function recordHttpRequest(string $method, string $route, int $status): void;

    public function recordGithubApiCall(string $endpoint, bool $cacheHit): void;

    public function recordNotificationSent(): void;

    public function recordScannerCycle(): void;

    public function recordHttpRequestDuration(string $method, string $route, float $durationMs): void;
}
