#!/usr/bin/env php
<?php

declare(strict_types=1);

use ScannerService\Config\Env;
use ScannerService\Scanner\ReleaseScanner;

require __DIR__ . '/../vendor/autoload.php';

function sleepInterruptible(int $seconds, bool &$running): void
{
    for ($i = 0; $i < $seconds; $i++) {
        sleep(1);
        pcntl_signal_dispatch();
        if (!$running) {
            break;
        }
    }
}

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$container = (new DI\ContainerBuilder())
    ->addDefinitions(dirname(__DIR__) . '/config/container.php')
    ->build();

/** @var ReleaseScanner $scanner */
$scanner = $container->get(ReleaseScanner::class);

/** @var \Psr\Log\LoggerInterface $logger */
$logger = $container->get(\Psr\Log\LoggerInterface::class);

$scanInterval = Env::int('SCANNER_INTERVAL', 300);

$logger->info('Scanner started', ['interval' => $scanInterval]);

$running = true;
pcntl_signal(SIGTERM, function () use (&$running): void {
    $running = false;
});
pcntl_signal(SIGINT, function () use (&$running): void {
    $running = false;
});

while ($running) {
    $logger->info('Starting scan cycle');

    try {
        $scanner->scan();
    } catch (\Throwable $e) {
        $logger->error('Scan cycle failed', ['error' => $e->getMessage()]);
    }

    $logger->info("Scan cycle complete. Sleeping {$scanInterval}s.");

    sleepInterruptible($scanInterval, $running);
}

$logger->info('Scanner stopped');
