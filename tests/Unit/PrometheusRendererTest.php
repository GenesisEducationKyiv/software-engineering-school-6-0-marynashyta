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

    #[Test]
    public function itRendersAllMetricsAtZeroWhenCacheIsEmpty(): void
    {
        $this->stubEmptyCache();

        self::assertSame($this->fixture('empty'), $this->renderer()->render());
    }

    #[Test]
    public function itRendersZeroActiveSubscriptionsWhenCounterThrows(): void
    {
        $this->stubEmptyCache();

        $counter = $this->createMock(ActiveSubscriptionCounterInterface::class);
        $counter->method('countActive')->willThrowException(new \RuntimeException('DB error'));

        self::assertSame($this->fixture('empty'), $this->renderer($counter)->render());
    }

    #[Test]
    public function itRendersHttpRequestCounter(): void
    {
        $this->cache->method('getAllHash')
            ->willReturnMap([
                ['rna:http_requests',      ['GET:/api/subscriptions:200' => '5']],
                ['rna:github_api_calls',   []],
                ['rna:http_duration_hist', []],
                ['rna:http_duration_sum',  []],
            ]);
        $this->stubIntMetrics();

        self::assertSame($this->fixture('http_request'), $this->renderer()->render());
    }

    #[Test]
    public function itRendersGitHubApiCallCounter(): void
    {
        $this->cache->method('getAllHash')
            ->willReturnMap([
                ['rna:http_requests',      []],
                ['rna:github_api_calls',   ['validate_repo:miss' => '3']],
                ['rna:http_duration_hist', []],
                ['rna:http_duration_sum',  []],
            ]);
        $this->stubIntMetrics();

        self::assertSame($this->fixture('github_api_call'), $this->renderer()->render());
    }

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

        self::assertSame($this->fixture('notifications'), $this->renderer()->render());
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

        self::assertSame($this->fixture('scanner_cycles'), $this->renderer()->render());
    }

    #[Test]
    public function itRendersOneWhenRedisIsConnected(): void
    {
        $this->stubEmptyAllHash();
        $this->cache->method('getInt')->willReturn(0);
        $this->cache->method('isConnected')->willReturn(true);

        self::assertSame($this->fixture('redis_connected'), $this->renderer()->render());
    }

    #[Test]
    public function itRendersActiveSubscriptionCountFromCounter(): void
    {
        $this->stubEmptyCache();

        $counter = $this->createMock(ActiveSubscriptionCounterInterface::class);
        $counter->method('countActive')->willReturn(13);

        self::assertSame($this->fixture('active_subscriptions'), $this->renderer($counter)->render());
    }

    #[Test]
    public function itRendersHistogramBucketsWithCumulativeCounts(): void
    {
        $this->cache->method('getAllHash')
            ->willReturnMap([
                ['rna:http_requests',      []],
                ['rna:github_api_calls',   []],
                ['rna:http_duration_hist', [
                    'GET:/api/subscriptions:100' => '1',
                    'GET:/api/subscriptions:500' => '1',
                ]],
                ['rna:http_duration_sum',  ['GET:/api/subscriptions' => '600']],
            ]);
        $this->stubIntMetrics();

        self::assertSame($this->fixture('histogram'), $this->renderer()->render());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function renderer(?ActiveSubscriptionCounterInterface $counter = null): PrometheusRenderer
    {
        return new PrometheusRenderer($this->cache, $counter);
    }

    private function fixture(string $name): string
    {
        $path    = __DIR__ . '/fixtures/prometheus/' . $name . '.txt';
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Fixture not found: {$path}");
        }
        return $content;
    }

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
