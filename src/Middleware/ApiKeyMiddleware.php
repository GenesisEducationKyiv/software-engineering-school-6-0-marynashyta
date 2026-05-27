<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class ApiKeyMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $apiKey)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->apiKey === '' || $request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        if ($path === '/metrics' || str_starts_with($path, '/metrics?')) {
            return $handler->handle($request);
        }

        $providedKey = $request->getHeaderLine('X-API-Key');

        if ($providedKey === '' || !hash_equals($this->apiKey, $providedKey)) {
            $res = new Response();
            $res->getBody()->write(Json::encode(['message' => 'Unauthorized']));
            return $res
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }

        return $handler->handle($request);
    }
}
