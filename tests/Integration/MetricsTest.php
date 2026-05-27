<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for GET /metrics.
 *
 * The metrics endpoint is exempt from API key authentication and returns
 * Prometheus exposition format (text/plain).
 */
final class MetricsTest extends AbstractApiTestCase
{
    #[Test]
    public function itReturns200WithPrometheusContentType(): void
    {
        $response = $this->http->get('/metrics');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/plain', $response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function itResponseContainsExpectedMetricNames(): void
    {
        $response = $this->http->get('/metrics');
        $body     = (string) $response->getBody();

        $this->assertStringContainsString('rna_http_requests_total', $body);
        $this->assertStringContainsString('rna_github_api_calls_total', $body);
        $this->assertStringContainsString('rna_notifications_sent_total', $body);
        $this->assertStringContainsString('rna_scanner_cycles_total', $body);
        $this->assertStringContainsString('rna_subscriptions_active', $body);
        $this->assertStringContainsString('rna_redis_connected', $body);
    }

    #[Test]
    public function itDoesNotRequireApiKey(): void
    {
        $response = $this->http->get('/metrics', [
            'headers' => ['X-API-Key' => 'INVALID'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
    }
}
