<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\GitHub\Domain\Exception\InvalidRepositoryFormatException;
use App\Modules\GitHub\Domain\Exception\RateLimitException;
use App\Modules\GitHub\Domain\Exception\RepositoryNotFoundException;
use App\Modules\GitHub\Infrastructure\GitHubService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GitHubServiceTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private GitHubService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->service = new GitHubService($this->httpClient, 'test-token');
    }

    #[Test]
    public function validateRepositoryWithValidRepoSucceeds(): void
    {
        $response = new Response(200, [], (string)json_encode(['id' => 1, 'full_name' => 'owner/repo']));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.github.com/repos/owner/repo', $this->anything())
            ->willReturn($response);

        $this->service->validateRepository('owner/repo');
        $this->addToAssertionCount(1);
    }

    #[Test]
    #[DataProvider('invalidRepoFormatProvider')]
    public function validateRepositoryWithInvalidFormatThrowsException(string $repo): void
    {
        $this->expectException(InvalidRepositoryFormatException::class);

        $this->httpClient->expects($this->never())->method('request');

        $this->service->validateRepository($repo);
    }

    #[Test]
    public function validateRepositoryWith404ThrowsRepositoryNotFoundException(): void
    {
        $this->expectException(RepositoryNotFoundException::class);

        $request = new Request('GET', 'https://api.github.com/repos/owner/nonexistent');
        $response = new Response(404, [], '{"message":"Not Found"}');
        $exception = new ClientException('Not Found', $request, $response);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $this->service->validateRepository('owner/nonexistent');
    }

    #[Test]
    public function validateRepositoryWith429ThrowsRateLimitException(): void
    {
        $this->expectException(RateLimitException::class);

        $request = new Request('GET', 'https://api.github.com/repos/owner/repo');
        $response = new Response(429, ['Retry-After' => ['120']], '{"message":"rate limit exceeded"}');
        $exception = new ClientException('rate limit exceeded', $request, $response);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $this->service->validateRepository('owner/repo');
    }

    #[Test]
    public function validateRepositoryWith429ReadsRetryAfterHeader(): void
    {
        $request = new Request('GET', 'https://api.github.com/repos/owner/repo');
        $response = new Response(429, ['Retry-After' => ['90']], '{"message":"rate limit exceeded"}');
        $exception = new ClientException('rate limit exceeded', $request, $response);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        try {
            $this->service->validateRepository('owner/repo');
            $this->fail('Expected RateLimitException was not thrown');
        } catch (RateLimitException $e) {
            $this->assertSame(90, $e->getRetryAfter());
        }
    }

    #[Test]
    public function getLatestReleaseReturnsTagName(): void
    {
        $response = new Response(200, [], (string)json_encode([
            'id' => 1,
            'tag_name' => 'v1.2.3',
            'name' => 'Release 1.2.3',
        ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.github.com/repos/owner/repo/releases/latest', $this->anything())
            ->willReturn($response);

        $this->assertSame('v1.2.3', $this->service->getLatestRelease('owner/repo'));
    }

    #[Test]
    public function getLatestReleaseReturnsNullWhenNoReleases(): void
    {
        $request = new Request('GET', 'https://api.github.com/repos/owner/repo/releases/latest');
        $response = new Response(404, [], '{"message":"Not Found"}');
        $exception = new ClientException('Not Found', $request, $response);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $this->assertNull($this->service->getLatestRelease('owner/repo'));
    }

    #[Test]
    public function getLatestReleaseWith429ThrowsRateLimitException(): void
    {
        $this->expectException(RateLimitException::class);

        $request = new Request('GET', 'https://api.github.com/repos/owner/repo/releases/latest');
        $response = new Response(429, ['Retry-After' => ['60']], '{"message":"rate limit exceeded"}');
        $exception = new ClientException('rate limit exceeded', $request, $response);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $this->service->getLatestRelease('owner/repo');
    }

    #[Test]
    public function getLatestReleaseIncludesBearerTokenWhenConfigured(): void
    {
        $service = new GitHubService($this->httpClient, 'my-github-token');
        $response = new Response(200, [], (string)json_encode(['tag_name' => 'v2.0.0']));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return isset($options['headers']['Authorization'])
                        && $options['headers']['Authorization'] === 'Bearer my-github-token';
                })
            )
            ->willReturn($response);

        $this->assertSame('v2.0.0', $service->getLatestRelease('owner/repo'));
    }

    #[Test]
    public function getLatestReleaseOmitsAuthHeaderWhenNoTokenConfigured(): void
    {
        $service = new GitHubService($this->httpClient, null);
        $response = new Response(200, [], (string)json_encode(['tag_name' => 'v1.0.0']));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return !isset($options['headers']['Authorization']);
                })
            )
            ->willReturn($response);

        $this->assertSame('v1.0.0', $service->getLatestRelease('owner/repo'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRepoFormatProvider(): array
    {
        return [
            'empty string' => [''],
            'no slash' => ['just-a-repo'],
            'double slash' => ['owner//repo'],
            'leading slash' => ['/repo'],
            'trailing slash' => ['owner/'],
            'spaces' => ['owner /repo'],
        ];
    }
}
