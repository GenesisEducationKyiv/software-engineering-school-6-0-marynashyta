<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\GitHub\Domain\GitHubServiceInterface;
use App\Modules\Subscription\Infrastructure\Acl\GitHubRepositoryGateway;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GitHubRepositoryGatewayTest extends TestCase
{
    private GitHubServiceInterface&MockObject $github;
    private GitHubRepositoryGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->github  = $this->createMock(GitHubServiceInterface::class);
        $this->gateway = new GitHubRepositoryGateway($this->github);
    }

    #[Test]
    public function assertRepositoryExistsDelegatesToGitHubService(): void
    {
        $this->github->expects($this->once())
            ->method('validateRepository')
            ->with('owner/repo');

        $this->gateway->assertRepositoryExists('owner/repo');
    }

    #[Test]
    public function findLatestReleaseTagDelegatesToGitHubService(): void
    {
        $this->github->expects($this->once())
            ->method('getLatestRelease')
            ->with('owner/repo')
            ->willReturn('v1.0.0');

        $this->assertSame('v1.0.0', $this->gateway->findLatestReleaseTag('owner/repo'));
    }
}
