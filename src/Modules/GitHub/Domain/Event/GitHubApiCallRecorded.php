<?php

declare(strict_types=1);

namespace App\Modules\GitHub\Domain\Event;

use App\SharedKernel\Domain\DomainEvent;

final readonly class GitHubApiCallRecorded implements DomainEvent
{
    public function __construct(
        public string $endpoint,
        public bool $cacheHit,
    ) {
    }
}
