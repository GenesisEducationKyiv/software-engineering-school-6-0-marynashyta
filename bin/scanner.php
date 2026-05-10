#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Cache\RedisCache;
use App\Database\Connection;
use App\Infrastructure\Env;
use App\Metrics\MetricsCollector;
use App\Repository\SubscriptionRepository;
use App\Scanner\ReleaseScanner;
use App\Services\EmailService;
use App\Services\GitHubService;
use GuzzleHttp\Client;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$log = static function (string $level, string $message): void {
    echo '[' . date('Y-m-d H:i:s') . "] [{$level}] {$message}" . PHP_EOL;
};

// ── Infrastructure ────────────────────────────────────────────────────────────

$redisCache = RedisCache::create(
    host: Env::string('REDIS_HOST', 'redis'),
    port: Env::int('REDIS_PORT', 6379),
    db:   Env::int('REDIS_DB', 0),
);

$metrics = new MetricsCollector($redisCache);

// ── Application services ──────────────────────────────────────────────────────

$githubToken = Env::string('GITHUB_TOKEN');

$githubService = new GitHubService(
    client:  new Client(['timeout' => 10.0]),
    token:   $githubToken !== '' ? $githubToken : null,
    cache:   $redisCache,
    metrics: $metrics,
);

$emailService = new EmailService(
    host:        Env::string('MAIL_HOST', 'localhost'),
    port:        Env::int('MAIL_PORT', 1025),
    username:    Env::string('MAIL_USERNAME'),
    password:    Env::string('MAIL_PASSWORD'),
    fromAddress: Env::string('MAIL_FROM_ADDRESS', 'noreply@releases-api.app'),
    fromName:    Env::string('MAIL_FROM_NAME', 'Release Notifications'),
    appUrl:      Env::string('APP_URL', 'http://localhost:8080'),
);

$scanner = new ReleaseScanner(
    repository: new SubscriptionRepository(Connection::getInstance()),
    github:     $githubService,
    mailer:     $emailService,
    metrics:    $metrics,
    logger:     $log,
);

$scanInterval = Env::int('SCANNER_INTERVAL', 300);

$log('INFO', "Scanner started. Interval: {$scanInterval}s");
$log('INFO', 'Redis ' . ($redisCache->isConnected() ? 'connected' : 'unavailable — caching disabled'));

while (true) {
    $log('INFO', 'Starting scan cycle...');

    try {
        $scanner->scan();
    } catch (\Throwable $e) {
        $log('ERROR', 'Scan cycle failed: ' . $e->getMessage());
    }

    $metrics->recordScannerCycle();

    $log('INFO', "Scan cycle complete. Sleeping {$scanInterval}s...");
    sleep($scanInterval);
}
