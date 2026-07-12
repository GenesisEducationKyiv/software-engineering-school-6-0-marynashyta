<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for GET /api/unsubscribe/{token}.
 *
 * No GitHub API calls are made by this endpoint — it only looks up the token
 * and deletes the subscription row.
 */
final class UnsubscribeTest extends AbstractApiTestCase
{
    private const KNOWN_REPO    = 'octocat/Hello-World';
    private const INVALID_TOKEN = 'not-a-valid-token';

    // ── Happy path ────────────────────────────────────────────────────────────

    #[Test]
    public function itReturns200WhenTokenIsValid(): void
    {
        $row = $this->insertSubscription(
            $this->uniqueEmail('unsub-ok'),
            self::KNOWN_REPO,
        );

        $response = $this->http->get("/api/unsubscribe/{$row['unsubscribe_token']}", [
            'headers' => $this->authHeader(),
        ]);

        $body = $this->assertJsonResponse($response, 200);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Validation errors ─────────────────────────────────────────────────────

    #[Test]
    public function itReturns400WithInvalidTokenFormat(): void
    {
        $response = $this->http->get('/api/unsubscribe/' . self::INVALID_TOKEN, [
            'headers' => $this->authHeader(),
        ]);

        $body = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Not found ─────────────────────────────────────────────────────────────

    #[Test]
    public function itReturns404WithNonexistentToken(): void
    {
        $fakeToken = str_repeat('c', 64);

        $response = $this->http->get("/api/unsubscribe/{$fakeToken}", [
            'headers' => $this->authHeader(),
        ]);

        $body = $this->assertJsonResponse($response, 404);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Authentication ────────────────────────────────────────────────────────

    #[Test]
    public function itReturns401WithoutApiKey(): void
    {
        $this->requireApiKey();

        $fakeToken = str_repeat('d', 64);

        $response = $this->http->get("/api/unsubscribe/{$fakeToken}", [
            'headers' => ['X-API-Key' => 'INVALID'],
        ]);

        $this->assertJsonResponse($response, 401);
    }
}
