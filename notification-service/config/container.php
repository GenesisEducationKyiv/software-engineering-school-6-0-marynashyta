<?php

declare(strict_types=1);

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use NotificationService\Config\AmqpConfig;
use NotificationService\Config\Env;
use NotificationService\Config\SmtpConfig;
use NotificationService\Consumer\MessageHandler;
use NotificationService\Consumer\MessageProcessor;
use NotificationService\Consumer\NotificationConsumer;
use NotificationService\Health\HealthController;
use NotificationService\Mailer;
use NotificationService\MailerInterface;
use NotificationService\Middleware\RequestLoggingMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    SmtpConfig::class => fn (): SmtpConfig => new SmtpConfig(
        host:        Env::string('MAIL_HOST', 'localhost'),
        port:        Env::int('MAIL_PORT', 1025),
        username:    Env::string('MAIL_USERNAME'),
        password:    Env::string('MAIL_PASSWORD'),
        fromAddress: Env::string('MAIL_FROM_ADDRESS', 'noreply@releases-api.app'),
        fromName:    Env::string('MAIL_FROM_NAME', 'Release Notifications'),
    ),

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
        $logger = new Logger('notification-service');
        $logger->pushHandler($handler);
        return $logger;
    },

    RequestLoggingMiddleware::class => function (ContainerInterface $c): RequestLoggingMiddleware {
        /** @var LoggerInterface $logger */
        $logger = $c->get(LoggerInterface::class);
        return new RequestLoggingMiddleware($logger);
    },

    Mailer::class => function (ContainerInterface $c): Mailer {
        /** @var SmtpConfig $smtp */
        $smtp = $c->get(SmtpConfig::class);
        return new Mailer(smtp: $smtp, appUrl: Env::string('APP_URL', 'http://localhost:8080'));
    },
    MailerInterface::class => \DI\get(Mailer::class),

    AmqpConfig::class => fn (): AmqpConfig => new AmqpConfig(
        host:     Env::string('RABBITMQ_HOST', 'rabbitmq'),
        port:     Env::int('RABBITMQ_PORT', 5672),
        user:     Env::string('RABBITMQ_USER', 'guest'),
        password: Env::string('RABBITMQ_PASSWORD', 'guest'),
    ),

    MessageHandler::class => \DI\autowire(),

    MessageProcessor::class => \DI\autowire(),

    NotificationConsumer::class => \DI\autowire(),

    HealthController::class => function (ContainerInterface $c): HealthController {
        /** @var SmtpConfig $smtp */
        $smtp = $c->get(SmtpConfig::class);
        return new HealthController(smtpHost: $smtp->host, smtpPort: $smtp->port);
    },
];
