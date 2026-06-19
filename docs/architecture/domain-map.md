# Domain Map — GitHub Release Notification API

Generated: 2026-06-19
Branch: `hw-7-message-bus`

---

## 1. Domain List

### Subscription
The core domain. Owns the full subscription lifecycle: create, confirm, unsubscribe, and query.

| | |
|---|---|
| **Owns** | `subscriptions` table (all columns), `SubscriptionRepository`, `SubscriptionService`, `SubscriptionController`, `TokenGenerator`, `SubscribeRequest` DTO, `Subscription` entity |
| **Reads** | GitHub API (via `GitHubServiceInterface`) — validates repo on subscribe, snapshots latest tag on confirm |
| **Writes** | Triggers confirmation email (via `ConfirmationMailerInterface`) on subscribe |
| **Emits** | (no event bus on this path) — calls `ConfirmationMailerInterface` directly |
| **Receives** | HTTP: `POST /api/subscribe`, `GET /api/confirm/{token}`, `GET /api/unsubscribe/{token}`, `GET /api/subscriptions` |
| **Exceptions** | `AlreadySubscribedException`, `TokenNotFoundException`, `ValidationException` |

---

### GitHub Integration
External API façade. Abstracts GitHub's REST API behind a stable domain interface. All callers consume `GitHubServiceInterface`.

| | |
|---|---|
| **Owns** | `GitHubService`, `GitHubServiceInterface`, `GitHubRelease` DTO, `GitHubReleaseUrlBuilder`, `ReleaseUrlBuilderInterface` |
| **Reads** | GitHub REST API (`/repos/{owner/repo}`, `/repos/{owner/repo}/releases/latest`); Redis cache via `CacheInterface` |
| **Writes** | Nothing — read-only; populates Redis cache as a side effect |
| **Emits** | Returns `?string` tag names or throws `InvalidRepositoryFormatException`, `RepositoryNotFoundException`, `RateLimitException` |
| **Receives** | Called by Subscription (validate + snapshot on confirm) and Scanner (release polling) |

---

### Notification (Strangler Fig — Phase 2, AMQP transport added)

Email delivery domain. Extracted to a standalone `notification-service` container in Phase 2. The monolith retains only domain interfaces and three transport adapters; the active transport is controlled by `NOTIFICATION_DRIVER`.

#### Monolith side (`src/Modules/Notification/`)

| Aspect | Detail |
|---|---|
| **Owns** | `ConfirmationMailerInterface`, `NotificationMailerInterface` (domain contracts) |
| **In-process impl** | `EmailService`, `EmailTemplates`, `SmtpConfig` — active when `NOTIFICATION_DRIVER=in_process` |
| **HTTP adapters** | `HttpConfirmationMailer`, `HttpNotificationMailer` — active when `NOTIFICATION_DRIVER=http`; POST to `notification-service` over Docker network |
| **AMQP adapters** | `AmqpConfirmationMailer`, `AmqpNotificationMailer`, `AmqpPublisher` — active when `NOTIFICATION_DRIVER=amqp`; publishes JSON to the `notifications` RabbitMQ queue |
| **Reads** | Nothing — all data passed as call parameters |
| **Writes** | Nothing — delegates to chosen transport impl |

#### `notification-service/` (standalone container)

| | |
|---|---|
| **Owns** | `MailerInterface`, `Mailer`, `MessageHandler`, `NotificationConsumer`, `SendConfirmationHandler`, `SendNotificationHandler`, `HealthController`, `RequestLoggingMiddleware`, `SmtpConfig`, `AmqpConfig`, `Env` |
| **Reads** | HTTP POST body (when called via `http` driver) or AMQP message body (when `amqp` driver is active) |
| **Writes** | Sends SMTP email via PHPMailer |
| **Exposes** | `POST /send-confirmation`, `POST /send-notification`, `GET /health/live`, `GET /health/ready` |
| **Consumes** | `notifications` RabbitMQ queue — `send_confirmation` and `send_notification` message types |
| **Contract** | `notification-service/openapi.yaml` |
| **Rollback** | Set `NOTIFICATION_DRIVER=in_process` — instant, no redeploy |

---

### Scanner
Long-running background process. Polls GitHub for new releases and dispatches notifications to confirmed subscribers.

| | |
|---|---|
| **Owns** | `ReleaseScanner`, `EchoLogger`, `MonologLogger`, `LoggerInterface`, `bin/scanner.php` |
| **Reads** | All confirmed subscriptions via `SubscriptionScanRepositoryInterface::findAllConfirmed()` |
| **Reads** | Latest release tags via `GitHubServiceInterface::getLatestRelease()` |
| **Writes** | `subscriptions.last_seen_tag` via `SubscriptionScanRepositoryInterface::updateLastSeenTag()` |
| **Emits** | Calls `NotificationMailerInterface::sendReleaseNotification()` (transport is AMQP or HTTP depending on `NOTIFICATION_DRIVER`); increments `MetricsCollectorInterface::recordNotificationSent()` |
| **Receives** | Scheduled — `bin/scanner.php` runs in a loop with configurable `SCANNER_INTERVAL` (default 300 s) |

---

### Observability (Metrics)
Cross-cutting concern. Collects runtime counters in Redis and renders a Prometheus-compatible `/metrics` endpoint.

| | |
|---|---|
| **Owns** | `MetricsCollector`, `MetricsCollectorInterface`, `MetricsKeys`, `PrometheusRenderer`, `MetricsRendererInterface`, `NullMetricsCollector`, `DatabaseSubscriptionCounter`, `ActiveSubscriptionCounterInterface`, `MetricsMiddleware`, `MetricsController` |
| **Reads** | Redis hash counters (HTTP requests, GitHub API calls, notification count, scanner cycles, latency histograms); `subscriptions` table `COUNT(*)` via `DatabaseSubscriptionCounter` |
| **Writes** | Redis counters only |
| **Emits** | Prometheus text format on `GET /metrics` |
| **Receives** | Incremented by GitHub Integration, Scanner, and `MetricsMiddleware` on every HTTP request |

---

### SharedKernel (cross-cutting infrastructure, not a domain)

Technical plumbing shared by all monolith modules. Contains no business logic.

| Namespace | Purpose |
|---|---|
| `SharedKernel/Infrastructure/Cache/` | `CacheInterface` + Redis implementation (`RedisCache`, `PredisAdapter`) + null stubs |
| `SharedKernel/Infrastructure/Database/` | `Connection` (PDO singleton factory), `Migrator` |
| `SharedKernel/Infrastructure/` | `Env` (typed env-var reader), `Json` (encode/decode with exceptions) |
| `SharedKernel/Domain/` | `HttpExceptionInterface` |
| `Bootstrap/Middleware/` | `ApiKeyMiddleware`, `CorsMiddleware`, `LoggingMiddleware` |

---

## 2. Context Map

Arrows show direction of data / call flow. The dashed line marks the process boundary. Both transport paths are shown; only one is active at runtime depending on `NOTIFICATION_DRIVER`.

```text
┌──────────────────────────────────────────────────────────────────────────┐
│  Monolith (api + scanner containers)                                     │
│                                                                          │
│  ┌──────────────┐   validates repo    ┌──────────────────────┐           │
│  │              │ ──────────────────► │                      │           │
│  │ Subscription │   snapshot tag      │  GitHub Integration  │           │
│  │              │ ──────────────────► │                      │           │
│  └──────┬───────┘                     └──────────┬───────────┘           │
│         │ ConfirmationMailerInterface             │ getLatestRelease      │
│         │                                        │                       │
│         │                             ┌──────────▼──────────┐            │
│         │                             │       Scanner       │            │
│         │                             │  (background poll)  │            │
│         │                             └──────────┬──────────┘            │
│         │ NotificationMailerInterface             │ NotificationMailerInterface
│         ▼                                        ▼                       │
│  ┌──────────────────────────────────────────────────────────────┐        │
│  │       Notification (monolith side)                           │        │
│  │                                                              │        │
│  │  NOTIFICATION_DRIVER=http  → HttpConfirmationMailer          │        │
│  │                               HttpNotificationMailer         │        │
│  │  NOTIFICATION_DRIVER=amqp  → AmqpConfirmationMailer          │        │
│  │                               AmqpNotificationMailer ──────────────►  RabbitMQ
│  │  NOTIFICATION_DRIVER=in_process → EmailService               │        │   │
│  └──────────────┬───────────────────────────────────────────────┘        │   │
└─────────────────┼─────────────────────────────────────────────────────────┘   │
                  │ HTTP POST                                                    │
        ╌ ╌ ╌ ╌ ╌│╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌
                  ▼                                                              │
   ┌──────────────────────────────────────────┐                                 │
   │          notification-service             │  ◄──── AMQP consumer ──────────┘
   │  POST /send-confirmation                  │       (notifications queue)
   │  POST /send-notification                  │
   │  NotificationConsumer (AMQP)              │
   │  → MessageHandler → Mailer → SMTP         │
   └──────────────────────────────────────────┘
```

```text
┌─────────────────────┐
│   subscriptions DB  │◄── Subscription (create, confirm, delete, findByToken)
│   (single table)    │◄── Scanner (updateLastSeenTag, findAllConfirmed)
└──────────┬──────────┘
           │ COUNT(*)
           ▼
┌─────────────────────┐
│    Observability    │
│  (reads COUNT(*))   │
└─────────────────────┘
```

### Transport selection (NOTIFICATION_DRIVER)

| Value | Transport | Trade-off |
|---|---|---|
| `amqp` | RabbitMQ queue → `notification-service` consumer | Fully async; SMTP failures don't block API; retry via nack |
| `http` | Synchronous HTTP POST → `notification-service` | Simpler; errors surface immediately in the API response |
| `in_process` | Direct PHP call to `EmailService` | Zero-dependency rollback; no network hop |

---

## 3. Pain Point Inventory

| File / Class | Issue | Status |
|---|---|---|
| `EmailService` implements both `ConfirmationMailerInterface` and `NotificationMailerInterface` | One class serves two distinct callers. Retained as the in-process fallback only. | **Addressed** — active path is AMQP or HTTP; `EmailService` is rollback only |
| `DatabaseSubscriptionCounter` in `Observability/` | Reaches into the `subscriptions` table from a different namespace. Fine as a read-only query; would require an anti-corruption layer if databases are ever split. | Open (low severity) |
| `ReleaseScanner` orchestrates three domains | Direct dependencies on `GitHubServiceInterface`, `NotificationMailerInterface`, and `SubscriptionScanRepositoryInterface`. Each becomes a cross-service call in a full microservice model. | Open — by design |
| Shared `subscriptions` table | Both the API process and the scanner process write to the same table. True write-ownership is split between Subscription and Scanner. | Open — natural future seam |
| `EmailTemplates` / `SmtpConfig` duplicated | Monolith retains them for the in-process fallback; `notification-service` has its own equivalents. | Resolves in Phase 3 when the in-process code is deleted |
| No dead-letter queue for AMQP | `MessageHandler` nacks on failure, but there is no DLX/DLQ configured in `NotificationConsumer`. Failed messages are dropped rather than parked for inspection. | Open — should be addressed before AMQP is promoted to default |

---

## 4. Assessment

**Strangler Fig — Phase 2 complete, AMQP transport added (Phase 3 candidate).**

The Notification domain has been fully extracted into a standalone `notification-service` container. The `hw-7-message-bus` branch adds a third transport option — RabbitMQ AMQP — alongside the existing synchronous HTTP and in-process paths. The monolith domain interfaces (`ConfirmationMailerInterface`, `NotificationMailerInterface`) are unchanged; callers are transport-agnostic.

The remaining monolith structure is well-modularised. Domain namespaces (`Subscription`, `GitHub`, `Scanner`, `Observability`, `Notification`) each follow a clean `Domain / Application / Infrastructure` layer pattern. The `SharedKernel` holds technical plumbing with no domain logic. No god classes exist. Cross-domain table writes are limited to the intentional Scanner → `subscriptions.last_seen_tag` write.

The primary remaining structural tension is the shared `subscriptions` table, written to by both the API process (Subscription domain) and the scanner process (Scanner domain). This is the natural seam for any future Scanner extraction.

---

## 5. Recommended Next Step

**Option A — Phase 3 cutover (Notification):** After 2+ weeks of AMQP stability in production:

1. Promote `NOTIFICATION_DRIVER=amqp` as the hardcoded default
2. Delete `EmailService`, `EmailTemplates`, `SmtpConfig` from the monolith, and the `HttpConfirmationMailer`/`HttpNotificationMailer` HTTP adapters
3. Remove the `NOTIFICATION_DRIVER` toggle from `config/container.php` and `.env.example`
4. Add a dead-letter exchange (DLX) to `NotificationConsumer` before deleting the `in_process` fallback

**Option B — Scanner extraction (`php-microservice-extraction`):** Extract the Scanner background process into its own service to eliminate the shared-table write split. This is the next natural domain boundary.

See [ADR-003](../adr/0003-extract-notification-as-microservice.md) for the Notification extraction rationale and rollback plan.
