<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start  = hrtime(true);
        $status = null;
        try {
            $response = $handler->handle($request);
            $status   = $response->getStatusCode();
            return $response;
        } catch (\Throwable $e) {
            $status = 500;
            throw $e;
        } finally {
            $ctx = [
                'method'      => $request->getMethod(),
                'path'        => $request->getUri()->getPath(),
                'status'      => $status,
                'duration_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
            ];
            $level = match (true) {
                $status >= 500 => 'error',
                $status >= 400 => 'warning',
                default        => 'info',
            };
            $this->logger->log($level, 'http.request', $ctx);
        }
    }
}
