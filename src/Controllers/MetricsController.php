<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Metrics\MetricsRendererInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MetricsController
{
    public function __construct(private readonly MetricsRendererInterface $renderer)
    {
    }

    public function metrics(Request $req, Response $res): Response
    {
        $res->getBody()->write($this->renderer->render());
        return $res->withHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
