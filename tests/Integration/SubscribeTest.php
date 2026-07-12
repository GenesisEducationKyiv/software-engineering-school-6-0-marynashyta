<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for POST /api/subscribe.
 *
 * Tests that call GitHub API (happy path, 409, 404-repo) require the running
 * server to have a valid GITHUB_TOKEN configured. Use octocat/Hello-World as
 * the stable reference repository.
 */
final class SubscribeTest extends AbstractApiTestCase
{
    private const KNOWN_REPO   = 'octocat/Hello-World';
    private const MISSING_REPO = 'this-user-xyz-404/this-repo-xyz-404';

    // ── Happy path ────────────────────────────────────────────────────────────

    #[Test]
    public function itReturns201WithValidInput(): void
    {
        $email = $this->uniqueEmail('subscribe-201');

        $response = $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode(['email' => $email, 'repo' => self::KNOWN_REPO]),
        ]);

        $body = $this->assertJsonResponse($response, 201);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Missing fields ────────────────────────────────────────────────────────

    #[Test]
    public function itReturns400WhenEmailIsMissing(): void
    {
        $response = $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode(['repo' => self::KNOWN_REPO]),
        ]);

        $body = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('message', $body);
    }

    #[Test]
    public function itReturns400WhenRepoIsMissing(): void
    {
        $response = $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode(['email' => $this->uniqueEmail('sub-no-repo')]),
        ]);

        $body = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('message', $body);
    }

    #[Test]
    public function itReturns400WhenBodyIsEmpty(): void
    {
        $response = $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => '{}',
        ]);

        $body = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Validation errors ─────────────────────────────────────────────────────

    #[Test]
    public function itReturns400WithInvalidEmailFormat(): void
    {
        $response = $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode(['email' => 'not-an-email', 'repo' => self::KNOWN_REPO]),
        ]);

        $body = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('message', $body);
    }

    #[Test]
    public function itReturns400WithInvalidRepoFormat(): void
    {
        $response = $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode(['email' => $this->uniqueEmail('sub-bad-repo'), 'repo' => 'no-slash-here']),
        ]);

        $body = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('message', $body);
    }

    // ── GitHub-level errors ───────────────────────────────────────────────────

    #[Test]
    public function itReturns404WhenRepoDoesNotExistOnGitHub(): void
    {
        $response = $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode([
                'email' => $this->uniqueEmail('sub-404-repo'),
                'repo'  => self::MISSING_REPO,
            ]),
        ]);

        $body = $this->assertJsonResponse($response, 404);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Duplicate subscription ────────────────────────────────────────────────

    #[Test]
    public function itReturns409WhenAlreadySubscribed(): void
    {
        $email = $this->uniqueEmail('sub-409');

        $this->insertSubscription($email, self::KNOWN_REPO);

        $response = $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode(['email' => $email, 'repo' => self::KNOWN_REPO]),
        ]);

        $body = $this->assertJsonResponse($response, 409);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Authentication ────────────────────────────────────────────────────────

    #[Test]
    public function itReturns401WithoutApiKey(): void
    {
        $this->requireApiKey();

        $response = $this->http->post('/api/subscribe', [
            'headers' => ['Content-Type' => 'application/json', 'X-API-Key' => 'INVALID'],
            'body'    => json_encode([
                'email' => $this->uniqueEmail('sub-401'),
                'repo'  => self::KNOWN_REPO,
            ]),
        ]);

        $this->assertJsonResponse($response, 401);
    }
}
