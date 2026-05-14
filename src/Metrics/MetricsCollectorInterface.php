<?php

declare(strict_types=1);

namespace App\Metrics;

interface MetricsCollectorInterface
{
    public function recordHttpRequest(string $method, string $route, int $status): void;

    public function recordGithubApiCall(string $endpoint, bool $cacheHit): void;

    public function recordNotificationSent(): void;

    public function recordScannerCycle(): void;

    public function render(): string;
}
