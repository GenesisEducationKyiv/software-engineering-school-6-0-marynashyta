# ADR-003: Extract Email Notification as a Standalone Microservice

**Status:** Accepted

**Date:** 2026-06-07

**Author:** Maryna Shyta

## Context

The monolith delivers subscription confirmation and release notification emails via `EmailService`, which is bound to the main `api` container. As the system grows, this coupling creates two problems:

1. **Deployment coupling** — a change to email templates or SMTP configuration requires rebuilding and redeploying the full API image.
2. **Scaling asymmetry** — the HTTP API handles real-time user requests; email delivery is bursty and latency-tolerant. They have fundamentally different scaling profiles.

Four domain modules were evaluated for extraction:

| Domain | Coupling | Scaling | Contract stability | Blast radius | Data independence | Score |
|---|---|---|---|---|---|---|
| Notification | No cross-domain writes | Different (bursty) | Stable for 6+ months | Degrades email only | No DB tables | **8** |
| Observability | No cross-domain writes | Slightly different | Minor changes | Degrades metrics only | No DB tables | 6 |
| GitHub | Reads from Notification | Same as monolith | Frequently changing | Degrades subscriptions | Shares tables | 5 |
| Scanner | Writes across domains | Same as monolith | Frequently changing | Degrades all notifications | Shares tables | 4 |

Notification scored highest because it owns zero database tables (no data migration risk), already has two clean domain interfaces (`ConfirmationMailerInterface`, `NotificationMailerInterface`), and a failure degrades only email delivery — subscriptions and scans continue.

## Decision

Extract email delivery into a standalone `notification-service` using the **Strangler Fig pattern** in three phases:

**Phase 1 (done in monolith):** Ensure all callers go through `ConfirmationMailerInterface` / `NotificationMailerInterface`. No concrete class is referenced directly in application code.

**Phase 2 (current):** Deploy `notification-service` as a separate container. Add HTTP adapters (`HttpConfirmationMailer`, `HttpNotificationMailer`) in the monolith. Wire via `NOTIFICATION_DRIVER` env var:

```dotenv
NOTIFICATION_DRIVER=http          # routes to notification-service
NOTIFICATION_DRIVER=in_process    # instant rollback — no redeploy needed
```

**Phase 3 (future — after 2+ weeks stable in production):** Delete `EmailService`'s mailer methods and the toggle; `HttpConfirmationMailer` / `HttpNotificationMailer` become the only implementations.

The new service exposes a minimal HTTP API (`POST /send-confirmation`, `POST /send-notification`, `GET /health/live`, `GET /health/ready`), has no database, and logs structured JSON with `X-Trace-Id` propagation. The contract is defined in `notification-service/openapi.yaml` and was written before any implementation.

## Considered Alternatives

**Keep email in the monolith.**
Simple, no network hop, no new operational surface. Rejected because it prevents independent deployment of email changes and makes scaling decisions unnecessarily coupled.

**Extract via async queue (RabbitMQ).**
Decouples latency entirely — the API enqueues and moves on; the consumer retries on SMTP failure. Rejected for this phase because it adds a broker dependency and complicates local development. The interfaces are designed so the transport can be swapped to a queue in Phase 3 without changing any callers.

**gRPC instead of HTTP.**
Stronger typing, binary framing, bi-directional streaming. Rejected because PHPMailer's delivery is already I/O-bound; the framing overhead is negligible, and gRPC adds tooling complexity (proto compilation) that HTTP+JSON avoids.

## Implications

**Positives:**

- Email templates, SMTP configuration, and PHPMailer version updates can be deployed independently of the API.
- `NOTIFICATION_DRIVER=in_process` is a one-variable, zero-redeploy rollback.
- The notification service has no database and no state — it is trivially horizontally scalable.
- Structured JSON logging with `X-Trace-Id` lets traces span both services in the ELK stack.
- PHPStan level 9 + PHPUnit run independently in CI without pulling in the full monolith test suite.

**Cons:**

- Adds a network hop (localhost Docker network — sub-millisecond in practice, but one more failure point).
- SMTP failure now surfaces as a 500 from the notification service rather than a PHP exception in the API process — error handling crosses an HTTP boundary.
- Phase 3 cleanup must be tracked explicitly; leaving the toggle and `in_process` code indefinitely is a maintenance burden.
- Operational surface increases: the `notification` container needs its own health checks, log aggregation, and restart policies.
