<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Metrics\MetricsCollectorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MetricsMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly MetricsCollectorInterface $metrics)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $this->metrics->recordHttpRequest(
            $request->getMethod(),
            $this->normaliseRoute($request->getUri()->getPath()),
            $response->getStatusCode()
        );

        return $response;
    }

    private function normaliseRoute(string $path): string
    {
        return match (true) {
            $path === '/api/subscribe' => '/api/subscribe',
            $path === '/api/subscriptions' => '/api/subscriptions',
            str_starts_with($path, '/api/confirm/') => '/api/confirm/{token}',
            str_starts_with($path, '/api/unsubscribe/') => '/api/unsubscribe/{token}',
            $path === '/metrics' => '/metrics',
            default => 'other',
        };
    }
}
