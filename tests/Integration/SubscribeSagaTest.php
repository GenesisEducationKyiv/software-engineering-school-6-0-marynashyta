<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

final class SubscribeSagaTest extends AbstractApiTestCase
{
    private const KNOWN_REPO = 'octocat/Hello-World';

    #[Test]
    public function itCreatesSagaRowWithCompletedState(): void
    {
        $email = $this->uniqueEmail('saga-happy');

        $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode(['email' => $email, 'repo' => self::KNOWN_REPO]),
        ]);

        $saga = $this->findSagaByEmail($email);

        $this->assertNotNull($saga, 'Expected a subscription_sagas row to be created');
        $this->assertSame('completed', $saga['state']);
        $this->assertSame($email, $saga['email']);
        $this->assertSame(self::KNOWN_REPO, $saga['repo']);
        $this->assertNotEmpty($saga['confirm_token']);
        $this->assertNotEmpty($saga['unsubscribe_token']);
        $this->assertNull($saga['compensation_reason']);
    }

    #[Test]
    public function itLeavesNoSagaRowWhenValidationFailsBeforeOrchestrator(): void
    {
        $response = $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode(['email' => 'not-an-email', 'repo' => self::KNOWN_REPO]),
        ]);

        $this->assertSame(400, $response->getStatusCode());

        $saga = $this->findSagaByEmail('not-an-email');
        $this->assertNull($saga, 'No saga row should be created when validation fails before the orchestrator runs');
    }

    #[Test]
    public function itCreatesSagaRowWithTokensMatchingSubscription(): void
    {
        $email = $this->uniqueEmail('saga-tokens');

        $this->http->post('/api/subscribe', [
            'headers' => array_merge(['Content-Type' => 'application/json'], $this->authHeader()),
            'body'    => json_encode(['email' => $email, 'repo' => self::KNOWN_REPO]),
        ]);

        $saga         = $this->findSagaByEmail($email);
        $subscription = $this->findSubscriptionByEmail($email);

        $this->assertNotNull($saga);
        $this->assertNotNull($subscription);

        $this->assertSame(
            $saga['confirm_token'],
            $subscription['confirm_token'],
            'Saga and subscription must share the same confirm token',
        );
        $this->assertSame(
            $saga['unsubscribe_token'],
            $subscription['unsubscribe_token'],
            'Saga and subscription must share the same unsubscribe token',
        );
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare(
            "DELETE FROM subscription_sagas WHERE email LIKE ?"
        )->execute(["%+{$this->testRunId()}%"]);

        parent::tearDown();
    }

    /** @return array<string, mixed>|null */
    private function findSagaByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, repo, state, confirm_token, unsubscribe_token, compensation_reason
             FROM subscription_sagas WHERE email = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$email]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function findSubscriptionByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, repo, confirm_token, unsubscribe_token
             FROM subscriptions WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    private function testRunId(): string
    {
        preg_match('/\+([a-f0-9]+)@/', $this->uniqueEmail(), $m);
        return $m[1] ?? '';
    }
}
