---
name: php-microservice-build
description: Build a standalone PHP microservice from scratch — triggers on "create a PHP microservice", "scaffold a new service", "Dockerfile for PHP", "health check endpoint", "RabbitMQ consumer PHP", "Slim 4 service", "OpenTelemetry PHP", "12-factor PHP app", or as the implementation step after php-microservice-extraction.
---

# PHP Microservice Build

Implement a standalone PHP service: directory scaffold, HTTP API, queue consumer,
container setup, migrations, static analysis, and production-grade observability.

---

## Scaffold

```
{service-name}/
├── .dockerignore               ← must list .env, /vendor, /tests
├── composer.json
├── public/
│   └── index.php               # HTTP entry point
├── bin/
│   └── consumer.php            # Queue consumer (omit if HTTP-only)
├── src/
│   ├── Domain/                 # Pure PHP — no framework
│   ├── Application/            # Command/Query handlers
│   ├── Infrastructure/
│   │   ├── Persistence/        # DB adapters
│   │   └── Http/               # Controllers
│   └── Container.php           # DI wiring
├── config/
│   ├── settings.php
│   └── routes.php
├── database/
│   └── migrations/
│       └── 001_create_invoices.sql
├── tests/
├── Dockerfile
├── docker-compose.yml
└── .env.example                # commit this — never commit .env
```

**`.dockerignore`** — always create this before building the image:
```
.env
vendor/
tests/
*.md
.git/
```

`composer.json` baseline (PHP 8.2+):
```json
{
    "require": {
        "php": ">=8.2",
        "slim/slim": "^4.12",
        "slim/psr7": "^1.6",
        "php-di/php-di": "^7.0",
        "monolog/monolog": "^3.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.10",
        "friendsofphp/php-cs-fixer": "^3.0"
    },
    "autoload": {
        "psr-4": { "Acme\\{Service}\\": "src/" }
    },
    "scripts": {
        "test":    "phpunit",
        "analyse": "phpstan analyse src --level=8",
        "cs-fix":  "php-cs-fixer fix src --rules=@PSR12"
    }
}
```

---

## HTTP entry point (Slim 4)

```php
// public/index.php
declare(strict_types=1);
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$container = (new ContainerBuilder())
    ->addDefinitions(require __DIR__ . '/../config/container.php')
    ->build();

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware((bool) getenv('APP_DEBUG'), true, true);

require __DIR__ . '/../config/routes.php';
$app->run();
```

---

## Health check endpoints

Always expose both — wired to separate Kubernetes probes:

```php
class HealthController {
    public function __construct(private readonly \PDO $pdo) {}

    // Liveness — is the process running? Never check external deps here.
    public function live(Request $req, Response $res): Response {
        $res->getBody()->write(json_encode(['status' => 'ok']));
        return $res->withHeader('Content-Type', 'application/json');
    }

    // Readiness — can the service take traffic?
    public function ready(Request $req, Response $res): Response {
        $checks = [];
        $code = 200;
        try {
            $this->pdo->query('SELECT 1');
            $checks['db'] = 'ok';
        } catch (\Exception $e) {
            $checks['db'] = 'error';
            $code = 503;
        }
        $res->getBody()->write(json_encode([
            'status' => $code === 200 ? 'ready' : 'not ready',
            'checks' => $checks,
        ]));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($code);
    }
}
```

---

## Database migrations

Migrations must be run explicitly — never silently on app startup.

**Option A — Init container (Kubernetes):**
```yaml
initContainers:
  - name: migrate
    image: acme/billing-service:latest
    command: ["php", "bin/migrate.php"]
```

**Option B — Composer script run in CI/CD before deploy:**
```bash
composer run migrate
```

Simple migration runner:
```php
// bin/migrate.php
$pdo = new PDO(getenv('DB_DSN'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $file) {
    $pdo->exec(file_get_contents($file));
    echo "Applied: " . basename($file) . "\n";
}
```

---

## Queue consumer (RabbitMQ)

Three critical additions beyond the basic loop: `basic_qos` prefetch, graceful `SIGTERM` shutdown, and idempotent handlers.

```php
// bin/consumer.php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$conn    = new AMQPStreamConnection(
    getenv('RABBITMQ_HOST'), (int) getenv('RABBITMQ_PORT'),
    getenv('RABBITMQ_USER'), getenv('RABBITMQ_PASSWORD'),
);
$channel = $conn->channel();
$channel->queue_declare('billing.commands', false, true, false, false);

// Prefetch: pull one message at a time — prevents overwhelming the process
$channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

// Graceful shutdown on SIGTERM (Kubernetes pod termination)
$running = true;
pcntl_signal(SIGTERM, function () use (&$running, $channel): void {
    $running = false;
    $channel->stopConsume();
});

$channel->basic_consume(
    queue: 'billing.commands',
    callback: function ($msg) use ($handler): void {
        try {
            $payload = json_decode($msg->body, true, 512, JSON_THROW_ON_ERROR);
            $handler->handle(CreateInvoiceCommand::fromArray($payload));
            $msg->ack();
        } catch (\Throwable $e) {
            $msg->nack(requeue: false); // dead-letter after one attempt
            error_log($e->getMessage());
        }
    }
);

while ($running && $channel->is_consuming()) {
    $channel->wait(allowed_methods: null, non_blocking: false, timeout: 1.0);
    pcntl_signal_dispatch();
}

$channel->close();
$conn->close();
```

All handlers **must be idempotent** — at-least-once delivery means the same message may arrive twice.

---

## Circuit breaker for outbound calls

**Important:** PHP-FPM spawns a new process per request — an in-memory circuit breaker resets every request and will never actually open. Use Redis-backed state for real circuit breaking.

```php
class RedisCircuitBreaker {
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $key,
        private readonly int $threshold = 5,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function call(callable $fn): mixed {
        $failures  = (int) $this->redis->get("{$this->key}:failures");
        $openedAt  = (float) ($this->redis->get("{$this->key}:opened_at") ?: 0);
        $isOpen    = $failures >= $this->threshold;

        if ($isOpen && (microtime(true) - $openedAt) < $this->timeoutSeconds) {
            throw new \RuntimeException("Circuit open for {$this->key}");
        }

        try {
            $result = $fn();
            $this->redis->del("{$this->key}:failures");
            return $result;
        } catch (\Throwable $e) {
            $this->redis->incr("{$this->key}:failures");
            $this->redis->set("{$this->key}:opened_at", microtime(true));
            throw $e;
        }
    }
}
```

For simpler needs, use a battle-tested library: `composer require php-http/retry-plugin`.

---

## Structured logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

$logger  = new Logger('billing-service');
$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new JsonFormatter());
$logger->pushHandler($handler);

// Always include trace_id for distributed tracing correlation
$logger->info('Invoice created', [
    'invoice_id' => (string) $invoice->getId(),
    'amount'     => $invoice->getAmount()->toCents(),
    'trace_id'   => $_SERVER['HTTP_X_TRACE_ID'] ?? null,
]);
```

---

## 12-factor configuration

```php
// config/settings.php
return [
    'db' => [
        'dsn'      => getenv('DB_DSN')      ?: throw new \RuntimeException('DB_DSN required'),
        'username' => getenv('DB_USERNAME') ?: throw new \RuntimeException('DB_USERNAME required'),
        'password' => getenv('DB_PASSWORD') ?: throw new \RuntimeException('DB_PASSWORD required'),
    ],
    'app' => [
        'env'   => getenv('APP_ENV')   ?: 'production',
        'debug' => (bool) getenv('APP_DEBUG'),
    ],
];
```

`.env.example` — commit this; never commit `.env`:
```dotenv
APP_ENV=local
APP_DEBUG=true
DB_DSN=mysql:host=db;dbname=billing;charset=utf8mb4
DB_USERNAME=billing
DB_PASSWORD=secret
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=billing
RABBITMQ_PASSWORD=secret
REDIS_URL=redis://redis:6379
```

---

## CI pipeline

```yaml
# .github/workflows/ci.yml
name: CI
on: [push, pull_request]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction
      - run: composer run migrate         # run migrations against test DB
      - run: composer run test            # phpunit
      - run: composer run analyse         # phpstan level 8
      - run: composer run cs-fix -- --dry-run --diff  # php-cs-fixer
      - run: docker build -t service:test .  # verify image builds
```

---

## Done checklist

- [ ] `.dockerignore` created — `.env` and `vendor/` excluded from image
- [ ] Health endpoints `/health/live` and `/health/ready` implemented and tested
- [ ] Migrations runner implemented and wired into deploy pipeline
- [ ] Queue consumer has `basic_qos` prefetch and `SIGTERM` graceful shutdown
- [ ] Circuit breaker uses Redis-backed state (not in-memory)
- [ ] All handlers are idempotent
- [ ] Structured JSON logging with `trace_id`
- [ ] All secrets come from env vars — no hardcoded values
- [ ] phpunit passes
- [ ] phpstan level 8 passes
- [ ] CI pipeline runs on every PR

---

## Reference

→ `references/microservice-php.md` — complete multi-stage Dockerfile, docker-compose with
healthchecks, full Slim 4 controller example, OpenTelemetry distributed tracing setup,
and Kubernetes liveness/readiness probe configuration.