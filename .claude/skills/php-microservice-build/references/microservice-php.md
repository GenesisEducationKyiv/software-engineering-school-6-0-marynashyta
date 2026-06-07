# PHP Microservice Implementation Reference

## Table of Contents
1. [Minimal Service Scaffold](#1-minimal-service-scaffold)
2. [Dockerfile and Docker Compose](#2-dockerfile-and-docker-compose)
3. [HTTP API with Slim Framework](#3-http-api-with-slim-framework)
4. [Message Queue Consumer](#4-message-queue-consumer)
5. [Health Checks and Readiness Probes](#5-health-checks-and-readiness-probes)
6. [Circuit Breaker](#6-circuit-breaker)
7. [Observability: Logging, Metrics, Tracing](#7-observability-logging-metrics-tracing)
8. [12-Factor Configuration](#8-12-factor-configuration)

---

## 1. Minimal Service Scaffold

```
billing-service/
├── composer.json
├── composer.lock
├── public/
│   └── index.php               # HTTP entry point
├── bin/
│   └── consumer.php            # Queue consumer entry point (if needed)
├── src/
│   ├── Domain/                 # Pure PHP — zero framework deps
│   │   ├── Invoice.php
│   │   ├── InvoiceRepository.php
│   │   └── Events/
│   │       └── InvoicePaid.php
│   ├── Application/            # Use-case handlers
│   │   ├── CreateInvoice/
│   │   │   ├── CreateInvoiceCommand.php
│   │   │   └── CreateInvoiceHandler.php
│   │   └── GetInvoice/
│   │       ├── GetInvoiceQuery.php
│   │       └── GetInvoiceHandler.php
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   │   └── PdoInvoiceRepository.php
│   │   └── Http/
│   │       └── InvoiceController.php
│   └── Container.php           # Dependency injection wiring
├── config/
│   └── settings.php
├── database/
│   └── migrations/
│       └── 001_create_invoices.sql
├── Dockerfile
├── docker-compose.yml
└── .env.example
```

### composer.json

```json
{
    "name": "acme/billing-service",
    "require": {
        "php": ">=8.2",
        "slim/slim": "^4.12",
        "slim/psr7": "^1.6",
        "php-di/php-di": "^7.0",
        "monolog/monolog": "^3.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.10"
    },
    "autoload": {
        "psr-4": { "Acme\\Billing\\": "src/" }
    }
}
```

---

## 2. Dockerfile and Docker Compose

### .dockerignore — create before building (prevents .env leaking into image)

```
.env
.env.*
vendor/
tests/
*.md
.git/
docker-compose*.yml
```

### Dockerfile (multi-stage, production-ready)

```dockerfile
# Stage 1: Composer install
FROM composer:2.7 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Stage 2: Runtime image
FROM php:8.3-fpm-alpine

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis opcache

# OPcache for production
RUN echo "opcache.enable=1\nopcache.memory_consumption=128\n\
opcache.max_accelerated_files=10000\nopcache.validate_timestamps=0" \
    >> /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/billing-service
# Copy source first, then overlay vendor — keeps layer cache valid on code-only changes
COPY . .
COPY --from=composer /app/vendor ./vendor

EXPOSE 9000
CMD ["php-fpm"]
```

Note: `pcntl` is required for graceful SIGTERM handling in queue consumers.

### docker-compose.yml

```yaml
version: '3.9'

services:
  billing-api:
    build: .
    ports:
      - "8081:80"
    environment:
      - APP_ENV=production
      - DB_HOST=billing-db
      - DB_DATABASE=billing
      - DB_USERNAME=billing
      - DB_PASSWORD=${BILLING_DB_PASSWORD}
    depends_on:
      billing-db:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 10s
      timeout: 3s
      retries: 3

  billing-db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: billing
      MYSQL_USER: billing
      MYSQL_PASSWORD: ${BILLING_DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${BILLING_DB_ROOT_PASSWORD}
    volumes:
      - billing-db-data:/var/lib/mysql
      - ./database/migrations:/docker-entrypoint-initdb.d
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 3s
      retries: 10

volumes:
  billing-db-data:
```

---

## 3. HTTP API with Slim Framework

### public/index.php

```php
<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(require __DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware((bool) getenv('APP_DEBUG'), true, true);

require __DIR__ . '/../config/routes.php';

$app->run();
```

### config/routes.php

```php
<?php

use Acme\Billing\Infrastructure\Http\InvoiceController;
use Acme\Billing\Infrastructure\Http\HealthController;

$app->get('/health', [HealthController::class, 'check']);

$app->group('/api/v1', function ($group) {
    $group->post('/invoices', [InvoiceController::class, 'create']);
    $group->get('/invoices/{id}', [InvoiceController::class, 'show']);
    $group->post('/invoices/{id}/pay', [InvoiceController::class, 'pay']);
});
```

### Infrastructure/Http/InvoiceController.php

```php
<?php

namespace Acme\Billing\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Acme\Billing\Application\CreateInvoice\CreateInvoiceCommand;
use Acme\Billing\Application\CreateInvoice\CreateInvoiceHandler;

class InvoiceController
{
    public function __construct(
        private CreateInvoiceHandler $createHandler,
    ) {}

    public function create(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $command = new CreateInvoiceCommand(
            orderId: $body['order_id'] ?? throw new \InvalidArgumentException('order_id required'),
            amountCents: (int) ($body['amount_cents'] ?? throw new \InvalidArgumentException('amount_cents required')),
            currency: $body['currency'] ?? 'USD',
        );

        $invoice = $this->createHandler->handle($command);

        $response->getBody()->write(json_encode(['id' => (string) $invoice->getId()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }
}
```

---

## 4. Message Queue Consumer

Three non-negotiable additions beyond the basic loop:
1. `basic_qos` prefetch — prevents the consumer pulling all messages at once
2. `SIGTERM` graceful shutdown — prevents message loss on pod termination
3. Idempotent handler — at-least-once delivery means the same message may arrive twice

```php
<?php
// bin/consumer.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection(
    getenv('RABBITMQ_HOST'),
    (int) getenv('RABBITMQ_PORT'),
    getenv('RABBITMQ_USER'),
    getenv('RABBITMQ_PASSWORD'),
);

$channel = $connection->channel();
$channel->queue_declare('billing.payment', false, true, false, false);

// Pull one message at a time — prevents overwhelming the process
$channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

// Graceful shutdown on SIGTERM (Kubernetes pod termination, docker stop)
$running = true;
pcntl_signal(SIGTERM, function () use (&$running, $channel): void {
    $running = false;
    $channel->stopConsume();
});

$channel->basic_consume(
    queue: 'billing.payment',
    callback: function (AMQPMessage $msg) use ($handler): void {
        try {
            $payload = json_decode($msg->body, true, 512, JSON_THROW_ON_ERROR);
            $handler->handle(ProcessPaymentCommand::fromArray($payload));
            $msg->ack();
        } catch (\Throwable $e) {
            $msg->nack(requeue: false); // dead-letter queue after one attempt
            error_log($e->getMessage());
        }
    }
);

while ($running && $channel->is_consuming()) {
    $channel->wait(allowed_methods: null, non_blocking: false, timeout: 1.0);
    pcntl_signal_dispatch(); // check for pending signals
}

$channel->close();
$connection->close();
```

`pcntl` extension must be enabled in the Dockerfile (`docker-php-ext-install pcntl`).

---

## 5. Health Checks and Readiness Probes

```php
class HealthController
{
    public function __construct(private \PDO $pdo) {}

    public function check(Request $request, Response $response): Response
    {
        $checks = [];
        $status = 200;

        // Database check
        try {
            $this->pdo->query('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error: ' . $e->getMessage();
            $status = 503;
        }

        $body = ['status' => $status === 200 ? 'healthy' : 'unhealthy', 'checks' => $checks];
        $response->getBody()->write(json_encode($body));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
```

Kubernetes liveness vs readiness:
- `/health/live` — Is the process running? (No DB check — just return 200)
- `/health/ready` — Can the service take traffic? (Check DB, queue, external deps)

---

## 6. Circuit Breaker

**Warning:** PHP-FPM spawns a new process per request — in-memory state resets on every request.
Use Redis-backed state for a circuit breaker that actually works.

```php
class RedisCircuitBreaker
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $serviceName,
        private readonly int $threshold = 5,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function call(callable $fn): mixed
    {
        $failKey   = "circuit:{$this->serviceName}:failures";
        $openedKey = "circuit:{$this->serviceName}:opened_at";

        $failures = (int) ($this->redis->get($failKey) ?: 0);
        $openedAt = (float) ($this->redis->get($openedKey) ?: 0);
        $isOpen   = $failures >= $this->threshold;

        if ($isOpen && (microtime(true) - $openedAt) < $this->timeoutSeconds) {
            throw new \RuntimeException("Circuit open for {$this->serviceName}");
        }

        try {
            $result = $fn();
            $this->redis->del($failKey, $openedKey); // reset on success
            return $result;
        } catch (\Throwable $e) {
            $this->redis->incr($failKey);
            $this->redis->set($openedKey, microtime(true));
            throw $e;
        }
    }
}

// Usage
$breaker = new RedisCircuitBreaker($redis, 'stripe-api', threshold: 5, timeoutSeconds: 60);
$result  = $breaker->call(fn () => $this->stripeClient->charge($payload));
```

For retry logic without a custom breaker: `composer require php-http/retry-plugin`.

---

## 7. Observability: Logging, Metrics, Tracing

### Structured logging (Monolog)

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

$logger = new Logger('billing-service');
$handler = new StreamHandler('php://stdout', Logger::DEBUG);
$handler->setFormatter(new JsonFormatter());
$logger->pushHandler($handler);

// Log with context
$logger->info('Invoice created', [
    'invoice_id' => (string) $invoice->getId(),
    'amount'     => $invoice->getAmount()->toCents(),
    'trace_id'   => $_SERVER['HTTP_X_TRACE_ID'] ?? null,
]);
```

### Distributed tracing (OpenTelemetry)

```bash
composer require open-telemetry/sdk open-telemetry/exporter-otlp
```

```php
use OpenTelemetry\API\Globals;

$tracer = Globals::tracerProvider()->getTracer('billing-service');
$span = $tracer->spanBuilder('create-invoice')->startSpan();
$span->setAttribute('invoice.amount', $amountCents);

try {
    // ... business logic
    $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_OK);
} catch (\Exception $e) {
    $span->recordException($e);
    $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage());
    throw $e;
} finally {
    $span->end();
}
```

---

## 8. 12-Factor Configuration

Never hardcode config. Use environment variables for everything environment-specific.

```php
// config/settings.php
return [
    'db' => [
        'dsn'      => getenv('DB_DSN')      ?: 'mysql:host=localhost;dbname=billing',
        'username' => getenv('DB_USERNAME') ?: 'billing',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'stripe' => [
        'api_key'       => getenv('STRIPE_API_KEY')       ?: throw new \RuntimeException('STRIPE_API_KEY required'),
        'webhook_secret'=> getenv('STRIPE_WEBHOOK_SECRET') ?: throw new \RuntimeException('STRIPE_WEBHOOK_SECRET required'),
    ],
    'app' => [
        'debug' => (bool) getenv('APP_DEBUG'),
        'env'   => getenv('APP_ENV') ?: 'production',
    ],
];
```

### .env.example (commit this; never commit .env)

```dotenv
APP_ENV=local
APP_DEBUG=true

DB_DSN=mysql:host=billing-db;dbname=billing;charset=utf8mb4
DB_USERNAME=billing
DB_PASSWORD=secret

STRIPE_API_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=billing
RABBITMQ_PASSWORD=secret

OTLP_ENDPOINT=http://otel-collector:4318
```