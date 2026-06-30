<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application\Acl;

use App\SharedKernel\Domain\HttpExceptionInterface;

interface RepositoryGatewayInterface
{
    /**
     * @throws HttpExceptionInterface if the repository is malformed or does not exist
     */
    public function assertRepositoryExists(string $repo): void;

    /**
     * @throws HttpExceptionInterface
     */
    public function findLatestReleaseTag(string $repo): ?string;
}
