<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ScannerService\GitHub\Exception\RateLimitException;
use ScannerService\GitHub\GitHubClientInterface;
use ScannerService\Notification\NotificationPublisherInterface;
use ScannerService\Scanner\ReleaseScanner;
use ScannerService\Subscription\Subscription;
use ScannerService\Subscription\SubscriptionScanClientInterface;

final class ReleaseScannerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionScanClientInterface $subscriptions;
    private GitHubClientInterface $github;
    private NotificationPublisherInterface $publisher;
    private LoggerInterface $logger;
    private ReleaseScanner $scanner;

    #[Test]
    public function itDoesNothingWhenNoSubscriptions(): void
    {
        $this->subscriptions->expects('findAllConfirmed')->andReturn([]);
        $this->github->expects('getLatestRelease')->never();
        $this->publisher->expects('sendReleaseNotification')->never();

        $this->scanner->scan();
    }

    #[Test]
    public function itSendsNotificationWhenNewReleaseFound(): void
    {
        $sub = new Subscription(1, 'user@example.com', 'owner/repo', 'v1.0.0', 'unsub-token');

        $this->subscriptions->expects('findAllConfirmed')->andReturn([$sub]);
        $this->github->expects('getLatestRelease')->with('owner/repo')->andReturn('v2.0.0');
        $this->publisher->expects('sendReleaseNotification')
            ->with('user@example.com', 'owner/repo', 'v2.0.0', 'unsub-token')
            ->once();
        $this->subscriptions->expects('updateLastSeenTag')->with(1, 'v2.0.0')->once();

        $this->scanner->scan();
    }

    #[Test]
    public function itSkipsNotificationWhenReleaseUnchanged(): void
    {
        $sub = new Subscription(1, 'user@example.com', 'owner/repo', 'v1.0.0', 'unsub-token');

        $this->subscriptions->expects('findAllConfirmed')->andReturn([$sub]);
        $this->github->expects('getLatestRelease')->with('owner/repo')->andReturn('v1.0.0');
        $this->publisher->expects('sendReleaseNotification')->never();
        $this->subscriptions->expects('updateLastSeenTag')->never();

        $this->scanner->scan();
    }

    #[Test]
    public function itSkipsNotificationWhenRepoHasNoReleases(): void
    {
        $sub = new Subscription(1, 'user@example.com', 'owner/repo', null, 'unsub-token');

        $this->subscriptions->expects('findAllConfirmed')->andReturn([$sub]);
        $this->github->expects('getLatestRelease')->with('owner/repo')->andReturn(null);
        $this->publisher->expects('sendReleaseNotification')->never();
        $this->subscriptions->expects('updateLastSeenTag')->never();

        $this->scanner->scan();
    }

    #[Test]
    public function itHandlesGitHubRateLimitGracefully(): void
    {
        $sub = new Subscription(1, 'user@example.com', 'owner/repo', null, 'unsub-token');

        $this->subscriptions->expects('findAllConfirmed')->andReturn([$sub]);
        $this->github->expects('getLatestRelease')->andThrow(new RateLimitException(0));
        $this->publisher->expects('sendReleaseNotification')->never();
        $this->subscriptions->expects('updateLastSeenTag')->never();

        $this->scanner->scan();
    }

    #[Test]
    public function itHandlesGitHubErrorAndContinuesToNextRepo(): void
    {
        $sub1 = new Subscription(1, 'a@example.com', 'owner/repo1', null, 'token1');
        $sub2 = new Subscription(2, 'b@example.com', 'owner/repo2', null, 'token2');

        $this->subscriptions->expects('findAllConfirmed')->andReturn([$sub1, $sub2]);
        $this->github->expects('getLatestRelease')->with('owner/repo1')
            ->andThrow(new \RuntimeException('Network error'));
        $this->github->expects('getLatestRelease')->with('owner/repo2')->andReturn('v1.0.0');
        $this->publisher->expects('sendReleaseNotification')
            ->with('b@example.com', 'owner/repo2', 'v1.0.0', 'token2')
            ->once();
        $this->subscriptions->expects('updateLastSeenTag')->with(2, 'v1.0.0')->once();

        $this->scanner->scan();
    }

    #[Test]
    public function itDoesNotUpdateTagWhenNotificationFails(): void
    {
        $sub = new Subscription(1, 'user@example.com', 'owner/repo', null, 'unsub-token');

        $this->subscriptions->expects('findAllConfirmed')->andReturn([$sub]);
        $this->github->expects('getLatestRelease')->with('owner/repo')->andReturn('v1.0.0');
        $this->publisher->expects('sendReleaseNotification')
            ->andThrow(new \RuntimeException('AMQP unavailable'));
        $this->subscriptions->expects('updateLastSeenTag')->never();

        $this->scanner->scan();
    }

    #[Test]
    public function itCallsGitHubOncePerRepoForMultipleSubscribers(): void
    {
        $sub1 = new Subscription(1, 'a@example.com', 'owner/repo', 'v1.0.0', 'token1');
        $sub2 = new Subscription(2, 'b@example.com', 'owner/repo', 'v1.0.0', 'token2');

        $this->subscriptions->expects('findAllConfirmed')->andReturn([$sub1, $sub2]);
        $this->github->expects('getLatestRelease')->with('owner/repo')->once()->andReturn('v2.0.0');
        $this->publisher->expects('sendReleaseNotification')
            ->with('a@example.com', 'owner/repo', 'v2.0.0', 'token1')->once();
        $this->publisher->expects('sendReleaseNotification')
            ->with('b@example.com', 'owner/repo', 'v2.0.0', 'token2')->once();
        $this->subscriptions->expects('updateLastSeenTag')->with(1, 'v2.0.0')->once();
        $this->subscriptions->expects('updateLastSeenTag')->with(2, 'v2.0.0')->once();

        $this->scanner->scan();
    }

    #[Test]
    public function itNotifiesSubscriberWhenLastSeenTagIsNull(): void
    {
        $sub = new Subscription(1, 'user@example.com', 'owner/repo', null, 'unsub-token');

        $this->subscriptions->expects('findAllConfirmed')->andReturn([$sub]);
        $this->github->expects('getLatestRelease')->with('owner/repo')->andReturn('v1.0.0');
        $this->publisher->expects('sendReleaseNotification')
            ->with('user@example.com', 'owner/repo', 'v1.0.0', 'unsub-token')
            ->once();
        $this->subscriptions->expects('updateLastSeenTag')->with(1, 'v1.0.0')->once();

        $this->scanner->scan();
    }

    protected function setUp(): void
    {
        $this->subscriptions = Mockery::mock(SubscriptionScanClientInterface::class);
        $this->github        = Mockery::mock(GitHubClientInterface::class);
        $this->publisher     = Mockery::mock(NotificationPublisherInterface::class);
        $this->logger        = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();

        $this->scanner = new ReleaseScanner(
            $this->subscriptions,
            $this->github,
            $this->publisher,
            $this->logger,
        );
    }
}
