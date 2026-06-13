<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Metrics\MetricsCollectorInterface;
use App\Middleware\MetricsMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MetricsMiddlewareTest extends TestCase
{
    private MetricsCollectorInterface&MockObject $metrics;
    private MetricsMiddleware $middleware;

    // ── Metric recording ──────────────────────────────────────────────────────

    #[Test]
    public function itRecordsHttpRequest(): void
    {
        $this->metrics
            ->expects(self::once())
            ->method('recordHttpRequest')
            ->with('POST', '/api/subscribe', 201);

        $this->middleware->process(
            $this->makeRequest('POST', '/api/subscribe'),
            $this->makeHandler(201),
        );
    }

    #[Test]
    public function itReturnsTheHandlerResponse(): void
    {
        $this->metrics->method('recordHttpRequest');
        $this->metrics->method('recordHttpRequestDuration');

        $result = $this->middleware->process(
            $this->makeRequest('GET', '/metrics'),
            $this->makeHandler(200),
        );

        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function itRecordsHttpRequestDuration(): void
    {
        $this->metrics->method('recordHttpRequest');
        $this->metrics
            ->expects(self::once())
            ->method('recordHttpRequestDuration')
            ->with('GET', '/api/subscriptions', self::callback(
                fn(float $d): bool => $d >= 0.0
            ));

        $this->middleware->process(
            $this->makeRequest('GET', '/api/subscriptions'),
            $this->makeHandler(200),
        );
    }

    #[Test]
    public function itRecordsStatus500AndRethrowsWhenHandlerThrows(): void
    {
        $this->metrics
            ->expects(self::once())
            ->method('recordHttpRequest')
            ->with('GET', '/api/subscriptions', 500);
        $this->metrics->method('recordHttpRequestDuration');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new \RuntimeException('boom'));

        $this->expectException(\RuntimeException::class);

        $this->middleware->process(
            $this->makeRequest('GET', '/api/subscriptions'),
            $handler,
        );
    }

    // ── Route normalisation ───────────────────────────────────────────────────

    #[Test]
    #[DataProvider('routeNormalisationCases')]
    public function itNormalisesRouteInStoredMetric(string $rawPath, string $expectedRoute): void
    {
        $this->metrics
            ->expects(self::once())
            ->method('recordHttpRequest')
            ->with('GET', $expectedRoute, 200);

        $this->middleware->process(
            $this->makeRequest('GET', $rawPath),
            $this->makeHandler(200),
        );
    }

    /** @return array<string, array{string, string}> */
    public static function routeNormalisationCases(): array
    {
        return [
            'subscribe'                => ['/api/subscribe',                       '/api/subscribe'],
            'subscriptions'            => ['/api/subscriptions',                   '/api/subscriptions'],
            'confirm with token'       => ['/api/confirm/abc123',                  '/api/confirm/{token}'],
            'confirm with long token'  => ['/api/confirm/' . str_repeat('a', 64),  '/api/confirm/{token}'],
            'unsubscribe with token'   => ['/api/unsubscribe/xyz789',              '/api/unsubscribe/{token}'],
            'metrics path'             => ['/metrics',                             '/metrics'],
            'unknown path'             => ['/unknown/path',                        'other'],
            'root path'                => ['/',                                    'other'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->metrics    = $this->createMock(MetricsCollectorInterface::class);
        $this->middleware = new MetricsMiddleware($this->metrics);
    }

    private function makeRequest(string $method, string $path): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    private function makeHandler(int $status = 200): RequestHandlerInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }
}
