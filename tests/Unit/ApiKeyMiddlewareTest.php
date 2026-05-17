<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\ApiKeyMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ApiKeyMiddlewareTest extends TestCase
{
    /**
     * Build a request stub with configurable method, path, and X-API-Key header.
     */
    private function makeRequest(
        string $method = 'GET',
        string $path = '/api/subscribe',
        string $apiKey = '',
    ): ServerRequestInterface {
        /** @var UriInterface&MockObject $uri */
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        /** @var ServerRequestInterface&MockObject $request */
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->willReturn($apiKey);

        return $request;
    }

    // ── Bypass conditions ─────────────────────────────────────────────────────

    #[Test]
    public function itBypassesAuthWhenApiKeyIsEmpty(): void
    {
        $middleware = new ApiKeyMiddleware('');

        /** @var ResponseInterface&MockObject $okResponse */
        $okResponse = $this->createMock(ResponseInterface::class);
        $okResponse->method('getStatusCode')->willReturn(200);

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($okResponse);

        $middleware->process($this->makeRequest(apiKey: 'anything'), $handler);
    }

    #[Test]
    public function itBypassesAuthForOptionsMethod(): void
    {
        $middleware = new ApiKeyMiddleware('secret');

        /** @var ResponseInterface&MockObject $okResponse */
        $okResponse = $this->createMock(ResponseInterface::class);
        $okResponse->method('getStatusCode')->willReturn(200);

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($okResponse);

        $middleware->process($this->makeRequest(method: 'OPTIONS', apiKey: ''), $handler);
    }

    #[Test]
    public function itBypassesAuthForMetricsPath(): void
    {
        $middleware = new ApiKeyMiddleware('secret');

        /** @var ResponseInterface&MockObject $okResponse */
        $okResponse = $this->createMock(ResponseInterface::class);
        $okResponse->method('getStatusCode')->willReturn(200);

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($okResponse);

        $middleware->process($this->makeRequest(path: '/metrics', apiKey: ''), $handler);
    }

    // ── Valid key ─────────────────────────────────────────────────────────────

    #[Test]
    public function itForwardsRequestWithCorrectApiKey(): void
    {
        $middleware = new ApiKeyMiddleware('secret');

        /** @var ResponseInterface&MockObject $okResponse */
        $okResponse = $this->createMock(ResponseInterface::class);
        $okResponse->method('getStatusCode')->willReturn(200);

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($okResponse);

        $result = $middleware->process($this->makeRequest(apiKey: 'secret'), $handler);

        self::assertSame(200, $result->getStatusCode());
    }

    // ── Unauthorised ──────────────────────────────────────────────────────────

    #[Test]
    public function itReturns401WhenApiKeyHeaderIsMissing(): void
    {
        $middleware = new ApiKeyMiddleware('secret');

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $result = $middleware->process($this->makeRequest(apiKey: ''), $handler);

        self::assertSame(401, $result->getStatusCode());
    }

    #[Test]
    public function itReturns401WhenApiKeyIsIncorrect(): void
    {
        $middleware = new ApiKeyMiddleware('secret');

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $result = $middleware->process($this->makeRequest(apiKey: 'wrong-key'), $handler);

        self::assertSame(401, $result->getStatusCode());
    }

    #[Test]
    public function itReturns401WithJsonContentType(): void
    {
        $middleware = new ApiKeyMiddleware('secret');

        /** @var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->createMock(ResponseInterface::class));

        $result = $middleware->process($this->makeRequest(apiKey: ''), $handler);

        self::assertStringContainsString('application/json', $result->getHeaderLine('Content-Type'));
    }
}
