<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Infrastructure\Acl;

use App\Modules\GitHub\Domain\GitHubServiceInterface;
use App\Modules\Subscription\Application\Acl\RepositoryGatewayInterface;

final class GitHubRepositoryGateway implements RepositoryGatewayInterface
{
    public function __construct(private readonly GitHubServiceInterface $github)
    {
    }

    public function assertRepositoryExists(string $repo): void
    {
        $this->github->validateRepository($repo);
    }

    public function findLatestReleaseTag(string $repo): ?string
    {
        return $this->github->getLatestRelease($repo);
    }
}
