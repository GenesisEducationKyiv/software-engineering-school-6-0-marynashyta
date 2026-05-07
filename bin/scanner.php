#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Cache\RedisCache;
use App\Database\Connection;
use App\Exceptions\RateLimitException;
use App\Infrastructure\Env;
use App\Metrics\MetricsCollector;
use App\Services\EmailService;
use App\Services\GitHubService;
use GuzzleHttp\Client;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

function logMessage(string $level, string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . "] [{$level}] {$message}" . PHP_EOL;
}

// ── Infrastructure ────────────────────────────────────────────────────────────

$redisCache = RedisCache::create(
    host: Env::string('REDIS_HOST', 'redis'),
    port: Env::int('REDIS_PORT', 6379),
    db: Env::int('REDIS_DB', 0),
);

$metrics = new MetricsCollector($redisCache);

// ── Application services ──────────────────────────────────────────────────────

$githubToken = Env::string('GITHUB_TOKEN');

$githubService = new GitHubService(
    client: new Client(['timeout' => 10.0]),
    token: $githubToken !== '' ? $githubToken : null,
    cache: $redisCache,
    metrics: $metrics,
);

$emailService = new EmailService(
    host: Env::string('MAIL_HOST', 'localhost'),
    port: Env::int('MAIL_PORT', 1025),
    username: Env::string('MAIL_USERNAME'),
    password: Env::string('MAIL_PASSWORD'),
    fromAddress: Env::string('MAIL_FROM_ADDRESS', 'noreply@releases-api.app'),
    fromName: Env::string('MAIL_FROM_NAME', 'Release Notifications'),
    appUrl: Env::string('APP_URL', 'http://localhost:8080'),
);

$scanInterval = Env::int('SCANNER_INTERVAL', 300);

logMessage('INFO', "Scanner started. Interval: {$scanInterval}s");
logMessage('INFO', 'Redis ' . ($redisCache->isConnected() ? 'connected' : 'unavailable — caching disabled'));

while (true) {
    logMessage('INFO', 'Starting scan cycle...');

    try {
        $pdo = Connection::getInstance();

        $stmt = $pdo->query(
            'SELECT id, email, repo, last_seen_tag, unsubscribe_token
             FROM subscriptions
             WHERE confirmed = 1'
        );

        /** @var list<array<string, mixed>> $subscriptions */
        $subscriptions = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        if (empty($subscriptions)) {
            logMessage('INFO', 'No confirmed subscriptions found.');
        } else {
            /** @var array<string, list<array<string, mixed>>> $grouped */
            $grouped = [];
            foreach ($subscriptions as $sub) {
                $repo = is_string($sub['repo']) ? $sub['repo'] : '';
                $grouped[$repo][] = $sub;
            }

            $repoCount = count($grouped);
            $subCount = count($subscriptions);
            logMessage('INFO', "Checking {$repoCount} unique repo(s) for {$subCount} subscription(s).");

            foreach ($grouped as $repo => $repoSubscriptions) {
                logMessage('INFO', "Checking latest release for: {$repo}");

                try {
                    $latestTag = $githubService->getLatestRelease($repo);
                } catch (RateLimitException $e) {
                    $retryAfter = $e->getRetryAfter();
                    logMessage('WARNING', "Rate limit hit for {$repo}. Sleeping {$retryAfter}s...");
                    sleep($retryAfter);
                    continue;
                } catch (\Throwable $e) {
                    logMessage('ERROR', "Failed to fetch release for {$repo}: " . $e->getMessage());
                    continue;
                }

                if ($latestTag === null) {
                    logMessage('INFO', "No releases found for {$repo}.");
                    continue;
                }

                logMessage('INFO', "Latest release for {$repo}: {$latestTag}");

                foreach ($repoSubscriptions as $subscription) {
                    $lastSeenTag = is_string($subscription['last_seen_tag']) ? $subscription['last_seen_tag'] : null;
                    $unsubscribeToken = is_string($subscription['unsubscribe_token'])
                        ? $subscription['unsubscribe_token']
                        : '';
                    $email = is_string($subscription['email']) ? $subscription['email'] : '';
                    $id = is_numeric($subscription['id']) ? (int)$subscription['id'] : 0;

                    if ($latestTag === $lastSeenTag) {
                        continue;
                    }

                    $prev = $lastSeenTag ?? 'none';
                    logMessage('INFO', "New release for {$email} on {$repo}: {$latestTag} (was: {$prev})");

                    try {
                        $emailService->sendReleaseNotification($email, $repo, $latestTag, $unsubscribeToken);

                        $pdo->prepare('UPDATE subscriptions SET last_seen_tag = ? WHERE id = ?')
                            ->execute([$latestTag, $id]);

                        $metrics->recordNotificationSent();

                        logMessage('INFO', "Notification sent to {$email} for {$repo} {$latestTag}");
                    } catch (\Throwable $e) {
                        logMessage('ERROR', "Failed to send notification to {$email}: " . $e->getMessage());
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        logMessage('ERROR', 'Scan cycle failed: ' . $e->getMessage());
    }

    $metrics->recordScannerCycle();

    logMessage('INFO', "Scan cycle complete. Sleeping {$scanInterval}s...");
    sleep($scanInterval);
}
