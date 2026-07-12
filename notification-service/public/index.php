<?php

declare(strict_types=1);

use NotificationService\Handler\SendConfirmationHandler;
use NotificationService\Handler\SendNotificationHandler;
use NotificationService\Health\HealthController;
use NotificationService\Middleware\RequestLoggingMiddleware;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$container = (new DI\ContainerBuilder())
    ->addDefinitions(dirname(__DIR__) . '/config/container.php')
    ->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    displayErrorDetails: (bool) ($_ENV['APP_DEBUG'] ?? false),
    logErrors:           true,
    logErrorDetails:     true,
);
$app->add(RequestLoggingMiddleware::class);

$app->post('/send-confirmation', SendConfirmationHandler::class);
$app->post('/send-notification', SendNotificationHandler::class);

$app->get('/health/live',  [HealthController::class, 'live']);
$app->get('/health/ready', [HealthController::class, 'ready']);
$app->get('/health', [HealthController::class, 'live']);

$app->run();
