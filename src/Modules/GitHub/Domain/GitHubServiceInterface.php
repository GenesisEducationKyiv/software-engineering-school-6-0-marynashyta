<?php

declare(strict_types=1);

namespace App\Modules\GitHub\Domain;

use App\Modules\GitHub\Domain\Exception\InvalidRepositoryFormatException;
use App\Modules\GitHub\Domain\Exception\RateLimitException;
use App\Modules\GitHub\Domain\Exception\RepositoryNotFoundException;

interface GitHubServiceInterface
{
    /**
     * @throws InvalidRepositoryFormatException if format is invalid
     * @throws RepositoryNotFoundException      if repo does not exist on GitHub
     * @throws RateLimitException               if GitHub rate limit is hit
     */
    public function validateRepository(string $repo): void;

    /**
     * @throws RateLimitException
     */
    public function getLatestRelease(string $repo): ?string;
}
