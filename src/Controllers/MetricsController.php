<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Metrics\MetricsCollectorInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MetricsController
{
    public function __construct(private MetricsCollectorInterface $metrics)
    {
    }

    public function metrics(Request $req, Response $res): Response
    {
        $res->getBody()->write($this->metrics->render());
        return $res->withHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
