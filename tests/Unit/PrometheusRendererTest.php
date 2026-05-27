<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Cache\CacheInterface;
use App\Metrics\ActiveSubscriptionCounterInterface;
use App\Metrics\PrometheusRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PrometheusRendererTest extends TestCase
{
    private CacheInterface&MockObject $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->createMock(CacheInterface::class);
    }

    private function renderer(?ActiveSubscriptionCounterInterface $counter = null): PrometheusRenderer
    {
        return new PrometheusRenderer($this->cache, $counter);
    }

    // ── Section headers ───────────────────────────────────────────────────────

    #[Test]
    public function itContainsAllExpectedMetricHelpLines(): void
    {
        $this->stubEmptyCache();

        $output = $this->renderer()->render();

        self::assertStringContainsString('rna_http_requests_total', $output);
        self::assertStringContainsString('rna_github_api_calls_total', $output);
        self::assertStringContainsString('rna_notifications_sent_total', $output);
        self::assertStringContainsString('rna_scanner_cycles_total', $output);
        self::assertStringContainsString('rna_subscriptions_active', $output);
        self::assertStringContainsString('rna_redis_connected', $output);
    }

    // ── HTTP request metrics ──────────────────────────────────────────────────

    #[Test]
    public function itRendersHttpRequestCounterWithCorrectLabels(): void
    {
        $this->cache->method('getAllHash')
            ->willReturnMap([
                ['rna:http_requests',    ['GET:/api/subscriptions:200' => '5']],
                ['rna:github_api_calls', []],
            ]);
        $this->stubIntMetrics();

        $output = $this->renderer()->render();

        self::assertStringContainsString(
            'rna_http_requests_total{method="GET",route="/api/subscriptions",status="200"} 5',
            $output,
        );
    }

    #[Test]
    public function itRendersNoHttpCounterLinesWhenHashIsEmpty(): void
    {
        $this->stubEmptyCache();

        $output = $this->renderer()->render();

        self::assertStringNotContainsString('rna_http_requests_total{', $output);
    }

    // ── GitHub API call metrics ───────────────────────────────────────────────

    #[Test]
    public function itRendersGitHubApiCallCounterWithCorrectLabels(): void
    {
        $this->cache->method('getAllHash')
            ->willReturnMap([
                ['rna:http_requests',    []],
                ['rna:github_api_calls', ['validate_repo:miss' => '3']],
            ]);
        $this->stubIntMetrics();

        $output = $this->renderer()->render();

        self::assertStringContainsString(
            'rna_github_api_calls_total{endpoint="validate_repo",cache="miss"} 3',
            $output,
        );
    }

    // ── Scalar counters ───────────────────────────────────────────────────────

    #[Test]
    public function itRendersNotificationCount(): void
    {
        $this->stubEmptyAllHash();
        $this->cache->method('getInt')
            ->willReturnMap([
                ['rna:notifications_sent', 42],
                ['rna:scanner_cycles',      0],
            ]);
        $this->cache->method('isConnected')->willReturn(false);

        $output = $this->renderer()->render();

        self::assertStringContainsString('rna_notifications_sent_total 42', $output);
    }

    #[Test]
    public function itRendersZeroNotificationCountWhenNoneSent(): void
    {
        $this->stubEmptyCache();

        $output = $this->renderer()->render();

        self::assertStringContainsString('rna_notifications_sent_total 0', $output);
    }

    #[Test]
    public function itRendersScannercyclesCount(): void
    {
        $this->stubEmptyAllHash();
        $this->cache->method('getInt')
            ->willReturnMap([
                ['rna:notifications_sent',  0],
                ['rna:scanner_cycles',      7],
            ]);
        $this->cache->method('isConnected')->willReturn(false);

        $output = $this->renderer()->render();

        self::assertStringContainsString('rna_scanner_cycles_total 7', $output);
    }

    // ── Redis connection ──────────────────────────────────────────────────────

    #[Test]
    public function itRendersOneWhenRedisIsConnected(): void
    {
        $this->stubEmptyAllHash();
        $this->cache->method('getInt')->willReturn(0);
        $this->cache->method('isConnected')->willReturn(true);

        $output = $this->renderer()->render();

        self::assertStringContainsString('rna_redis_connected 1', $output);
    }

    #[Test]
    public function itRendersZeroWhenRedisIsDisconnected(): void
    {
        $this->stubEmptyCache();

        $output = $this->renderer()->render();

        self::assertStringContainsString('rna_redis_connected 0', $output);
    }

    // ── Active subscriptions ──────────────────────────────────────────────────

    #[Test]
    public function itRendersZeroActiveSubscriptionsWhenCounterIsNull(): void
    {
        $this->stubEmptyCache();

        $output = $this->renderer(counter: null)->render();

        self::assertStringContainsString('rna_subscriptions_active 0', $output);
    }

    #[Test]
    public function itRendersActiveSubscriptionCountFromCounter(): void
    {
        $this->stubEmptyCache();

        /** @var ActiveSubscriptionCounterInterface&MockObject $counter */
        $counter = $this->createMock(ActiveSubscriptionCounterInterface::class);
        $counter->method('countActive')->willReturn(13);

        $output = $this->renderer(counter: $counter)->render();

        self::assertStringContainsString('rna_subscriptions_active 13', $output);
    }

    #[Test]
    public function itRendersZeroActiveSubscriptionsWhenCounterThrows(): void
    {
        $this->stubEmptyCache();

        /** @var ActiveSubscriptionCounterInterface&MockObject $counter */
        $counter = $this->createMock(ActiveSubscriptionCounterInterface::class);
        $counter->method('countActive')->willThrowException(new \RuntimeException('DB error'));

        $output = $this->renderer(counter: $counter)->render();

        self::assertStringContainsString('rna_subscriptions_active 0', $output);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function stubEmptyAllHash(): void
    {
        $this->cache->method('getAllHash')->willReturn([]);
    }

    private function stubEmptyCache(): void
    {
        $this->cache->method('getAllHash')->willReturn([]);
        $this->cache->method('getInt')->willReturn(0);
        $this->cache->method('isConnected')->willReturn(false);
    }

    private function stubIntMetrics(): void
    {
        $this->cache->method('getInt')->willReturn(0);
        $this->cache->method('isConnected')->willReturn(false);
    }
}
