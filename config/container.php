<?php

declare(strict_types=1);

use App\Cache\CacheInterface;
use App\Cache\RedisCache;
use App\Database\Connection;
use App\DTO\SmtpConfig;
use App\Infrastructure\Env;
use App\Metrics\ActiveSubscriptionCounterInterface;
use App\Metrics\DatabaseSubscriptionCounter;
use App\Metrics\MetricsCollector;
use App\Metrics\MetricsCollectorInterface;
use App\Metrics\MetricsRendererInterface;
use App\Metrics\PrometheusRenderer;
use App\Middleware\ApiKeyMiddleware;
use App\Repository\SubscriptionRepository;
use App\Repository\SubscriptionRepositoryInterface;
use App\Repository\SubscriptionScanRepositoryInterface;
use App\Scanner\EchoLogger;
use App\Scanner\LoggerInterface;
use App\Services\ConfirmationMailerInterface;
use App\Services\EmailService;
use App\Services\GitHubReleaseUrlBuilder;
use App\Services\GitHubService;
use App\Services\GitHubServiceInterface;
use App\Services\NotificationMailerInterface;
use App\Services\ReleaseUrlBuilderInterface;
use App\Services\SubscriptionService;
use App\Services\SubscriptionServiceInterface;
use App\Services\TokenGenerator;
use App\Services\TokenGeneratorInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use PDO;
use Psr\Container\ContainerInterface;

return [
    PDO::class => fn (): PDO => Connection::getInstance(),

    RedisCache::class => fn (): RedisCache => RedisCache::create(
        host: Env::string('REDIS_HOST', 'redis'),
        port: Env::int('REDIS_PORT', 6379),
        db:   Env::int('REDIS_DB', 0),
    ),
    CacheInterface::class => \DI\get(RedisCache::class),

    ClientInterface::class => fn (): ClientInterface => new Client(['timeout' => 10.0]),

    SmtpConfig::class => fn (): SmtpConfig => new SmtpConfig(
        host:        Env::string('MAIL_HOST', 'localhost'),
        port:        Env::int('MAIL_PORT', 1025),
        username:    Env::string('MAIL_USERNAME'),
        password:    Env::string('MAIL_PASSWORD'),
        fromAddress: Env::string('MAIL_FROM_ADDRESS', 'noreply@releases-api.app'),
        fromName:    Env::string('MAIL_FROM_NAME', 'Release Notifications'),
    ),

    GitHubService::class => function (ContainerInterface $c): GitHubService {
        /** @var CacheInterface $cache */
        $cache = $c->get(CacheInterface::class);
        /** @var MetricsCollectorInterface $metrics */
        $metrics = $c->get(MetricsCollectorInterface::class);
        $token = Env::string('GITHUB_TOKEN');
        return new GitHubService(
            client:  new Client(['timeout' => 10.0]),
            token:   $token !== '' ? $token : null,
            cache:   $cache,
            metrics: $metrics,
        );
    },
    GitHubServiceInterface::class => \DI\get(GitHubService::class),

    MetricsCollectorInterface::class => \DI\get(MetricsCollector::class),

    EmailService::class => function (ContainerInterface $c): EmailService {
        /** @var SmtpConfig $smtp */
        $smtp = $c->get(SmtpConfig::class);
        /** @var ReleaseUrlBuilderInterface $urlBuilder */
        $urlBuilder = $c->get(ReleaseUrlBuilderInterface::class);
        return new EmailService(
            smtp:              $smtp,
            appUrl:            Env::string('APP_URL', 'http://localhost:8080'),
            releaseUrlBuilder: $urlBuilder,
        );
    },
    ConfirmationMailerInterface::class  => \DI\get(EmailService::class),
    NotificationMailerInterface::class  => \DI\get(EmailService::class),

    ReleaseUrlBuilderInterface::class => \DI\get(GitHubReleaseUrlBuilder::class),
    TokenGeneratorInterface::class    => \DI\get(TokenGenerator::class),

    SubscriptionRepositoryInterface::class     => \DI\get(SubscriptionRepository::class),
    SubscriptionScanRepositoryInterface::class => \DI\get(SubscriptionRepository::class),
    SubscriptionServiceInterface::class        => \DI\get(SubscriptionService::class),

    ActiveSubscriptionCounterInterface::class => \DI\get(DatabaseSubscriptionCounter::class),
    MetricsRendererInterface::class           => \DI\get(PrometheusRenderer::class),

    LoggerInterface::class => \DI\get(EchoLogger::class),

    ApiKeyMiddleware::class => fn (): ApiKeyMiddleware => new ApiKeyMiddleware(Env::string('API_KEY')),
];
