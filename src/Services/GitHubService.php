<?php

declare(strict_types=1);

namespace App\Services;

use App\Cache\CacheInterface;
use App\DTO\GitHubRelease;
use App\Exceptions\InvalidRepositoryFormatException;
use App\Exceptions\RateLimitException;
use App\Exceptions\RepositoryNotFoundException;
use App\Infrastructure\Json;
use App\Metrics\MetricsCollectorInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

final class GitHubService implements GitHubServiceInterface
{
    private const API_BASE       = 'https://api.github.com';
    private const CACHE_TTL      = 600;
    private const CACHE_NULL_TAG = '__null__';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly ?string $token = null,
        private readonly ?CacheInterface $cache = null,
        private readonly ?MetricsCollectorInterface $metrics = null,
    ) {
    }

    /**
     * @throws InvalidRepositoryFormatException if format is invalid
     * @throws RepositoryNotFoundException      if 404 from GitHub
     * @throws RateLimitException               if 429 from GitHub
     */
    public function validateRepository(string $repo): void
    {
        if (!preg_match('/^[a-zA-Z0-9_.\-]+\/[a-zA-Z0-9_.\-]+$/', $repo)) {
            throw new InvalidRepositoryFormatException($repo);
        }

        $cacheKey = "github:validate:{$repo}";

        if ($this->cache?->get($cacheKey) !== null) {
            $this->metrics?->recordGithubApiCall('validate_repo', true);
            return;
        }

        try {
            $this->makeRequest(self::API_BASE . "/repos/{$repo}");
            $this->cache?->set($cacheKey, '1', self::CACHE_TTL);
            $this->metrics?->recordGithubApiCall('validate_repo', false);
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();

            if ($statusCode === 404) {
                throw new RepositoryNotFoundException($repo, 0, $e);
            }

            if ($statusCode === 429) {
                $retryAfter = (int) ($e->getResponse()->getHeaderLine('Retry-After') ?: 60);
                throw new RateLimitException($retryAfter, 0, $e);
            }

            throw $e;
        }
    }

    /**
     * @throws RateLimitException if 429 from GitHub
     */
    public function getLatestRelease(string $repo): ?string
    {
        $cacheKey = "github:release:{$repo}";
        $cached   = $this->cache?->get($cacheKey);

        if ($cached !== null) {
            $this->metrics?->recordGithubApiCall('latest_release', true);
            return $cached === self::CACHE_NULL_TAG ? null : $cached;
        }

        try {
            $data    = $this->makeRequest(self::API_BASE . "/repos/{$repo}/releases/latest");
            $release = GitHubRelease::fromApiResponse($data);
            $latestTag = $release->tagName !== '' ? $release->tagName : null;

            $this->cache?->set($cacheKey, $latestTag ?? self::CACHE_NULL_TAG, self::CACHE_TTL);
            $this->metrics?->recordGithubApiCall('latest_release', false);

            return $latestTag;
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();

            if ($statusCode === 404) {
                $this->cache?->set($cacheKey, self::CACHE_NULL_TAG, self::CACHE_TTL);
                $this->metrics?->recordGithubApiCall('latest_release', false);
                return null;
            }

            if ($statusCode === 429) {
                $retryAfter = (int) ($e->getResponse()->getHeaderLine('Retry-After') ?: 60);
                throw new RateLimitException($retryAfter, 0, $e);
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     * @throws ClientException   on 4xx responses
     * @throws GuzzleException   on network errors
     */
    private function makeRequest(string $url): array
    {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'ReleaseNotificationAPI/1.0',
        ];

        if ($this->token !== null && $this->token !== '') {
            $headers['Authorization'] = "Bearer {$this->token}";
        }

        $response = $this->client->request('GET', $url, ['headers' => $headers]);

        return Json::decode((string) $response->getBody());
    }
}
