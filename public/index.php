<?php

declare(strict_types=1);

use App\Controllers\MetricsController;
use App\Controllers\SubscriptionController;
use App\Infrastructure\Env;
use App\Middleware\ApiKeyMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\MetricsMiddleware;
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
    displayErrorDetails: Env::bool('APP_DEBUG'),
    logErrors:           true,
    logErrorDetails:     true
);

$app->add(MetricsMiddleware::class);
$app->add(ApiKeyMiddleware::class);
$app->add(CorsMiddleware::class);

$app->get('/', fn ($req, $res) => $res->withHeader('Location', '/subscribe.html')->withStatus(302));
$app->post('/api/subscribe', [SubscriptionController::class, 'subscribe']);
$app->get('/api/confirm/{token}', [SubscriptionController::class, 'confirm']);
$app->get('/api/unsubscribe/{token}', [SubscriptionController::class, 'unsubscribe']);
$app->get('/api/subscriptions', [SubscriptionController::class, 'getSubscriptions']);
$app->get('/metrics', [MetricsController::class, 'metrics']);

$app->run();
