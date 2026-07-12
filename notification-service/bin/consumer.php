#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use NotificationService\Consumer\NotificationConsumer;

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    Dotenv::createImmutable(__DIR__ . '/..')->load();
}

$container = (new ContainerBuilder())
    ->addDefinitions(require __DIR__ . '/../config/container.php')
    ->build();

/** @var NotificationConsumer $consumer */
$consumer = $container->get(NotificationConsumer::class);
$consumer->run();
