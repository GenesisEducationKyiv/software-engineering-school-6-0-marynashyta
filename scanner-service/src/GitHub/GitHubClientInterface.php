<?php

declare(strict_types=1);

namespace ScannerService\GitHub;

use ScannerService\GitHub\Exception\RateLimitException;

interface GitHubClientInterface
{
    /** @throws RateLimitException */
    public function getLatestRelease(string $repo): ?string;
}
