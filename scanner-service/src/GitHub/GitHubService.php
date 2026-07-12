<?php

declare(strict_types=1);

namespace ScannerService\GitHub;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use ScannerService\GitHub\Exception\RateLimitException;
use ScannerService\Infrastructure\CircuitBreaker;

final class GitHubService implements GitHubClientInterface
{
    private const API_BASE = 'https://api.github.com';

    public function __construct(
        private readonly ClientInterface $http,
        private readonly ?string $token = null,
        private readonly ?CircuitBreaker $circuitBreaker = null,
    ) {
    }

    /** @throws RateLimitException */
    public function getLatestRelease(string $repo): ?string
    {
        $call = fn () => $this->fetchLatestRelease($repo);

        return $this->circuitBreaker !== null
            ? $this->circuitBreaker->call($call)
            : $call();
    }

    /** @throws RateLimitException */
    private function fetchLatestRelease(string $repo): ?string
    {
        try {
            $response = $this->http->request('GET', self::API_BASE . "/repos/{$repo}/releases/latest", [
                'headers' => $this->headers(),
            ]);

            /** @var array<string, mixed> $data */
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            return GitHubRelease::fromApiResponse($data)->tagName;
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            if ($status === 404) {
                return null;
            }

            if ($status === 429) {
                $retryAfter = (int) ($e->getResponse()->getHeaderLine('Retry-After') ?: 60);
                throw new RateLimitException($retryAfter, 0, $e);
            }

            throw $e;
        }
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'ReleaseNotificationAPI/1.0',
        ];

        if ($this->token !== null && $this->token !== '') {
            $headers['Authorization'] = "Bearer {$this->token}";
        }

        return $headers;
    }
}
