<?php

declare(strict_types=1);

use NotificationService\Grpc\NotificationServiceInterface;
use NotificationService\Grpc\NotificationServiceImpl;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\GRPC\Server;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$container = (new DI\ContainerBuilder())
    ->addDefinitions(dirname(__DIR__) . '/config/container.php')
    ->build();

$worker = Worker::create();
$server = new Server(options: ['debug' => (bool) ($_ENV['APP_DEBUG'] ?? false)]);

/** @var NotificationServiceImpl $impl */
$impl = $container->get(NotificationServiceImpl::class);
$server->registerService(NotificationServiceInterface::class, $impl);

$server->serve($worker);
