<?php

declare(strict_types=1);

namespace Tests\Integration;

use GuzzleHttp\Client;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractApiTestCase extends TestCase
{
    protected Client $http;
    protected PDO $pdo;

    private string $testRunId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testRunId = bin2hex(random_bytes(8));

        $this->http = new Client([
            'base_uri'    => $this->envString('API_BASE_URL', 'http://localhost:8080'),
            'http_errors' => false,
            'timeout'     => 15.0,
        ]);

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->envString('DB_HOST', 'localhost'),
            $this->envString('DB_PORT', '3306'),
            $this->envString('DB_NAME', 'release_notifications'),
        );

        $this->pdo = new PDO(
            $dsn,
            $this->envString('DB_USER', 'app'),
            $this->envString('DB_PASS', 'secret'),
            [
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );
    }

    protected function tearDown(): void
    {
        // Clean up every row whose email contains the per-test unique ID,
        // covering both rows seeded via PDO and rows created through the API.
        $this->pdo->prepare(
            "DELETE FROM subscriptions WHERE email LIKE ?"
        )->execute(["%+{$this->testRunId}%"]);

        parent::tearDown();
    }

    /** Returns a unique email address for this test run. */
    protected function uniqueEmail(string $prefix = 'user'): string
    {
        return "{$prefix}+{$this->testRunId}@example.com";
    }

    /**
     * Assert status code and Content-Type, then decode the JSON body.
     *
     * @return array<string, mixed>
     */
    protected function assertJsonResponse(ResponseInterface $response, int $expectedStatus): array
    {
        $this->assertSame(
            $expectedStatus,
            $response->getStatusCode(),
            'Unexpected status. Body: ' . (string) $response->getBody(),
        );
        $this->assertStringContainsString(
            'application/json',
            $response->getHeaderLine('Content-Type'),
        );

        $body = (string) $response->getBody();
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Seed a subscription row directly via PDO and return its attributes.
     *
     * @return array{id: int, email: string, repo: string, confirmed: bool, confirm_token: string, unsubscribe_token: string}
     */
    protected function insertSubscription(
        string $email,
        string $repo,
        bool $confirmed = false,
        ?string $confirmToken = null,
        ?string $unsubscribeToken = null,
    ): array {
        $confirmToken     = $confirmToken     ?? bin2hex(random_bytes(32));
        $unsubscribeToken = $unsubscribeToken ?? bin2hex(random_bytes(32));

        $this->pdo->prepare(
            'INSERT INTO subscriptions (email, repo, confirmed, confirm_token, unsubscribe_token)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$email, $repo, $confirmed ? 1 : 0, $confirmToken, $unsubscribeToken]);

        return [
            'id'                => (int) $this->pdo->lastInsertId(),
            'email'             => $email,
            'repo'              => $repo,
            'confirmed'         => $confirmed,
            'confirm_token'     => $confirmToken,
            'unsubscribe_token' => $unsubscribeToken,
        ];
    }

    /**
     * Returns the X-API-Key header array when API_KEY is configured,
     * or an empty array when the middleware is disabled (API_KEY = '').
     *
     * @return array<string, string>
     */
    protected function authHeader(): array
    {
        $key = $this->envString('API_KEY');
        return $key !== '' ? ['X-API-Key' => $key] : [];
    }

    /**
     * Skip the current test when API_KEY is not set, because the middleware
     * is disabled and 401 responses cannot be triggered.
     */
    protected function requireApiKey(): void
    {
        if ($this->envString('API_KEY') === '') {
            $this->markTestSkipped('API_KEY is not configured — auth middleware is disabled.');
        }
    }

    /** Read a string value from $_ENV, falling back to $default when absent or non-string. */
    private function envString(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    /**
     * Assert status and Content-Type for a JSON list (array) response, then
     * return the decoded list. Use this for endpoints that return a JSON array
     * at the top level (e.g. GET /api/subscriptions).
     *
     * @return list<mixed>
     */
    protected function assertJsonListResponse(ResponseInterface $response, int $expectedStatus): array
    {
        $this->assertSame(
            $expectedStatus,
            $response->getStatusCode(),
            'Unexpected status. Body: ' . (string) $response->getBody(),
        );
        $this->assertStringContainsString(
            'application/json',
            $response->getHeaderLine('Content-Type'),
        );

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);

        return array_values($data);
    }
}
