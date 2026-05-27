<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for GET /api/confirm/{token}.
 *
 * The happy-path test (unconfirmed → confirmed) causes the server to call
 * GitHub->getLatestRelease() so the server needs a reachable GitHub API.
 * The idempotent test (already confirmed) short-circuits before that call.
 */
final class ConfirmTest extends AbstractApiTestCase
{
    private const KNOWN_REPO    = 'octocat/Hello-World';
    private const INVALID_TOKEN = 'not-a-valid-token';

    // ── Happy path ────────────────────────────────────────────────────────────

    #[Test]
    public function itReturns200WhenConfirmingUnconfirmedSubscription(): void
    {
        $row = $this->insertSubscription(
            $this->uniqueEmail('confirm-ok'),
            self::KNOWN_REPO,
            confirmed: false,
        );

        $response = $this->http->get("/api/confirm/{$row['confirm_token']}", [
            'headers' => $this->authHeader(),
        ]);

        $body = $this->assertJsonResponse($response, 200);
        $this->assertArrayHasKey('message', $body);
    }

    #[Test]
    public function itReturns200IdempotentlyWhenAlreadyConfirmed(): void
    {
        // Seeding as confirmed=true means the service returns early before
        // calling GitHub, making this test fully self-contained.
        $row = $this->insertSubscription(
            $this->uniqueEmail('confirm-idem'),
            self::KNOWN_REPO,
            confirmed: true,
        );

        $response = $this->http->get("/api/confirm/{$row['confirm_token']}", [
            'headers' => $this->authHeader(),
        ]);

        $body = $this->assertJsonResponse($response, 200);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Validation errors ─────────────────────────────────────────────────────

    #[Test]
    public function itReturns400WithInvalidTokenFormat(): void
    {
        $response = $this->http->get('/api/confirm/' . self::INVALID_TOKEN, [
            'headers' => $this->authHeader(),
        ]);

        $body = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Not found ─────────────────────────────────────────────────────────────

    #[Test]
    public function itReturns404WithNonexistentToken(): void
    {
        $fakeToken = str_repeat('a', 64);

        $response = $this->http->get("/api/confirm/{$fakeToken}", [
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

        $fakeToken = str_repeat('b', 64);

        $response = $this->http->get("/api/confirm/{$fakeToken}", [
            'headers' => ['X-API-Key' => 'INVALID'],
        ]);

        $this->assertJsonResponse($response, 401);
    }
}
