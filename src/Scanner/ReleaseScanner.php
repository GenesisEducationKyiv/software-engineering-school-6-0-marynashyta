<?php

declare(strict_types=1);

namespace App\Scanner;

use App\DTO\Subscription;
use App\Exceptions\RateLimitException;
use App\Metrics\MetricsCollectorInterface;
use App\Repository\SubscriptionScanRepositoryInterface;
use App\Services\GitHubServiceInterface;
use App\Services\NotificationMailerInterface;

final class ReleaseScanner
{
    public function __construct(
        private readonly SubscriptionScanRepositoryInterface $repository,
        private readonly GitHubServiceInterface $github,
        private readonly NotificationMailerInterface $mailer,
        private readonly MetricsCollectorInterface $metrics,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function scan(): void
    {
        $subscriptions = $this->repository->findAllConfirmed();

        if (empty($subscriptions)) {
            $this->logger->log('INFO', 'No confirmed subscriptions found.');
            return;
        }

        /** @var array<string, list<Subscription>> $grouped */
        $grouped = [];
        foreach ($subscriptions as $sub) {
            $grouped[$sub->repo][] = $sub;
        }

        $repoCount = count($grouped);
        $subCount  = count($subscriptions);
        $this->logger->log('INFO', "Checking {$repoCount} unique repo(s) for {$subCount} subscription(s).");

        foreach ($grouped as $repo => $repoSubscriptions) {
            $this->logger->log('INFO', "Checking latest release for: {$repo}");
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
            $this->logger->log('WARNING', "Rate limit hit for {$repo}. Sleeping {$retryAfter}s...");
            sleep($retryAfter);
            return;
        } catch (\Throwable $e) {
            $this->logger->log('ERROR', "Failed to fetch release for {$repo}: " . $e->getMessage());
            return;
        }

        if ($latestTag === null) {
            $this->logger->log('INFO', "No releases found for {$repo}.");
            return;
        }

        $this->logger->log('INFO', "Latest release for {$repo}: {$latestTag}");

        foreach ($repoSubscriptions as $sub) {
            if ($latestTag === $sub->lastSeenTag) {
                continue;
            }

            $prev = $sub->lastSeenTag ?? 'none';
            $this->logger->log('INFO', "New release for {$sub->email} on {$repo}: {$latestTag} (was: {$prev})");

            try {
                $this->mailer->sendReleaseNotification(
                    $sub->email,
                    $repo,
                    $latestTag,
                    $sub->unsubscribeToken
                );
                $this->repository->updateLastSeenTag($sub->id, $latestTag);
                $this->metrics->recordNotificationSent();
                $this->logger->log('INFO', "Notification sent to {$sub->email} for {$repo} {$latestTag}");
            } catch (\Throwable $e) {
                $this->logger->log('ERROR', "Failed to send notification to {$sub->email}: " . $e->getMessage());
            }
        }
    }
}
