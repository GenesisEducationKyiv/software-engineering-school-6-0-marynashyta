<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\GitHub\Domain\Event\GitHubApiCallRecorded;
use App\Modules\Observability\Domain\MetricsCollectorInterface;
use App\Modules\Observability\Infrastructure\Listener\GitHubApiCallMetricsListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GitHubApiCallMetricsListenerTest extends TestCase
{
    #[Test]
    public function invokeRecordsGithubApiCallMetric(): void
    {
        /** @var MetricsCollectorInterface&MockObject $metrics */
        $metrics  = $this->createMock(MetricsCollectorInterface::class);
        $listener = new GitHubApiCallMetricsListener($metrics);

        $metrics->expects($this->once())
            ->method('recordGithubApiCall')
            ->with('validate_repo', true);

        $listener(new GitHubApiCallRecorded('validate_repo', true));
    }
}
