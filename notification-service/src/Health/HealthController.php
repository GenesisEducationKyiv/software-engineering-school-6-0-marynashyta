<?php

declare(strict_types=1);

namespace NotificationService\Health;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HealthController
{
    public function __construct(
        private readonly string $smtpHost,
        private readonly int $smtpPort,
    ) {
    }

    public function live(Request $req, Response $res): Response
    {
        $res->getBody()->write((string) json_encode(['status' => 'ok']));
        return $res->withHeader('Content-Type', 'application/json');
    }

    public function ready(Request $req, Response $res): Response
    {
        $checks = [];
        $code   = 200;

        $fp = @fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 3.0);
        if ($fp !== false) {
            fclose($fp);
            $checks['smtp'] = 'ok';
        } else {
            $checks['smtp'] = "error: {$errstr}";
            $code           = 503;
        }

        $res->getBody()->write((string) json_encode([
            'status' => $code === 200 ? 'ready' : 'not ready',
            'checks' => $checks,
        ]));

        return $res->withHeader('Content-Type', 'application/json')->withStatus($code);
    }
}
