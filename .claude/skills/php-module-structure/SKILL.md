---
name: php-module-structure
description: Restructure a PHP monolith into isolated Domain/Application/Infrastructure modules — triggers on "restructure into modules", "clean architecture in Laravel/Symfony", "introduce hexagonal architecture", "how do I organise my src folder", "too much coupling between classes", or after php-domain-discovery.
---

# PHP Internal Module Structure

Restructure a PHP monolith so each domain lives in an isolated module with strict
`Domain / Application / Infrastructure` layering. This is the prerequisite before any
microservice extraction — do not skip it.

---

## Target directory layout

```
src/
├── Modules/
│   └── {Domain}/                        ← one folder per bounded context
│       ├── Domain/                      ← pure PHP, zero framework imports
│       │   ├── {Entity}.php
│       │   ├── {ValueObject}.php
│       │   ├── {Repository}.php         ← interface (port)
│       │   └── Events/
│       │       └── {DomainEvent}.php
│       ├── Application/                 ← use-case handlers, commands, queries
│       │   └── {UseCase}/
│       │       ├── {UseCase}Command.php
│       │       └── {UseCase}Handler.php
│       ├── Infrastructure/              ← DB, HTTP clients, queue adapters
│       │   ├── Persistence/
│       │   └── Http/
│       └── {Domain}ServiceProvider.php  ← binds interfaces → implementations
├── SharedKernel/
│   ├── Domain/
│   │   ├── AggregateRoot.php
│   │   └── DomainEvent.php
│   └── Infrastructure/
│       └── EventBus.php
└── Bootstrap/                           ← framework glue only
```

---

## Layer rules

| Layer | May import from | Must NOT import from |
|-------|----------------|----------------------|
| `Domain/` | SharedKernel only | Application, Infrastructure, any framework, other modules |
| `Application/` | Domain, SharedKernel | Infrastructure directly; other modules' Domain/Application |
| `Infrastructure/` | Domain, Application, framework libs | Other modules' Domain, Application, or Infrastructure |

Cross-module calls go through **domain events** or **SharedKernel interfaces** only —
never direct class instantiation across module boundaries.

---

## Steps

### 1. Create the folder scaffold

For each domain from `php-domain-discovery`, create the three layers:
```bash
mkdir -p src/Modules/{Domain}/{Domain,Application,Infrastructure}
```

### 2. Move entities and value objects into `Domain/` — incrementally

Do not delete the original class on day one. Use an alias to avoid breaking the app
while the migration is in progress:

```php
// Legacy location — keep temporarily during migration
// app/Models/Invoice.php
class Invoice extends Model {
    // mark as deprecated so the team knows it is being replaced
}
```

Strip all framework imports from the new domain class. A domain entity must be
testable with `phpunit` and zero framework bootstrap:

```php
// BEFORE — Eloquent god-model (framework + side effects in domain)
class Invoice extends Model {
    public function markAsPaid(): void {
        $this->status = 'paid';
        $this->save();                                           // DB call — wrong
        Mail::to($this->email)->send(new InvoicePaidMail($this)); // side effect — wrong
    }
}

// AFTER — pure domain entity (PHP 8.2+, readonly where applicable)
final class Invoice {
    private InvoiceStatus $status;  // enum, not string

    public function markAsPaid(): void {
        if ($this->status !== InvoiceStatus::ISSUED) {
            throw new \DomainException('Only issued invoices can be paid.');
        }
        $this->status = InvoiceStatus::PAID;
        $this->recordEvent(new Events\InvoicePaid($this->id));
    }
}

// PHP 8.1+ enum replaces string constants
enum InvoiceStatus {
    case DRAFT;
    case ISSUED;
    case PAID;
    case CANCELLED;
}
```

### 3. Define repository and service interfaces in `Domain/`

```php
interface InvoiceRepository {
    public function findById(InvoiceId $id): ?Invoice;
    public function save(Invoice $invoice): void;
}
```

### 4. Move use-cases into `Application/`

One folder per use-case with a Command/Query DTO and a Handler.
Handlers depend on domain interfaces only — never on concrete infrastructure:

```php
final class CreateInvoiceHandler {
    public function __construct(
        private readonly InvoiceRepository $invoices,  // interface injected
        private readonly EventBus $events,
    ) {}

    public function handle(CreateInvoiceCommand $cmd): Invoice {
        $invoice = Invoice::create(
            InvoiceId::generate(),
            new Money($cmd->amountCents, $cmd->currency),
        );
        $this->invoices->save($invoice);
        $this->events->dispatch(...$invoice->releaseEvents());
        return $invoice;
    }
}
```

### 5. Move DB / HTTP / queue code into `Infrastructure/`

Implement domain interfaces here. Map between persistence models and domain entities
using a dedicated mapper — never return Eloquent models from repository methods:

```php
final class EloquentInvoiceRepository implements InvoiceRepository {
    public function findById(InvoiceId $id): ?Invoice {
        $model = InvoiceModel::find((string) $id);
        return $model ? InvoiceMapper::toDomain($model) : null;
    }
    public function save(Invoice $invoice): void {
        InvoiceModel::updateOrCreate(
            ['id' => (string) $invoice->getId()],
            InvoiceMapper::toArray($invoice),
        );
    }
}
```

### 6. Wire with a ServiceProvider / DI container

**Laravel:**
```php
class BillingServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->app->bind(InvoiceRepository::class, EloquentInvoiceRepository::class);
    }
    public function boot(): void {
        $this->loadMigrationsFrom(__DIR__ . '/Infrastructure/Persistence/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/Infrastructure/Http/routes.php');
    }
}
```

**Symfony** — see `references/module-structure-php.md` § 3.

### 7. Write tests for each domain class

Domain entities are pure PHP — test them with zero framework overhead:

```php
class InvoiceTest extends TestCase {
    public function test_paid_invoice_cannot_be_paid_again(): void {
        $invoice = Invoice::create(InvoiceId::generate(), new Money(1000, 'USD'));
        $invoice->issue();
        $invoice->markAsPaid();

        $this->expectException(\DomainException::class);
        $invoice->markAsPaid();  // second call must throw
    }
}
```

Run after every migration step: `./vendor/bin/phpunit --testdox`

### 8. Enforce boundaries in CI

**deptrac** — fails the build on illegal cross-module imports.
List every module — unlisted modules are silently ignored:

```bash
composer require --dev qossmic/deptrac
```

```yaml
# deptrac.yaml
layers:
  - name: Billing
    collectors: [{ type: className, regex: ^App\\Modules\\Billing\\.* }]
  - name: Orders
    collectors: [{ type: className, regex: ^App\\Modules\\Orders\\.* }]
  - name: Notifications
    collectors: [{ type: className, regex: ^App\\Modules\\Notifications\\.* }]
  - name: SharedKernel
    collectors: [{ type: className, regex: ^App\\SharedKernel\\.* }]
ruleset:
  Billing:       [SharedKernel]
  Orders:        [SharedKernel]
  Notifications: [SharedKernel]
  # add every module — missing entries are not checked
```

**php-cs-fixer** — enforce PSR-12 code style across all modules:
```bash
composer require --dev friendsofphp/php-cs-fixer
./vendor/bin/php-cs-fixer fix src/Modules --rules=@PSR12
```

**CI pipeline** — add both tools (see `references/module-structure-php.md` § CI):
```yaml
# .github/workflows/quality.yml
- run: ./vendor/bin/phpunit
- run: ./vendor/bin/deptrac analyse
- run: ./vendor/bin/php-cs-fixer fix --dry-run --diff
- run: ./vendor/bin/phpstan analyse src --level=8
```

---

## SharedKernel — what belongs here

**In:** `AggregateRoot`, `DomainEvent` interface, `EventBus` interface,
universal value objects (`Money`, `Email`, `Uuid`).

**Out:** business logic, Eloquent models, framework classes, module-specific concepts.

SharedKernel changing frequently = domain logic leaking into it. Move it back.

---

## Done checklist

- [ ] Every domain has `Domain/`, `Application/`, `Infrastructure/` folders
- [ ] No framework imports in any `Domain/` class
- [ ] All domain entities covered by unit tests (`phpunit` passes with no bootstrap)
- [ ] deptrac passes with zero violations
- [ ] php-cs-fixer passes
- [ ] phpstan level 8 passes
- [ ] CI pipeline runs all four tools on every PR
- [ ] Ready to proceed to `php-microservice-extraction`

---

## Reference

→ `references/module-structure-php.md` — full Laravel ServiceProvider, Symfony DI YAML,
Eloquent-free entity patterns, deptrac config, cross-module event wiring, Composer
path-repository approach for hard boundary enforcement, CI pipeline examples.