<?php

declare(strict_types=1);

use App\Cache\RedisCache;
use App\Controllers\MetricsController;
use App\Controllers\SubscriptionController;
use App\Database\Connection;
use App\Infrastructure\Env;
use App\Metrics\MetricsCollector;
use App\Middleware\ApiKeyMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\MetricsMiddleware;
use App\Repository\SubscriptionRepository;
use App\Services\EmailService;
use App\Services\GitHubService;
use App\Services\SubscriptionService;
use GuzzleHttp\Client;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    displayErrorDetails: Env::bool('APP_DEBUG'),
    logErrors:           true,
    logErrorDetails:     true
);

// ── Infrastructure ────────────────────────────────────────────────────────────

$redisCache = RedisCache::create(
    host: Env::string('REDIS_HOST', 'redis'),
    port: Env::int('REDIS_PORT', 6379),
    db:   Env::int('REDIS_DB', 0),
);

$pdo     = Connection::getInstance();
$metrics = new MetricsCollector($redisCache, $pdo);

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

$subscriptionService = new SubscriptionService(
    repository: new SubscriptionRepository($pdo),
    github:     $githubService,
    email:      $emailService,
);

// ── Controllers ───────────────────────────────────────────────────────────────

$subscriptionController = new SubscriptionController($subscriptionService);
$metricsController      = new MetricsController($metrics);

// ── Middleware ────────────────────────────────────────────────────────────────

$app->add(new MetricsMiddleware($metrics));
$app->add(new ApiKeyMiddleware(Env::string('API_KEY')));
$app->add(new CorsMiddleware());

// ── Routes ────────────────────────────────────────────────────────────────────

$app->get('/', fn($req, $res) => $res->withHeader('Location', '/subscribe.html')->withStatus(302));
$app->post('/api/subscribe', [$subscriptionController, 'subscribe']);
$app->get('/api/confirm/{token}', [$subscriptionController, 'confirm']);
$app->get('/api/unsubscribe/{token}', [$subscriptionController, 'unsubscribe']);
$app->get('/api/subscriptions', [$subscriptionController, 'getSubscriptions']);
$app->get('/metrics', [$metricsController,      'metrics']);

$app->run();
