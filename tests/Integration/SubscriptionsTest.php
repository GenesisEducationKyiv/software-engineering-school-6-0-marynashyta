<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for GET /api/subscriptions?email={email}.
 *
 * This endpoint only queries the database — no external service calls are made.
 */
final class SubscriptionsTest extends AbstractApiTestCase
{
    private const REPO_A = 'octocat/Hello-World';
    private const REPO_B = 'octocat/Spoon-Knife';

    // ── Happy path ────────────────────────────────────────────────────────────

    #[Test]
    public function itReturns200WithConfirmedSubscriptions(): void
    {
        $email = $this->uniqueEmail('subs-ok');

        $this->insertSubscription($email, self::REPO_A, confirmed: true);
        $this->insertSubscription($email, self::REPO_B, confirmed: true);

        $response = $this->http->get('/api/subscriptions', [
            'headers' => $this->authHeader(),
            'query'   => ['email' => $email],
        ]);

        $list = $this->assertJsonListResponse($response, 200);
        $this->assertCount(2, $list);

        $repos = array_column($list, 'repo');
        $this->assertContains(self::REPO_A, $repos);
        $this->assertContains(self::REPO_B, $repos);
    }

    #[Test]
    public function itReturns200WithEmptyArrayWhenNoConfirmedSubscriptions(): void
    {
        $email = $this->uniqueEmail('subs-empty');

        // Insert an unconfirmed subscription — it must not appear in the result.
        $this->insertSubscription($email, self::REPO_A, confirmed: false);

        $response = $this->http->get('/api/subscriptions', [
            'headers' => $this->authHeader(),
            'query'   => ['email' => $email],
        ]);

        $list = $this->assertJsonListResponse($response, 200);
        $this->assertCount(0, $list);
    }

    #[Test]
    public function itReturns200WithEmptyArrayForUnknownEmail(): void
    {
        $response = $this->http->get('/api/subscriptions', [
            'headers' => $this->authHeader(),
            'query'   => ['email' => $this->uniqueEmail('subs-unknown')],
        ]);

        $list = $this->assertJsonListResponse($response, 200);
        $this->assertCount(0, $list);
    }

    #[Test]
    public function itResponseItemsHaveExpectedShape(): void
    {
        $email = $this->uniqueEmail('subs-shape');
        $this->insertSubscription($email, self::REPO_A, confirmed: true);

        $response = $this->http->get('/api/subscriptions', [
            'headers' => $this->authHeader(),
            'query'   => ['email' => $email],
        ]);

        $list = $this->assertJsonListResponse($response, 200);
        $this->assertCount(1, $list);

        $item = $list[0];
        $this->assertIsArray($item);
        $this->assertArrayHasKey('email', $item);
        $this->assertArrayHasKey('repo', $item);
        $this->assertArrayHasKey('confirmed', $item);
        $this->assertArrayHasKey('last_seen_tag', $item);
        $this->assertSame($email, $item['email']);
        $this->assertSame(self::REPO_A, $item['repo']);
    }

    // ── Validation errors ─────────────────────────────────────────────────────

    #[Test]
    public function itReturns400WhenEmailParamIsMissing(): void
    {
        $response = $this->http->get('/api/subscriptions', [
            'headers' => $this->authHeader(),
        ]);

        $body = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('message', $body);
    }

    #[Test]
    public function itReturns400WithInvalidEmailFormat(): void
    {
        $response = $this->http->get('/api/subscriptions', [
            'headers' => $this->authHeader(),
            'query'   => ['email' => 'not-valid'],
        ]);

        $body = $this->assertJsonResponse($response, 400);
        $this->assertArrayHasKey('message', $body);
    }

    // ── Authentication ────────────────────────────────────────────────────────

    #[Test]
    public function itReturns401WithoutApiKey(): void
    {
        $this->requireApiKey();

        $response = $this->http->get('/api/subscriptions', [
            'headers' => ['X-API-Key' => 'INVALID'],
            'query'   => ['email' => $this->uniqueEmail('subs-401')],
        ]);

        $this->assertJsonResponse($response, 401);
    }
}
