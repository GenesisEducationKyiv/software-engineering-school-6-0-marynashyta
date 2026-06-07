# Domain Map — GitHub Release Notification API

Generated: 2026-06-07  
Branch: `hw-6-microservices`

---

## 1. Domain List

### Subscription
The core domain. Owns the full subscription lifecycle: create, confirm, unsubscribe, and query.

| | |
|---|---|
| **Owns** | `subscriptions` table (all columns except `last_seen_tag`), `SubscriptionRepository`, `SubscriptionService`, `SubscriptionController`, `TokenGenerator`, `SubscribeRequest` DTO, `Subscription` DTO |
| **Reads** | GitHub API (via `GitHubServiceInterface`) — validates repo on subscribe, snapshots latest tag on confirm |
| **Writes** | Triggers confirmation email (via `ConfirmationMailerInterface`) on subscribe |
| **Emits** | (no event bus) — calls `ConfirmationMailerInterface` directly |
| **Receives** | HTTP requests: `POST /api/subscribe`, `GET /api/confirm/{token}`, `GET /api/unsubscribe/{token}`, `GET /api/subscriptions` |
| **Exceptions** | `AlreadySubscribedException`, `TokenNotFoundException`, `ValidationException`, `InvalidRepositoryFormatException`, `RepositoryNotFoundException`, `RateLimitException` |

---

### GitHub Integration
External API façade. Abstracts GitHub's REST API behind a stable interface. All callers consume `GitHubServiceInterface`.

| | |
|---|---|
| **Owns** | `GitHubService`, `GitHubServiceInterface`, `GitHubRelease` DTO, `GitHubReleaseUrlBuilder`, `ReleaseUrlBuilderInterface` |
| **Reads** | GitHub REST API (`/repos/{owner/repo}`, `/repos/{owner/repo}/releases/latest`) |
| **Writes** | Nothing — read-only |
| **Emits** | Returns `?string` tag names or throws domain exceptions |
| **Receives** | Called by Subscription (validate + snapshot) and Scanner (poll for new releases) |

---

### Notification (Strangler Fig — Phase 2)

Email delivery. Extracted to a standalone `notification-service` container. The monolith retains the domain interfaces and two adapters; which adapter is active is controlled by `NOTIFICATION_DRIVER`.

#### Monolith side (`src/Modules/Notification/`)

| | |

|---|---|
| **Owns** | `ConfirmationMailerInterface`, `NotificationMailerInterface` (domain contracts) |
| **In-process impl** | `EmailService`, `EmailTemplates`, `SmtpConfig` — active when `NOTIFICATION_DRIVER=in_process` |
| **HTTP adapters** | `HttpConfirmationMailer`, `HttpNotificationMailer` — active when `NOTIFICATION_DRIVER=http`; POST to `notification-service` over Docker network |
| **Reads** | Nothing — all data passed in as parameters |
| **Writes** | Nothing from the monolith side — delegates to the chosen impl |

#### `notification-service/` (standalone container)

| | |
|---|---|
| **Owns** | `MailerInterface`, `Mailer`, `SendConfirmationHandler`, `SendNotificationHandler`, `HealthController`, `RequestLoggingMiddleware`, `SmtpConfig`, `Env` |
| **Reads** | Nothing — all data arrives via HTTP POST body |
| **Writes** | Sends SMTP email via PHPMailer |
| **Exposes** | `POST /send-confirmation`, `POST /send-notification`, `GET /health/live`, `GET /health/ready` |
| **Contract** | `notification-service/openapi.yaml` |
| **Rollback** | Set `NOTIFICATION_DRIVER=in_process` — instant, no redeploy |

---

### Scanner
Long-running background process. Polls GitHub for new releases and dispatches notifications to subscribers.

| | |
|---|---|
| **Owns** | `ReleaseScanner`, `EchoLogger`, `MonologLogger`, `LoggerInterface`, `bin/scanner.php` |
| **Reads** | All confirmed subscriptions via `SubscriptionScanRepositoryInterface` |
| **Reads** | Latest release tags via `GitHubServiceInterface` |
| **Writes** | `subscriptions.last_seen_tag` (via `SubscriptionScanRepositoryInterface::updateLastSeenTag`) |
| **Emits** | (no event bus) — calls `NotificationMailerInterface` directly; records `MetricsCollectorInterface::recordNotificationSent` |
| **Receives** | Scheduled — runs in a `while (true)` loop with configurable `SCANNER_INTERVAL` (default 300 s) |

---

### Observability (Metrics)
Cross-cutting concern. Collects counters and renders a Prometheus-compatible `/metrics` endpoint.

| | |
|---|---|
| **Owns** | `MetricsCollector`, `MetricsCollectorInterface`, `MetricsKeys`, `PrometheusRenderer`, `MetricsRendererInterface`, `NullMetricsCollector`, `DatabaseSubscriptionCounter`, `ActiveSubscriptionCounterInterface`, `MetricsMiddleware`, `MetricsController` |
| **Reads** | Redis hash counters (HTTP, GitHub API, notifications, scanner cycles, latency histogram); `subscriptions` table `COUNT(*)` via `DatabaseSubscriptionCounter` |
| **Writes** | Redis counters only |
| **Emits** | Prometheus text format on `GET /metrics` |
| **Receives** | Incremented by GitHub Integration, Scanner, and HTTP middleware |

---

### SharedKernel (cross-cutting, not a domain)

Technical plumbing shared by all monolith domains.

| Namespace | Purpose |
|---|---|
| `SharedKernel/Infrastructure/Cache/` | `CacheInterface` + Redis implementation (`RedisCache`, `PredisAdapter`) + null stub |
| `SharedKernel/Infrastructure/Database/` | `Connection` (PDO singleton factory), `Migrator` |
| `SharedKernel/Infrastructure/` | `Env` (typed env-var reader), `Json` (encode/decode with exceptions) |
| `SharedKernel/Domain/` | `HttpExceptionInterface` |
| `Bootstrap/Middleware/` | `ApiKeyMiddleware`, `CorsMiddleware`, `LoggingMiddleware` |

---

## 2. Context Map

Arrows show direction of data/call flow. The dashed line marks the process boundary introduced in Phase 2.

```text
┌──────────────────────────────────────────────────────────────────────┐
│  Monolith (api + scanner containers)                                 │
│                                                                      │
│  ┌──────────────┐   validates repo    ┌─────────────────────┐        │
│  │              │ ──────────────────► │                     │        │
│  │ Subscription │   snapshot tag      │  GitHub Integration │        │
│  │              │ ──────────────────► │                     │        │
│  └──────┬───────┘                     └──────────┬──────────┘        │
│         │ ConfirmationMailerInterface             │ getLatestRelease  │
│         │                                        │                   │
│         │                            ┌──────────▼──────────┐        │
│         │                            │       Scanner       │        │
│         │                            │  (background poll)  │        │
│         │                            └──────────┬──────────┘        │
│         │ NotificationMailerInterface            │ NotificationMailerInterface
│         ▼                                        ▼                   │
│  ┌──────────────────────────────────────────────────────────┐        │
│  │       Notification (monolith side)                       │        │
│  │  HttpConfirmationMailer / HttpNotificationMailer         │        │
│  │  (or EmailService when NOTIFICATION_DRIVER=in_process)   │        │
│  └──────────────────────────┬───────────────────────────────┘        │
└─────────────────────────────┼────────────────────────────────────────┘
                              │ HTTP POST (Docker network)
                    ╌ ╌ ╌ ╌ ╌│╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌ ╌
                              ▼
               ┌──────────────────────────┐
               │   notification-service   │
               │  POST /send-confirmation │
               │  POST /send-notification │
               │  → PHPMailer → SMTP      │
               └──────────────────────────┘
```

```text
┌─────────────────────┐
│   subscriptions DB  │◄── Subscription (create, confirm, delete)
│   (single table)    │◄── Scanner (updateLastSeenTag, findAllConfirmed)
└──────────┬──────────┘
           │ COUNT(*)
           ▼
┌─────────────────────┐
│    Observability    │
│  (reads COUNT(*))   │
└─────────────────────┘
```

### Active process boundary

`NOTIFICATION_DRIVER=http` (production default) routes email delivery across the Docker network to `notification-service`. The monolith domain interfaces (`ConfirmationMailerInterface`, `NotificationMailerInterface`) remain unchanged — callers are unaware of the transport.

`NOTIFICATION_DRIVER=in_process` bypasses the network and calls `EmailService` directly. This is the zero-deploy rollback path.

---

## 3. Pain Point Inventory

| File / Class | Issue | Status |
|---|---|---|
| `EmailService` implements both `ConfirmationMailerInterface` and `NotificationMailerInterface` | One class serves two distinct callers. Retained as the in-process fallback. | **Addressed** — HTTP adapters are the active path; `EmailService` is now the rollback only |
| `DatabaseSubscriptionCounter` in `Observability/` | Reaches into the `subscriptions` table from the Observability namespace. Fine as a read-only query; requires an API call if databases are ever split. | Open |
| `ReleaseScanner` orchestrates three domains | Direct dependencies on `GitHubServiceInterface`, `NotificationMailerInterface`, and `SubscriptionScanRepositoryInterface`. Each becomes a cross-service call in a full microservice model. | Open |
| No event bus | Subscription → Notification and Scanner → Notification are now HTTP calls (Phase 2). Async messaging would eliminate the synchronous coupling and add retry/backoff. | Open — intentionally deferred |
| Shared `subscriptions` table owned by two processes | Both API and scanner write to the same table. True ownership is split between Subscription and Scanner domains. | Open |
| `EmailTemplates` / `SmtpConfig` duplicated | The monolith still contains `EmailTemplates` and `SmtpConfig` for the in-process fallback; `notification-service` has its own equivalents. | Resolves in Phase 3 when in-process code is deleted |

---

## 4. Assessment

**Strangler Fig — Phase 2 (feature-flag cutover).**

The Notification domain has been extracted into a standalone `notification-service` container. The monolith retains the domain interfaces and delegates to either the HTTP adapters or the in-process `EmailService` via `NOTIFICATION_DRIVER`. The extraction was zero-downtime: no database schema changes, no message broker, and a single env-var rollback.

The remaining monolith structure is well-modularised. Domain namespaces (`Subscription`, `GitHub`, `Scanner`, `Observability`, `Notification`) each have clean `Domain / Application / Infrastructure` layers. The `SharedKernel` holds technical plumbing with no domain logic.

The primary remaining structural tension is the shared `subscriptions` table, written to by both the API process and the scanner process. This is the natural seam for any future Scanner extraction.

---

## 5. Next Step

**Phase 3 — Cut over and clean up** (after 2+ weeks stable in production with `NOTIFICATION_DRIVER=http`):

1. Delete `EmailService`'s mailer methods (or the entire class if nothing else uses it)
2. Delete `EmailTemplates`, monolith `SmtpConfig`
3. Remove the `NOTIFICATION_DRIVER` toggle from `config/container.php`
4. Remove `HttpConfirmationMailer` / `HttpNotificationMailer` wrapper indirection — wire the Guzzle calls directly or keep the adapters
5. Remove `NOTIFICATION_DRIVER` from `.env.example` and documentation

See [ADR-003](../adr/0003-extract-notification-as-microservice.md) for the full rationale and rollback plan.
