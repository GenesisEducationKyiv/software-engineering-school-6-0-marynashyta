# Microservice Extraction Patterns Reference

## Table of Contents
1. [Strangler Fig Pattern](#1-strangler-fig-pattern)
2. [Anti-Corruption Layer (ACL)](#2-anti-corruption-layer-acl)
3. [Event-Carried State Transfer](#3-event-carried-state-transfer)
4. [Saga Pattern for Distributed Transactions](#4-saga-pattern-for-distributed-transactions)
5. [Feature Flags for Gradual Cutover](#5-feature-flags-for-gradual-cutover)
6. [Common Pitfalls](#6-common-pitfalls)

---

## 1. Strangler Fig Pattern

The safest extraction strategy: grow the new service around the old code like a strangler fig
tree, routing traffic progressively until the old code can be deleted.

### Phase 1 — Introduce the port (interface) in the monolith

```php
// Monolith: define what the Notifications domain provides
// SharedKernel or Orders module defines this interface:

interface NotificationService
{
    public function sendOrderConfirmation(string $to, string $orderId): void;
    public function sendPasswordReset(string $to, string $token): void;
}

// The existing implementation lives inside the monolith:
class InProcessNotificationService implements NotificationService
{
    public function sendOrderConfirmation(string $to, string $orderId): void
    {
        // existing Mailables / SwiftMailer code
    }
}
```

All callers already use the interface, not the concrete class.

### Phase 2 — Stand up the new service (see microservice-php.md)

### Phase 3 — Add an HTTP adapter in the monolith

```php
class HttpNotificationService implements NotificationService
{
    public function __construct(private \GuzzleHttp\Client $http) {}

    public function sendOrderConfirmation(string $to, string $orderId): void
    {
        $this->http->post('/notifications/order-confirmation', [
            'json' => ['to' => $to, 'order_id' => $orderId],
        ]);
    }
}
```

### Phase 4 — Toggle via config / feature flag

```php
// config/services.php (Laravel)
'notification_driver' => env('NOTIFICATION_DRIVER', 'in_process'),

// AppServiceProvider
$driver = config('services.notification_driver');
if ($driver === 'http') {
    $this->app->bind(NotificationService::class, HttpNotificationService::class);
} else {
    $this->app->bind(NotificationService::class, InProcessNotificationService::class);
}
```

Set `NOTIFICATION_DRIVER=http` in `.env` on a staging environment first.

### Phase 5 — Delete the old code

After several weeks with `NOTIFICATION_DRIVER=http` stable in production:
1. Delete `InProcessNotificationService`
2. Remove the toggle — `HttpNotificationService` is the only implementation
3. Drop the old notification tables from the monolith's DB (after migrating data)

---

## 2. Anti-Corruption Layer (ACL)

When the new service has a different domain model than the monolith, use an ACL to translate.

```
Monolith                      ACL                         New Service
─────────                     ───                         ───────────
LegacyOrder      →    OrderTranslator    →    OrderEvent
  .customer_id         maps legacy              .customerId (UUID)
  (int DB id)          int → UUID               .lineItems (typed)
  .items (JSON)
```

```php
class OrderTranslator
{
    public function toNewServiceEvent(LegacyOrder $order): OrderPlacedEvent
    {
        return new OrderPlacedEvent(
            orderId: Uuid::fromInt($order->id),  // translate int ID to UUID
            customerId: $this->customerIdMap->resolve($order->customer_id),
            lineItems: $this->parseJsonItems($order->items),
        );
    }
}
```

The ACL lives in the monolith's Infrastructure layer for the domain being extracted.
It is temporary — once extraction is complete, the monolith stops producing the legacy format.

---

## 3. Event-Carried State Transfer

When two services need shared data (e.g., the Billing service needs Customer names), instead of
making synchronous queries to the monolith, the Billing service maintains its own read-only copy
of Customer data, kept up-to-date via domain events.

```
Identity Service                        Billing Service
────────────────                        ───────────────
  Publishes:                              Subscribes:
  CustomerRegistered  ───────────────►   stores {id, name, email}
  CustomerUpdated     ───────────────►   updates local copy
  CustomerDeleted     ───────────────►   marks as deleted
```

```php
// Billing service: consumer
class CustomerProjection
{
    public function onCustomerRegistered(CustomerRegistered $event): void
    {
        $this->db->insert('billing_customers', [
            'id'    => $event->customerId,
            'name'  => $event->name,
            'email' => $event->email,
        ]);
    }
}
```

**Trade-off**: eventual consistency. Billing may read slightly stale customer data for milliseconds.
Acceptable for most billing workflows; unacceptable for security-critical reads.

---

## 4. Saga Pattern for Distributed Transactions

Use sagas when an operation spans multiple services and needs rollback on failure.

### Choreography-based saga (event-driven)

```
Orders Service        Billing Service         Inventory Service
──────────────        ───────────────         ─────────────────
OrderPlaced ──────►  ChargeCustomer
                     PaymentSucceeded ──────►  ReserveStock
                                               StockReserved  (→ complete)
                     PaymentFailed   ──────►  (no action needed)
                                               StockInsufficient ──► CancelOrder
```

Each service reacts to events and emits its own. No central coordinator.

### Orchestration-based saga (explicit state machine)

```php
class PlaceOrderSaga
{
    // States: STARTED → PAYMENT_PENDING → STOCK_PENDING → COMPLETED | COMPENSATING | FAILED

    public function start(OrderId $orderId): void
    {
        $this->commandBus->dispatch(new ChargeCustomer($orderId, $this->getAmount($orderId)));
        $this->updateState($orderId, SagaState::PAYMENT_PENDING);
    }

    public function onPaymentSucceeded(PaymentSucceeded $event): void
    {
        $this->commandBus->dispatch(new ReserveStock($event->orderId, $this->getItems($event->orderId)));
        $this->updateState($event->orderId, SagaState::STOCK_PENDING);
    }

    public function onPaymentFailed(PaymentFailed $event): void
    {
        $this->commandBus->dispatch(new CancelOrder($event->orderId, 'Payment failed'));
        $this->updateState($event->orderId, SagaState::FAILED);
    }

    // compensating transactions on failure
    public function onStockInsufficient(StockInsufficient $event): void
    {
        $this->commandBus->dispatch(new RefundPayment($event->orderId));
        $this->commandBus->dispatch(new CancelOrder($event->orderId, 'No stock'));
        $this->updateState($event->orderId, SagaState::FAILED);
    }
}
```

Use orchestration when you need explicit visibility into saga state (auditing, retries, timeouts).
Use choreography for simpler flows with 2–3 participants.

---

## 5. Feature Flags for Gradual Cutover

Never do a big-bang cutover. Use flags to gradually shift traffic.

```php
// Simple in-code flag (no external dependency)
if (config('features.use_notification_microservice')) {
    return $this->app->make(HttpNotificationService::class);
}
return $this->app->make(InProcessNotificationService::class);
```

For canary releases (1% → 10% → 50% → 100%):
```php
// Flag based on user ID modulo
$useNewService = ($userId % 100) < config('features.notification_microservice_pct');
```

Shadow mode (run both, compare results, don't fail on new service errors):
```php
try {
    $newServiceResult = $this->httpNotifications->send($to, $message);
    $this->compareWithLegacy($legacyResult, $newServiceResult);
} catch (\Exception $e) {
    $this->logger->warning('New notification service failed', ['error' => $e->getMessage()]);
    // Don't rethrow — legacy result is what the caller gets
}
```

---

## 6. Common Pitfalls

### Distributed Monolith
You've split into services but they call each other synchronously for every operation.
Any service going down cascades to all services.

**Signs**:
- Microservices share a database
- Services call each other in a chain (A → B → C → D) for a single user request
- You can't deploy Service A without deploying Service B

**Fix**: Use async messaging. Services should be able to operate (degraded) even if a peer is down.

### Chatty interfaces
Service A makes 50 HTTP calls to Service B to render one page.

**Fix**: Design coarse-grained APIs. One call returns all needed data. Use event-carried state
transfer so services don't need to query peers at read time.

### Premature extraction
Splitting before you understand the domain model. You'll end up with the wrong service boundaries
and face a very expensive re-split.

**Fix**: Modularise the monolith first (Step 3 of the main skill). Run it in production as a
well-modularised monolith for at least 1–2 release cycles before extracting.

### No service contract (API-first skipped)
Teams code against assumptions about each other's API and discover mismatches in integration.

**Fix**: Write OpenAPI spec or event schema first, code-review it with all teams, then implement.
Generate client/server stubs from the spec.

### Missing idempotency
Message-queue consumers or HTTP retries call your handler twice; you charge a customer twice.

**Fix**: Every command/event handler must be idempotent.
```php
public function handle(CreateInvoiceCommand $cmd): void
{
    if ($this->invoiceRepo->existsForOrder($cmd->orderId)) {
        return; // already created — idempotent
    }
    // ...create invoice
}
```