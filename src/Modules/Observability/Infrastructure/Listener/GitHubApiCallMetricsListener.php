<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Listener;

use App\Modules\GitHub\Domain\Event\GitHubApiCallRecorded;
use App\Modules\Observability\Domain\MetricsCollectorInterface;

final class GitHubApiCallMetricsListener
{
    public function __construct(private readonly MetricsCollectorInterface $metrics)
    {
    }

    public function __invoke(GitHubApiCallRecorded $event): void
    {
        $this->metrics->recordGithubApiCall($event->endpoint, $event->cacheHit);
    }
}
