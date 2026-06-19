<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use ScannerService\Config\AmqpConfig;
use ScannerService\Config\ApiConfig;
use ScannerService\Config\Env;
use ScannerService\GitHub\GitHubClientInterface;
use ScannerService\GitHub\GitHubService;
use ScannerService\Infrastructure\CircuitBreaker;
use ScannerService\Notification\AmqpNotificationPublisher;
use ScannerService\Notification\NotificationPublisherInterface;
use ScannerService\Scanner\ReleaseScanner;
use ScannerService\Subscription\HttpSubscriptionScanClient;
use ScannerService\Subscription\SubscriptionScanClientInterface;

return [
    LoggerInterface::class => function (): LoggerInterface {
        $level = match (strtolower(Env::string('LOG_LEVEL', 'warning'))) {
            'debug'     => Level::Debug,
            'info'      => Level::Info,
            'notice'    => Level::Notice,
            'error'     => Level::Error,
            'critical'  => Level::Critical,
            'alert'     => Level::Alert,
            'emergency' => Level::Emergency,
            default     => Level::Warning,
        };
        $handler = new StreamHandler(Env::string('LOG_PATH', 'php://stderr'), $level);
        $handler->setFormatter(new JsonFormatter());
        $logger = new Logger('scanner-service');
        $logger->pushHandler($handler);
        return $logger;
    },

    ClientInterface::class => fn (): ClientInterface => new Client(['timeout' => 10.0]),

    ApiConfig::class => fn (): ApiConfig => new ApiConfig(
        baseUrl: rtrim(Env::string('API_BASE_URL', 'http://api:80'), '/'),
        apiKey:  Env::string('API_KEY'),
    ),

    AmqpConfig::class => fn (): AmqpConfig => new AmqpConfig(
        host:     Env::string('RABBITMQ_HOST', 'rabbitmq'),
        port:     Env::int('RABBITMQ_PORT', 5672),
        user:     Env::string('RABBITMQ_USER', 'guest'),
        password: Env::string('RABBITMQ_PASSWORD', 'guest'),
    ),

    'circuit_breaker.github' => fn (): CircuitBreaker => new CircuitBreaker(
        name:           'github-api',
        threshold:      5,
        timeoutSeconds: 60,
    ),

    'circuit_breaker.subscription' => fn (): CircuitBreaker => new CircuitBreaker(
        name:           'subscription-api',
        threshold:      3,
        timeoutSeconds: 30,
    ),

    GitHubService::class => \DI\autowire()
        ->constructorParameter('token', \DI\factory(function (): ?string {
            $token = Env::string('GITHUB_TOKEN');
            return $token !== '' ? $token : null;
        }))
        ->constructorParameter('circuitBreaker', \DI\get('circuit_breaker.github')),
    GitHubClientInterface::class => \DI\get(GitHubService::class),

    HttpSubscriptionScanClient::class => \DI\autowire()
        ->constructorParameter('circuitBreaker', \DI\get('circuit_breaker.subscription')),
    SubscriptionScanClientInterface::class => \DI\get(HttpSubscriptionScanClient::class),

    AmqpNotificationPublisher::class => \DI\autowire(),
    NotificationPublisherInterface::class => \DI\get(AmqpNotificationPublisher::class),

    ReleaseScanner::class => \DI\autowire(),
];
