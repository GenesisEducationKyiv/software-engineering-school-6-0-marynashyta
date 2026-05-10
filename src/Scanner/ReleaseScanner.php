<?php

declare(strict_types=1);

namespace App\Scanner;

use App\Exceptions\RateLimitException;
use App\Metrics\MetricsCollector;
use App\Repository\SubscriptionRepositoryInterface;
use App\Services\GitHubServiceInterface;
use App\Services\NotificationMailerInterface;

final class ReleaseScanner
{
    /**
     * @param \Closure(string, string): void $logger
     */
    public function __construct(
        private readonly SubscriptionRepositoryInterface $repository,
        private readonly GitHubServiceInterface $github,
        private readonly NotificationMailerInterface $mailer,
        private readonly MetricsCollector $metrics,
        private readonly \Closure $logger,
    ) {
    }

    public function scan(): void
    {
        $subscriptions = $this->repository->findAllConfirmed();

        if (empty($subscriptions)) {
            $this->log('INFO', 'No confirmed subscriptions found.');
            return;
        }

        /** @var array<string, list<array{id: int, email: string, repo: string, last_seen_tag: string|null,
         *                                                                             unsubscribe_token: string}>> $grouped */
        $grouped = [];
        foreach ($subscriptions as $sub) {
            $grouped[$sub['repo']][] = $sub;
        }

        $repoCount = count($grouped);
        $subCount  = count($subscriptions);
        $this->log('INFO', "Checking {$repoCount} unique repo(s) for {$subCount} subscription(s).");

        foreach ($grouped as $repo => $repoSubscriptions) {
            $this->log('INFO', "Checking latest release for: {$repo}");
            $this->processRepo($repo, $repoSubscriptions);
        }
    }

    /**
     * @param list<array{id: int, email: string, repo: string, last_seen_tag: string|null,
     *                   unsubscribe_token: string}> $repoSubscriptions
     */
    private function processRepo(string $repo, array $repoSubscriptions): void
    {
        try {
            $latestTag = $this->github->getLatestRelease($repo);
        } catch (RateLimitException $e) {
            $retryAfter = $e->getRetryAfter();
            $this->log('WARNING', "Rate limit hit for {$repo}. Sleeping {$retryAfter}s...");
            sleep($retryAfter);
            return;
        } catch (\Throwable $e) {
            $this->log('ERROR', "Failed to fetch release for {$repo}: " . $e->getMessage());
            return;
        }

        if ($latestTag === null) {
            $this->log('INFO', "No releases found for {$repo}.");
            return;
        }

        $this->log('INFO', "Latest release for {$repo}: {$latestTag}");

        foreach ($repoSubscriptions as $sub) {
            if ($latestTag === $sub['last_seen_tag']) {
                continue;
            }

            $prev = $sub['last_seen_tag'] ?? 'none';
            $this->log('INFO', "New release for {$sub['email']} on {$repo}: {$latestTag} (was: {$prev})");

            try {
                $this->mailer->sendReleaseNotification(
                    $sub['email'],
                    $repo,
                    $latestTag,
                    $sub['unsubscribe_token']
                );
                $this->repository->updateLastSeenTag($sub['id'], $latestTag);
                $this->metrics->recordNotificationSent();
                $this->log('INFO', "Notification sent to {$sub['email']} for {$repo} {$latestTag}");
            } catch (\Throwable $e) {
                $this->log('ERROR', "Failed to send notification to {$sub['email']}: " . $e->getMessage());
            }
        }
    }

    private function log(string $level, string $message): void
    {
        ($this->logger)($level, $message);
    }
}
