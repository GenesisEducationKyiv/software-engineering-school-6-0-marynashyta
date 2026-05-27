<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTO\SubscribeRequest;
use App\Exceptions\HttpExceptionInterface;
use App\Infrastructure\Json;
use App\Services\SubscriptionServiceInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SubscriptionController
{
    public function __construct(private readonly SubscriptionServiceInterface $service)
    {
    }

    /**
     * POST /api/subscribe
     * Body: {"email": "...", "repo": "owner/repo"}
     */
    public function subscribe(Request $req, Response $res): Response
    {
        $body    = $req->getParsedBody();
        $body    = is_array($body) ? $body : [];
        $request = new SubscribeRequest(
            email: trim((string) ($body['email'] ?? '')),
            repo:  trim((string) ($body['repo'] ?? '')),
        );

        if ($request->email === '' || $request->repo === '') {
            return $this->json($res, ['message' => 'Fields "email" and "repo" are required'], 400);
        }

        try {
            $this->service->subscribe($request);
            return $this->json($res, ['message' => 'Subscription created. Please check your email to confirm.'], 201);
        } catch (HttpExceptionInterface $e) {
            return $this->json($res, ['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * GET /api/confirm/{token}
     *
     * @param array<string, string> $args
     */
    public function confirm(Request $req, Response $res, array $args): Response
    {
        $token = trim($args['token'] ?? '');

        if (!$this->isValidToken($token)) {
            return $this->json($res, ['message' => 'Invalid token format'], 400);
        }

        try {
            $this->service->confirm($token);
            return $this->json($res, ['message' => 'Subscription confirmed successfully'], 200);
        } catch (HttpExceptionInterface $e) {
            return $this->json($res, ['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * GET /api/unsubscribe/{token}
     *
     * @param array<string, string> $args
     */
    public function unsubscribe(Request $req, Response $res, array $args): Response
    {
        $token = trim($args['token'] ?? '');

        if (!$this->isValidToken($token)) {
            return $this->json($res, ['message' => 'Invalid token format'], 400);
        }

        try {
            $this->service->unsubscribe($token);
            return $this->json($res, ['message' => 'Unsubscribed successfully'], 200);
        } catch (HttpExceptionInterface $e) {
            return $this->json($res, ['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * GET /api/subscriptions?email=...
     */
    public function getSubscriptions(Request $req, Response $res): Response
    {
        $params = $req->getQueryParams();
        $email  = trim((string) ($params['email'] ?? ''));

        if ($email === '') {
            return $this->json($res, ['message' => 'Query parameter "email" is required'], 400);
        }

        try {
            $subscriptions = $this->service->getSubscriptions($email);
            return $this->json($res, $subscriptions, 200);
        } catch (HttpExceptionInterface $e) {
            return $this->json($res, ['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * A 64-character lowercase hex string — the format produced by bin2hex(random_bytes(32)).
     */
    private function isValidToken(string $token): bool
    {
        return (bool) preg_match('/^[0-9a-f]{64}$/', $token);
    }

    /**
     * Encode $data as JSON and write it into the response.
     * JSON_THROW_ON_ERROR ensures json_encode() never silently returns false.
     */
    private function json(Response $res, mixed $data, int $status): Response
    {
        $res->getBody()->write(Json::encode($data));

        return $res
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
