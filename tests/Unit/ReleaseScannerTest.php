<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Subscription;
use App\Exceptions\RateLimitException;
use App\Metrics\MetricsCollectorInterface;
use App\Repository\SubscriptionScanRepositoryInterface;
use App\Scanner\LoggerInterface;
use App\Scanner\ReleaseScanner;
use App\Services\GitHubServiceInterface;
use App\Services\NotificationMailerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReleaseScannerTest extends TestCase
{
    private SubscriptionScanRepositoryInterface&MockObject $repository;
    private GitHubServiceInterface&MockObject $github;
    private NotificationMailerInterface&MockObject $mailer;
    private MetricsCollectorInterface&MockObject $metrics;
    private LoggerInterface&MockObject $logger;
    private ReleaseScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(SubscriptionScanRepositoryInterface::class);
        $this->github     = $this->createMock(GitHubServiceInterface::class);
        $this->mailer     = $this->createMock(NotificationMailerInterface::class);
        $this->metrics    = $this->createMock(MetricsCollectorInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);

        $this->scanner = new ReleaseScanner(
            $this->repository,
            $this->github,
            $this->mailer,
            $this->metrics,
            $this->logger,
        );
    }

    // ── Empty subscriptions ───────────────────────────────────────────────────

    #[Test]
    public function itLogsAndReturnsWhenNoSubscriptionsFound(): void
    {
        $this->repository->method('findAllConfirmed')->willReturn([]);

        $this->github->expects(self::never())->method('getLatestRelease');
        $this->mailer->expects(self::never())->method('sendReleaseNotification');

        $this->logger->expects(self::once())
            ->method('log')
            ->with('INFO', self::stringContains('No confirmed subscriptions'));

        $this->scanner->scan();
    }

    // ── Grouping by repo ─────────────────────────────────────────────────────

    #[Test]
    public function itCallsGitHubOncePerUniqueRepo(): void
    {
        $repo = 'owner/repo';
        $tag  = 'v1.0.0';

        $this->repository->method('findAllConfirmed')->willReturn([
            new Subscription(1, 'a@example.com', $repo, true, $tag, 'tok1'),
            new Subscription(2, 'b@example.com', $repo, true, $tag, 'tok2'),
        ]);

        $this->github->expects(self::once())
            ->method('getLatestRelease')
            ->with($repo)
            ->willReturn($tag);

        $this->mailer->expects(self::never())->method('sendReleaseNotification');

        $this->scanner->scan();
    }

    #[Test]
    public function itCallsGitHubSeparatelyForEachUniqueRepo(): void
    {
        $tag = 'v1.0.0';

        $this->repository->method('findAllConfirmed')->willReturn([
            new Subscription(1, 'a@example.com', 'owner/repo1', true, $tag, 'tok1'),
            new Subscription(2, 'b@example.com', 'owner/repo2', true, $tag, 'tok2'),
        ]);

        $this->github->expects(self::exactly(2))
            ->method('getLatestRelease')
            ->willReturn($tag);

        $this->scanner->scan();
    }

    // ── Tag comparison ────────────────────────────────────────────────────────

    #[Test]
    public function itSkipsNotificationWhenTagMatchesLastSeen(): void
    {
        $repo = 'owner/repo';
        $tag  = 'v2.0.0';

        $this->repository->method('findAllConfirmed')->willReturn([
            new Subscription(1, 'user@example.com', $repo, true, $tag, 'tok'),
        ]);

        $this->github->method('getLatestRelease')->willReturn($tag);

        $this->mailer->expects(self::never())->method('sendReleaseNotification');
        $this->repository->expects(self::never())->method('updateLastSeenTag');
        $this->metrics->expects(self::never())->method('recordNotificationSent');

        $this->scanner->scan();
    }

    #[Test]
    public function itSendsNotificationWhenTagDiffers(): void
    {
        $repo      = 'owner/repo';
        $latestTag = 'v2.0.0';
        $oldTag    = 'v1.0.0';

        $sub = new Subscription(5, 'user@example.com', $repo, true, $oldTag, 'unsub-tok');

        $this->repository->method('findAllConfirmed')->willReturn([$sub]);
        $this->github->method('getLatestRelease')->willReturn($latestTag);

        $this->mailer->expects(self::once())
            ->method('sendReleaseNotification')
            ->with('user@example.com', $repo, $latestTag, 'unsub-tok');

        $this->repository->expects(self::once())
            ->method('updateLastSeenTag')
            ->with(5, $latestTag);

        $this->metrics->expects(self::once())->method('recordNotificationSent');

        $this->scanner->scan();
    }

    #[Test]
    public function itSendsNotificationWhenLastSeenTagIsNull(): void
    {
        $repo = 'owner/repo';
        $tag  = 'v1.0.0';

        $this->repository->method('findAllConfirmed')->willReturn([
            new Subscription(3, 'user@example.com', $repo, true, null, 'tok'),
        ]);

        $this->github->method('getLatestRelease')->willReturn($tag);

        $this->mailer->expects(self::once())->method('sendReleaseNotification');
        $this->repository->expects(self::once())->method('updateLastSeenTag');

        $this->scanner->scan();
    }

    // ── No releases ───────────────────────────────────────────────────────────

    #[Test]
    public function itSkipsNotificationWhenRepoHasNoReleases(): void
    {
        $this->repository->method('findAllConfirmed')->willReturn([
            new Subscription(1, 'user@example.com', 'owner/repo', true, null, 'tok'),
        ]);

        $this->github->method('getLatestRelease')->willReturn(null);

        $this->mailer->expects(self::never())->method('sendReleaseNotification');

        $this->scanner->scan();
    }

    // ── Error handling ────────────────────────────────────────────────────────

    #[Test]
    public function itHandlesRateLimitExceptionWithoutSendingNotification(): void
    {
        $this->repository->method('findAllConfirmed')->willReturn([
            new Subscription(1, 'user@example.com', 'owner/repo', true, null, 'tok'),
        ]);

        $this->github->method('getLatestRelease')
            ->willThrowException(new RateLimitException(0));

        $this->mailer->expects(self::never())->method('sendReleaseNotification');
        $this->repository->expects(self::never())->method('updateLastSeenTag');

        $this->scanner->scan();
    }

    #[Test]
    public function itContinuesAfterGitHubThrowsGenericException(): void
    {
        $this->repository->method('findAllConfirmed')->willReturn([
            new Subscription(1, 'a@example.com', 'owner/repo1', true, null, 'tok1'),
            new Subscription(2, 'b@example.com', 'owner/repo2', true, null, 'tok2'),
        ]);

        $this->github->expects(self::exactly(2))
            ->method('getLatestRelease')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('network error')),
                'v1.0.0',
            );

        $this->mailer->expects(self::once())
            ->method('sendReleaseNotification')
            ->with('b@example.com', 'owner/repo2', 'v1.0.0', 'tok2');

        $this->scanner->scan();
    }

    #[Test]
    public function itContinuesAfterMailerThrows(): void
    {
        $repo   = 'owner/repo';
        $latestTag = 'v2.0.0';

        $this->repository->method('findAllConfirmed')->willReturn([
            new Subscription(1, 'a@example.com', $repo, true, 'v1.0.0', 'tok1'),
            new Subscription(2, 'b@example.com', $repo, true, 'v1.0.0', 'tok2'),
        ]);

        $this->github->method('getLatestRelease')->willReturn($latestTag);

        $this->mailer->expects(self::exactly(2))
            ->method('sendReleaseNotification')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \RuntimeException('SMTP error')),
                null,
            );

        // Second subscriber still gets updated even though first mailer call failed.
        $this->repository->expects(self::once())
            ->method('updateLastSeenTag')
            ->with(2, $latestTag);

        $this->scanner->scan();
    }

    // ── Metrics ───────────────────────────────────────────────────────────────

    #[Test]
    public function itRecordsMetricForEachNotificationSent(): void
    {
        $repo = 'owner/repo';

        $this->repository->method('findAllConfirmed')->willReturn([
            new Subscription(1, 'a@example.com', $repo, true, 'v1.0.0', 'tok1'),
            new Subscription(2, 'b@example.com', $repo, true, 'v1.0.0', 'tok2'),
        ]);

        $this->github->method('getLatestRelease')->willReturn('v2.0.0');
        $this->metrics->expects(self::exactly(2))->method('recordNotificationSent');

        $this->scanner->scan();
    }
}
