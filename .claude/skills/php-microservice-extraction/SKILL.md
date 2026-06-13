---
name: php-microservice-extraction
description: Extract a PHP domain module into a standalone microservice — triggers on "extract X into a microservice", "strangler fig in PHP", "split off the billing/notification service", "move X to its own service", "how do we do this without downtime", or after php-module-structure.
---

# PHP Microservice Extraction

Extract an isolated PHP domain module into a standalone service, safely, without downtime.
Assumes the module already has clean `Domain / Application / Infrastructure` boundaries
(see `php-module-structure`). If it doesn't — do that first.

---

## Step 1 — Choose the extraction candidate

Score each domain on the criteria below. Pick the highest total — if tied, prefer lower risk.

| Criterion | 2 pts | 1 pt | 0 pts |
|-----------|-------|------|-------|
| **Coupling** | No reads from other domains | Reads but never writes across boundaries | Shares DB transactions with siblings |
| **Scaling** | Clearly different traffic profile | Slightly different | Same load pattern as monolith |
| **Contract stability** | API unchanged for 6+ months | Minor changes only | Frequently changing interface |
| **Blast radius** | Failure degrades one feature | Failure degrades a flow | Failure kills checkout / auth |
| **Data independence** | Owns all its tables exclusively | Shares read replicas only | Writes to shared tables |

Best first extractions: `Notifications`, `Reporting`, `Media/Upload`, `Billing`.
Avoid extracting `Orders` if it shares DB transactions with `Inventory`.

**Tiebreaker:** when scores are equal, extract the domain that causes the most developer pain today.

---

## Step 2 — Define the service contract first

Write the contract **before** writing any service code. Get it reviewed by all teams before implementing.

**HTTP API** → write `openapi.yaml`:
```yaml
openapi: 3.1.0
info:
  title: Billing Service
  version: 1.0.0
paths:
  /invoices:
    post:
      summary: Create invoice
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [order_id, amount_cents, currency]
              properties:
                order_id:     { type: string, format: uuid }
                amount_cents: { type: integer, minimum: 1 }
                currency:     { type: string, example: USD }
      responses:
        '201':
          content:
            application/json:
              schema:
                properties:
                  id: { type: string, format: uuid }
```

**Async events** → write a JSON Schema per event type.
**gRPC** → write the `.proto` file first.

---

## Step 3 — Strangler Fig (three phases)

### Phase 1 — Introduce the port in the monolith

Confirm there is exactly one interface covering everything the monolith needs from this domain.
All callers must use the interface, not the concrete class. If they don't — refactor first.

```php
// Already exists from php-module-structure:
interface NotificationService {
    public function sendOrderConfirmation(string $to, string $orderId): void;
}
class InProcessNotificationService implements NotificationService { ... }
```

### Phase 2 — Deploy the new service and add an HTTP adapter

Build the new service with `php-microservice-build`. Then add an HTTP adapter in the monolith.

The new service URL must come from config, not be hardcoded:
```php
// config/services.php
'notification_service_url' => env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8080'),
```

```php
class HttpNotificationService implements NotificationService {
    public function __construct(
        private readonly \GuzzleHttp\Client $http,
        private readonly string $baseUrl,
    ) {}

    public function sendOrderConfirmation(string $to, string $orderId): void {
        $this->http->post($this->baseUrl . '/notifications/order-confirmation', [
            'json' => ['to' => $to, 'order_id' => $orderId],
        ]);
    }
}
```

Toggle via env variable:
```php
// AppServiceProvider
$this->app->bind(
    NotificationService::class,
    match(config('services.notification_driver')) {
        'http'  => HttpNotificationService::class,
        default => InProcessNotificationService::class,
    }
);
```

```dotenv
NOTIFICATION_DRIVER=http            # staging
NOTIFICATION_SERVICE_URL=http://notification-service:8080
# NOTIFICATION_DRIVER=in_process    # rollback: revert this one variable
```

**Shadow mode** — prerequisite: the new service handler must be idempotent before enabling shadow mode.
Shadow mode only makes sense when a double-call is safe:

```php
// Safe only if sendOrderConfirmation is idempotent on the new service
try {
    $this->httpService->sendOrderConfirmation($to, $orderId);
} catch (\Throwable $e) {
    $this->logger->warning('Shadow: new service failed — falling back', [
        'error'    => $e->getMessage(),
        'order_id' => $orderId,
    ]);
    $this->inProcessService->sendOrderConfirmation($to, $orderId);
    // NOTE: if the HTTP call partially succeeded (email sent, response timed out),
    // the in-process fallback will send a duplicate. Confirm idempotency first.
}
```

### Phase 3 — Cut over and clean up

**Rollback plan** (keep until Phase 3 is fully stable):
```dotenv
NOTIFICATION_DRIVER=in_process   # instant rollback — one env var change, no deploy
```

After several weeks stable on production with no rollbacks:
1. Delete `InProcessNotificationService`
2. Remove the toggle — `HttpNotificationService` is the only binding
3. Remove `NOTIFICATION_DRIVER` from config
4. Proceed to data ownership migration (Step 4)
5. Drop legacy tables from the monolith's schema

---

## Step 4 — Data ownership migration

Full SQL scripts and PHP migration code are in `references/data-ownership.md`.

Three phases in brief:

```
Phase A — Shared DB (weeks only)
  Both apps connect to the same DB, different DB users.
  Monolith grants new service READ + WRITE on its tables.

Phase B — Schema-per-service (same DB server)
  New service owns its own schema (billing.*).
  Monolith loses WRITE grants. Sync via domain events.

Phase C — Separate DB (target state)
  New service has its own DB process.
  No shared tables. All cross-service data via events or API.
```

Rules throughout all phases:
- One service writes; others read via events or the owning service's API
- No FK constraints across service boundaries — reference by UUID only
- Never query another service's DB directly from application code

---

## Step 5 — Anti-patterns to avoid

**Distributed monolith** — services call each other synchronously in a chain.
Fix: async messaging; services must degrade gracefully without peers.

**Chatty interface** — 50 HTTP calls to render one page.
Fix: coarse-grained APIs; event-carried state transfer for read data.

**Premature extraction** — splitting before internal boundaries are clean.
Fix: always complete `php-module-structure` first.

**Missing idempotency** — retry delivers a message twice, customer charged twice.
Fix: every handler checks for prior execution before acting:
```php
public function handle(CreateInvoiceCommand $cmd): void {
    if ($this->invoices->existsForOrder($cmd->orderId)) {
        return; // already created — safe to call twice
    }
    // ... create invoice
}
```

**No rollback plan** — cutting over with no way back.
Fix: always keep the feature flag and the in-process implementation until Phase 3 is proven stable.

---

## Done checklist

- [ ] Extraction candidate chosen and scored
- [ ] Service contract written (OpenAPI / event schema / proto) and reviewed
- [ ] Port interface verified — all callers use the interface
- [ ] New service built and deployed (see `php-microservice-build`)
- [ ] HTTP adapter added to monolith, wired via feature flag
- [ ] Shadow mode tested on staging with idempotency confirmed
- [ ] Rollback env var documented and tested
- [ ] Traffic cut over on production, stable for 2+ weeks
- [ ] Old in-process code deleted
- [ ] Data ownership migration plan documented
- [ ] Legacy tables dropped after data migration complete

---

## Reference

→ `references/extraction-patterns.md` — Strangler Fig walkthrough, Anti-Corruption Layer,
Event-Carried State Transfer, Saga patterns, feature flag strategies, pitfall deep-dives.

→ `references/data-ownership.md` — SQL migration scripts, PHP data-copy script,
zero-downtime Expand–Contract migration, parity verification.