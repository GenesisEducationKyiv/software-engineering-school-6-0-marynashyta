<?php

declare(strict_types=1);

use App\Bootstrap\Middleware\ApiKeyMiddleware;
use App\Modules\GitHub\Domain\ReleaseUrlBuilderInterface;
use App\Modules\GitHub\Infrastructure\GitHubReleaseUrlBuilder;
use App\Modules\GitHub\Infrastructure\GitHubService;
use App\Modules\GitHub\Domain\GitHubServiceInterface;
use App\Modules\Notification\Domain\ConfirmationMailerInterface;
use App\Modules\Notification\Domain\NotificationMailerInterface;
use App\Modules\Notification\Infrastructure\EmailService;
use App\Modules\Notification\Infrastructure\Amqp\AmqpConfig;
use App\Modules\Notification\Infrastructure\Amqp\AmqpConfirmationMailer;
use App\Modules\Notification\Infrastructure\Amqp\AmqpNotificationMailer;
use App\Modules\Notification\Infrastructure\Amqp\AmqpPublisher;
use App\Modules\Notification\Infrastructure\Http\HttpConfirmationMailer;
use App\Modules\Notification\Infrastructure\Http\HttpNotificationMailer;
use App\Modules\Notification\Infrastructure\SmtpConfig;
use App\Modules\Observability\Domain\ActiveSubscriptionCounterInterface;
use App\Modules\Observability\Domain\MetricsCollectorInterface;
use App\Modules\Observability\Domain\MetricsRendererInterface;
use App\Modules\Observability\Infrastructure\DatabaseSubscriptionCounter;
use App\Modules\Observability\Infrastructure\MetricsCollector;
use App\Modules\Observability\Infrastructure\PrometheusRenderer;
use App\Modules\Scanner\Domain\LoggerInterface;
use App\Modules\Scanner\Infrastructure\MonologLogger;
use App\Modules\Subscription\Application\Saga\SubscribeSagaOrchestrator;
use App\Modules\Subscription\Application\Saga\SubscribeSagaOrchestratorInterface;
use App\Modules\Subscription\Application\TokenGenerator;
use App\Modules\Subscription\Application\TokenGeneratorInterface;
use App\Modules\Subscription\Application\SubscriptionService;
use App\Modules\Subscription\Application\SubscriptionServiceInterface;
use App\Modules\Subscription\Domain\Saga\SagaRepositoryInterface;
use App\Modules\Subscription\Domain\SubscriptionRepositoryInterface;
use App\Modules\Subscription\Domain\SubscriptionScanRepositoryInterface;
use App\Modules\Subscription\Infrastructure\Persistence\SagaRepository;
use App\Modules\Subscription\Infrastructure\Persistence\SubscriptionRepository;
use App\SharedKernel\Infrastructure\Cache\CacheInterface;
use App\SharedKernel\Infrastructure\Cache\RedisCache;
use App\SharedKernel\Infrastructure\Database\Connection;
use App\SharedKernel\Infrastructure\Env;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

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
    AmqpConfig::class => fn (): AmqpConfig => new AmqpConfig(
        host:     Env::string('RABBITMQ_HOST', 'rabbitmq'),
        port:     Env::int('RABBITMQ_PORT', 5672),
        user:     Env::string('RABBITMQ_USER', 'guest'),
        password: Env::string('RABBITMQ_PASSWORD', 'guest'),
    ),
    AmqpPublisher::class => fn (ContainerInterface $c): AmqpPublisher =>
        new AmqpPublisher($c->get(AmqpConfig::class)),
    AmqpConfirmationMailer::class => fn (ContainerInterface $c): AmqpConfirmationMailer =>
        new AmqpConfirmationMailer($c->get(AmqpPublisher::class)),
    AmqpNotificationMailer::class => fn (ContainerInterface $c): AmqpNotificationMailer =>
        new AmqpNotificationMailer($c->get(AmqpPublisher::class)),

    HttpConfirmationMailer::class => function (ContainerInterface $c): HttpConfirmationMailer {
        /** @var ClientInterface $http */
        $http = $c->get(ClientInterface::class);
        return new HttpConfirmationMailer($http, Env::string('NOTIFICATION_SERVICE_URL', 'http://notification:80'));
    },
    HttpNotificationMailer::class => function (ContainerInterface $c): HttpNotificationMailer {
        /** @var ClientInterface $http */
        $http = $c->get(ClientInterface::class);
        return new HttpNotificationMailer($http, Env::string('NOTIFICATION_SERVICE_URL', 'http://notification:80'));
    },

    ConfirmationMailerInterface::class => function (ContainerInterface $c): ConfirmationMailerInterface {
        return match (Env::string('NOTIFICATION_DRIVER')) {
            'http'  => $c->get(HttpConfirmationMailer::class),
            'amqp'  => $c->get(AmqpConfirmationMailer::class),
            default => $c->get(EmailService::class),
        };
    },
    NotificationMailerInterface::class => function (ContainerInterface $c): NotificationMailerInterface {
        return match (Env::string('NOTIFICATION_DRIVER')) {
            'http'  => $c->get(HttpNotificationMailer::class),
            'amqp'  => $c->get(AmqpNotificationMailer::class),
            default => $c->get(EmailService::class),
        };
    },

    ReleaseUrlBuilderInterface::class => \DI\get(GitHubReleaseUrlBuilder::class),
    TokenGeneratorInterface::class    => \DI\get(TokenGenerator::class),

    SubscriptionRepositoryInterface::class     => \DI\get(SubscriptionRepository::class),
    SubscriptionScanRepositoryInterface::class => \DI\get(SubscriptionRepository::class),
    SagaRepositoryInterface::class             => \DI\get(SagaRepository::class),

    SubscribeSagaOrchestratorInterface::class => \DI\get(SubscribeSagaOrchestrator::class),

    SubscribeSagaOrchestrator::class => function (ContainerInterface $c): SubscribeSagaOrchestrator {
        /** @var SubscriptionRepositoryInterface $subscriptionRepo */
        $subscriptionRepo = $c->get(SubscriptionRepositoryInterface::class);
        /** @var SagaRepositoryInterface $sagaRepo */
        $sagaRepo = $c->get(SagaRepositoryInterface::class);
        /** @var ConfirmationMailerInterface $mailer */
        $mailer = $c->get(ConfirmationMailerInterface::class);
        /** @var TokenGeneratorInterface $tokenGenerator */
        $tokenGenerator = $c->get(TokenGeneratorInterface::class);
        return new SubscribeSagaOrchestrator($subscriptionRepo, $sagaRepo, $mailer, $tokenGenerator);
    },

    SubscriptionServiceInterface::class => \DI\get(SubscriptionService::class),

    ActiveSubscriptionCounterInterface::class => \DI\get(DatabaseSubscriptionCounter::class),
    MetricsRendererInterface::class           => \DI\get(PrometheusRenderer::class),

    PsrLoggerInterface::class => function (): PsrLoggerInterface {
        $handler = new StreamHandler(
            Env::string('LOG_PATH', 'php://stderr'),
            Level::fromName(Env::string('LOG_LEVEL', 'warning'))
        );
        $handler->setFormatter(new JsonFormatter());
        $logger = new Logger(Env::string('LOG_CHANNEL', 'app'));
        $logger->pushHandler($handler);
        $logger->pushProcessor(new UidProcessor());
        return $logger;
    },
    LoggerInterface::class    => \DI\get(MonologLogger::class),

    ApiKeyMiddleware::class => fn (): ApiKeyMiddleware => new ApiKeyMiddleware(Env::string('API_KEY')),
];
