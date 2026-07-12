# PHP Module Structure Reference

## Table of Contents
1. [Framework-Agnostic Module Skeleton](#1-framework-agnostic-module-skeleton)
2. [Laravel-Specific Patterns](#2-laravel-specific-patterns)
3. [Symfony-Specific Patterns](#3-symfony-specific-patterns)
4. [Enforcing Boundaries with Composer](#4-enforcing-boundaries-with-composer)
5. [Cross-Module Communication Patterns](#5-cross-module-communication-patterns)
6. [SharedKernel Guidelines](#6-sharedkernel-guidelines)

---

## 1. Framework-Agnostic Module Skeleton

```php
src/Modules/Billing/
├── Domain/
│   ├── Invoice.php                    # Aggregate root / entity
│   ├── InvoiceId.php                  # Value object
│   ├── Money.php                      # Value object
│   ├── InvoiceRepository.php          # Interface (port)
│   ├── PaymentGateway.php             # Interface (port)
│   └── Events/
│       ├── InvoiceCreated.php
│       └── InvoicePaid.php
├── Application/
│   ├── CreateInvoice/
│   │   ├── CreateInvoiceCommand.php   # DTO / command
│   │   └── CreateInvoiceHandler.php  # Use-case handler
│   ├── ProcessPayment/
│   │   ├── ProcessPaymentCommand.php
│   │   └── ProcessPaymentHandler.php
│   └── GetInvoice/
│       ├── GetInvoiceQuery.php
│       └── GetInvoiceHandler.php
├── Infrastructure/
│   ├── Persistence/
│   │   └── EloquentInvoiceRepository.php  # implements Domain\InvoiceRepository
│   ├── Payment/
│   │   └── StripeGateway.php              # implements Domain\PaymentGateway
│   └── Http/
│       └── StripeWebhookController.php
└── BillingServiceProvider.php             # Binds interfaces → implementations
```

### Domain entity example (pure PHP, no framework dependency)

```php
<?php

namespace App\Modules\Billing\Domain;

use App\SharedKernel\Domain\AggregateRoot;

final class Invoice extends AggregateRoot
{
    private InvoiceId $id;
    private Money $amount;
    private InvoiceStatus $status;

    private function __construct(InvoiceId $id, Money $amount)
    {
        $this->id = $id;
        $this->amount = $amount;
        $this->status = InvoiceStatus::DRAFT;
    }

    public static function create(InvoiceId $id, Money $amount): self
    {
        $invoice = new self($id, $amount);
        $invoice->recordEvent(new Events\InvoiceCreated($id, $amount));
        return $invoice;
    }

    public function markAsPaid(): void
    {
        if ($this->status !== InvoiceStatus::ISSUED) {
            throw new \DomainException('Only issued invoices can be paid.');
        }
        $this->status = InvoiceStatus::PAID;
        $this->recordEvent(new Events\InvoicePaid($this->id));
    }

    public function getId(): InvoiceId { return $this->id; }
    public function getAmount(): Money { return $this->amount; }
    public function getStatus(): InvoiceStatus { return $this->status; }
}
```

---

## 2. Laravel-Specific Patterns

### Module Service Provider

```php
<?php

namespace App\Modules\Billing;

use Illuminate\Support\ServiceProvider;
use App\Modules\Billing\Domain\InvoiceRepository;
use App\Modules\Billing\Domain\PaymentGateway;
use App\Modules\Billing\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Modules\Billing\Infrastructure\Payment\StripeGateway;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->bind(PaymentGateway::class, StripeGateway::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Infrastructure/Persistence/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/Infrastructure/Http/routes.php');
    }
}
```

Register in `config/app.php`:
```php
'providers' => [
    // ...
    App\Modules\Billing\BillingServiceProvider::class,
    App\Modules\Orders\OrdersServiceProvider::class,
    App\Modules\Notifications\NotificationsServiceProvider::class,
],
```

### Keeping Eloquent out of the Domain

**Anti-pattern** — Eloquent model as domain entity:
```php
// WRONG: Billing\Domain\Invoice extends Model
// This ties domain logic to the database ORM
```

**Pattern 1 — Eloquent as persistence model only (recommended)**:
```php
// Infrastructure/Persistence/InvoiceModel.php (extends Model)
// Domain/Invoice.php (pure PHP class)
// Infrastructure/Persistence/EloquentInvoiceRepository.php maps between them
```

**Pattern 2 — Active Record with domain discipline** (pragmatic for smaller apps):
Keep Eloquent models but ban business logic from them. Put all business rules in
Domain Service classes. The model is a dumb data-bag with query scopes only.

### Laravel module routes

```php
// src/Modules/Billing/Infrastructure/Http/routes.php
use Illuminate\Support\Facades\Route;
use App\Modules\Billing\Infrastructure\Http\InvoiceController;

Route::middleware(['auth:api'])->prefix('billing')->group(function () {
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::post('/invoices/{id}/pay', [InvoiceController::class, 'pay']);
});
```

---

## 3. Symfony-Specific Patterns

### Bundle-per-module (classic, < Symfony 4)
Each module is a full Symfony Bundle (`BillingBundle`). Heavy; avoid for new code.

### Kernel config approach (Symfony 4+, preferred)

Directory layout mirrors the framework-agnostic skeleton above.
Wire in `config/services.yaml`:

```yaml
# config/services.yaml
services:
    App\Modules\Billing\:
        resource: '../src/Modules/Billing/'
        exclude:
            - '../src/Modules/Billing/Domain/'    # Domain classes manually wired
            - '../src/Modules/Billing/**/*Command.php'
            - '../src/Modules/Billing/**/*Event.php'

    App\Modules\Billing\Domain\InvoiceRepository:
        class: App\Modules\Billing\Infrastructure\Persistence\DoctrineInvoiceRepository

    App\Modules\Billing\Domain\PaymentGateway:
        class: App\Modules\Billing\Infrastructure\Payment\StripeGateway
        arguments:
            $apiKey: '%env(STRIPE_API_KEY)%'
```

Symfony Messenger as command/event bus:
```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        buses:
            command.bus:
                middleware: ['validation']
            event.bus:
                default_middleware: allow_no_handlers
        routing:
            'App\Modules\Billing\Application\CreateInvoice\CreateInvoiceCommand': command.bus
            'App\Modules\Billing\Domain\Events\InvoicePaid': event.bus
```

---

## 4. Enforcing Boundaries with Composer

For **hard enforcement** of module boundaries (build will fail on illegal cross-module imports),
use Composer path repositories to make each module a separate package:

```json
// billing-module/composer.json
{
    "name": "acme/billing",
    "autoload": {
        "psr-4": { "App\\Modules\\Billing\\": "src/" }
    },
    "require": {
        "acme/shared-kernel": "*"
    }
}
```

```json
// Root composer.json
{
    "repositories": [
        { "type": "path", "url": "modules/billing" },
        { "type": "path", "url": "modules/orders" },
        { "type": "path", "url": "modules/shared-kernel" }
    ],
    "require": {
        "acme/billing": "*",
        "acme/orders": "*"
    }
}
```

Billing cannot import Orders classes because it's not in billing's `composer.json` requirements.

Lighter alternative: **deptrac** (static analysis tool for layer/module boundaries):

```yaml
# deptrac.yaml
layers:
    - name: Billing
      collectors:
          - type: className
            regex: ^App\\Modules\\Billing\\.*
    - name: Orders
      collectors:
          - type: className
            regex: ^App\\Modules\\Orders\\.*
    - name: SharedKernel
      collectors:
          - type: className
            regex: ^App\\SharedKernel\\.*
ruleset:
    Billing:
        - SharedKernel          # Billing may only depend on SharedKernel
    Orders:
        - SharedKernel
```

Run: `vendor/bin/deptrac analyse` — fails CI if a module imports from a sibling.

---

## CI Pipeline

All boundary and quality tools must run on every PR:

```yaml
# .github/workflows/quality.yml
name: Quality
on: [push, pull_request]
jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction
      - run: ./vendor/bin/phpunit --testdox
      - run: ./vendor/bin/deptrac analyse --no-progress
      - run: ./vendor/bin/php-cs-fixer fix --dry-run --diff src/
      - run: ./vendor/bin/phpstan analyse src --level=8
```

All four checks must be green before merging. deptrac violations are treated as build failures,
not warnings — the boundary only holds if it is enforced in CI.

---

## 5. Cross-Module Communication Patterns

### Option A — Shared interface in SharedKernel

```php
// SharedKernel/Domain/NotificationSender.php
interface NotificationSender
{
    public function sendOrderConfirmation(string $to, OrderId $orderId): void;
}

// Orders module uses it:
class PlaceOrderHandler
{
    public function __construct(private NotificationSender $notifications) {}

    public function handle(PlaceOrderCommand $cmd): void
    {
        // ... place order logic ...
        $this->notifications->sendOrderConfirmation($cmd->email, $orderId);
    }
}

// Notifications module implements it:
class NotificationService implements NotificationSender { ... }
```

### Option B — Domain events (preferred for loose coupling)

```php
// Orders/Domain/Events/OrderPlaced.php
final class OrderPlaced implements DomainEvent
{
    public function __construct(
        public readonly OrderId $orderId,
        public readonly string $customerEmail,
        public readonly Money $total,
    ) {}
}

// Notifications/Application/SendOrderConfirmationOnOrderPlaced.php
final class SendOrderConfirmationOnOrderPlaced
{
    public function __invoke(OrderPlaced $event): void
    {
        $this->mailer->sendOrderConfirmation($event->customerEmail, $event->orderId);
    }
}
```

Register the listener in Notifications' ServiceProvider — Orders never imports from Notifications.

---

## 6. SharedKernel Guidelines

**Should be in SharedKernel:**
- `AggregateRoot` base class
- `DomainEvent` interface
- `EventBus` interface
- Common value objects used everywhere: `Money`, `Email`, `Uuid`
- `Repository` base interface (generic CRUD contract)

**Should NOT be in SharedKernel:**
- Business logic (even "generic" validation rules)
- Eloquent models / Doctrine entities
- Application services
- Controllers
- Any concept that belongs to one specific domain

SharedKernel must be dependency of all modules — keep it tiny and stable.
If SharedKernel changes frequently, it means business logic is leaking into it.