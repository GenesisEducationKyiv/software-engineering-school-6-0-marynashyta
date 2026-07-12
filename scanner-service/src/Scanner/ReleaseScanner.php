<?php

declare(strict_types=1);

namespace ScannerService\Scanner;

use Psr\Log\LoggerInterface;
use ScannerService\GitHub\Exception\RateLimitException;
use ScannerService\GitHub\GitHubClientInterface;
use ScannerService\Notification\NotificationPublisherInterface;
use ScannerService\Subscription\Subscription;
use ScannerService\Subscription\SubscriptionScanClientInterface;

final class ReleaseScanner
{
    public function __construct(
        private readonly SubscriptionScanClientInterface $subscriptions,
        private readonly GitHubClientInterface $github,
        private readonly NotificationPublisherInterface $publisher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function scan(): void
    {
        $subscriptions = $this->subscriptions->findAllConfirmed();

        if (empty($subscriptions)) {
            $this->logger->info('No confirmed subscriptions found.');
            return;
        }

        /** @var array<string, list<Subscription>> $grouped */
        $grouped = [];
        foreach ($subscriptions as $sub) {
            $grouped[$sub->repo][] = $sub;
        }

        $repoCount = count($grouped);
        $subCount  = count($subscriptions);
        $this->logger->info("Checking {$repoCount} unique repo(s) for {$subCount} subscription(s).");

        foreach ($grouped as $repo => $repoSubscriptions) {
            $this->logger->info("Checking latest release for: {$repo}");
            $this->processRepo($repo, $repoSubscriptions);
        }
    }

    /** @param list<Subscription> $repoSubscriptions */
    private function processRepo(string $repo, array $repoSubscriptions): void
    {
        try {
            $latestTag = $this->github->getLatestRelease($repo);
        } catch (RateLimitException $e) {
            $retryAfter = $e->getRetryAfter();
            $this->logger->warning("Rate limit hit for {$repo}. Sleeping {$retryAfter}s...");
            sleep($retryAfter);
            return;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to fetch release for {$repo}: " . $e->getMessage());
            return;
        }

        if ($latestTag === null) {
            $this->logger->info("No releases found for {$repo}.");
            return;
        }

        $this->logger->info("Latest release for {$repo}: {$latestTag}");

        foreach ($repoSubscriptions as $sub) {
            if ($latestTag === $sub->lastSeenTag) {
                continue;
            }

            $prev = $sub->lastSeenTag ?? 'none';
            $this->logger->info("New release for {$sub->email} on {$repo}: {$latestTag} (was: {$prev})");

            try {
                $this->publisher->sendReleaseNotification(
                    $sub->email,
                    $repo,
                    $latestTag,
                    $sub->unsubscribeToken,
                );
                $this->subscriptions->updateLastSeenTag($sub->id, $latestTag);
                $this->logger->info("Notification queued for {$sub->email} on {$repo} {$latestTag}");
            } catch (\Throwable $e) {
                $this->logger->error("Failed to notify {$sub->email}: " . $e->getMessage());
            }
        }
    }
}
