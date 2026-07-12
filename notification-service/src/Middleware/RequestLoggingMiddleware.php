<?php

declare(strict_types=1);

namespace NotificationService\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class RequestLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $traceId = $request->getHeaderLine('X-Trace-Id') ?: bin2hex(random_bytes(8));
        $start   = microtime(true);

        $this->logger->info('request received', [
            'trace_id' => $traceId,
            'method'   => $request->getMethod(),
            'path'     => (string) $request->getUri()->getPath(),
        ]);

        $response = $handler->handle($request);

        $this->logger->info('request completed', [
            'trace_id'    => $traceId,
            'method'      => $request->getMethod(),
            'path'        => (string) $request->getUri()->getPath(),
            'status'      => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return $response->withHeader('X-Trace-Id', $traceId);
    }
}
