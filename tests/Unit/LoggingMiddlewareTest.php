<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\LoggingMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class LoggingMiddlewareTest extends TestCase
{
    #[Test]
    public function itLogsMethodPathAndStatus(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('log')
            ->with('info', 'http.request', self::callback(function (array $ctx): bool {
                return $ctx['method'] === 'POST'
                    && $ctx['path'] === '/api/subscribe'
                    && $ctx['status'] === 201
                    && isset($ctx['duration_ms']);
            }));

        (new LoggingMiddleware($logger))->process(
            $this->makeRequest('POST', '/api/subscribe'),
            $this->makeHandler(201),
        );
    }

    #[Test]
    public function itReturnsTheHandlerResponse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('log');

        $result = (new LoggingMiddleware($logger))->process(
            $this->makeRequest('GET', '/metrics'),
            $this->makeHandler(200),
        );

        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function itRecordsDurationMs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('log')
            ->with('info', 'http.request', self::callback(
                fn(array $ctx): bool => is_int($ctx['duration_ms']) && $ctx['duration_ms'] >= 0
            ));

        (new LoggingMiddleware($logger))->process(
            $this->makeRequest('GET', '/api/subscriptions'),
            $this->makeHandler(200),
        );
    }

    #[Test]
    public function itLogsStatus500AndRethrowsWhenHandlerThrows(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('log')
            ->with('error', 'http.request', self::callback(fn(array $ctx): bool => $ctx['status'] === 500));

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new \RuntimeException('boom'));

        $this->expectException(\RuntimeException::class);

        (new LoggingMiddleware($logger))->process(
            $this->makeRequest('GET', '/api/subscriptions'),
            $handler,
        );
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

    private function makeHandler(int $status): RequestHandlerInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }
}
