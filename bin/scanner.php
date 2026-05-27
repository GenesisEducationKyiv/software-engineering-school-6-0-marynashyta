#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Cache\RedisCache;
use App\Infrastructure\Env;
use App\Metrics\MetricsCollectorInterface;
use App\Scanner\EchoLogger;
use App\Scanner\ReleaseScanner;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$container = (new DI\ContainerBuilder())
    ->addDefinitions(dirname(__DIR__) . '/config/container.php')
    ->build();

/** @var ReleaseScanner $scanner */
$scanner = $container->get(ReleaseScanner::class);

/** @var EchoLogger $log */
$log = $container->get(EchoLogger::class);

/** @var RedisCache $redisCache */
$redisCache = $container->get(RedisCache::class);

/** @var MetricsCollectorInterface $metrics */
$metrics = $container->get(MetricsCollectorInterface::class);

$scanInterval = Env::int('SCANNER_INTERVAL', 300);

$log->log('INFO', "Scanner started. Interval: {$scanInterval}s");
$log->log('INFO', 'Redis ' . ($redisCache->isConnected() ? 'connected' : 'unavailable — caching disabled'));

while (true) {
    $log->log('INFO', 'Starting scan cycle...');

    try {
        $scanner->scan();
    } catch (\Throwable $e) {
        $log->log('ERROR', 'Scan cycle failed: ' . $e->getMessage());
    }

    $metrics->recordScannerCycle();

    $log->log('INFO', "Scan cycle complete. Sleeping {$scanInterval}s...");
    sleep($scanInterval);
}
