# ADR-0001 — Ports & adapters per feature, enforced by the Dependency Rule

- **Status:** Accepted
- **Date:** 2026-07-04
- **Deciders:** Maryna Shyta

## Context

Notification Service was extracted from the release-notification monolith to own email
delivery behind two independently deployable entry points — an HTTP API and an AMQP
consumer — that share one codebase and one image. It was built feature-first: a single
`MailerInterface` port with one `Mailer` adapter, a `Handler/` package for the HTTP delivery
mechanism, and a `Consumer/` package for the AMQP delivery mechanism (connection handling,
retry, dead-lettering), rather than with top-level `Domain/Application/Infrastructure/
Presentation` folders like the platform template and the monolith's modules use. Without an
explicit rule, nothing would stop either delivery mechanism from reaching around the port
into `Mailer`/PHPMailer directly, which would make the "does this message parse and validate
correctly" logic untestable without a real SMTP connection and duplicate it between the two
entry points.

## Decision

We keep the existing feature-first package layout — two delivery mechanisms sharing one
core is a natural fit for it — but enforce the same Dependency Rule the rest of the platform
uses: source-code dependencies point inward, toward the port, never toward the concrete
mailer. Concretely:

- **Domain** — the port (`MailerInterface`) and the one business rule that doesn't belong to
  either delivery mechanism specifically: `Consumer\RetryPolicy` (how many attempts, which
  failures are worth retrying).
- **Application** — `Consumer\MessageHandler`, the transport-agnostic use case: parse an
  inbound message and call the port. There is no equivalent Application class on the HTTP
  side because validating four required fields and delegating is thin enough to live
  directly in the Presentation handler.
- **Infrastructure** — `Mailer` (the only port implementation), the AMQP queue mechanics
  (`Consumer\MessageProcessor`, `Consumer\NotificationConsumer`), typed settings
  (`Config\SmtpConfig`, `Config\AmqpConfig`), and the third-party libraries they wrap
  (`php-amqplib`, PHPMailer's client class).
- **Presentation** — the two HTTP send-mail routes, health checks, and request-logging
  middleware; delivery-specific, request/response mapping only.
- **Shared** — `Config\Env`, the PSR logging contract, and — deliberately — PHPMailer's
  `Exception` class, because `MailerInterface` declares `@throws PHPMailerException` on both
  methods. That exception is part of the port's contract, not an Infrastructure detail
  leaking past it; every caller of the port is allowed to catch it.

Deptrac and PHPArkitect express these as regex layers over namespaces/classes instead of
physical top-level folders, and both run in CI (`.github/workflows/architecture.yml`).

## Alternatives considered

- **Restructure into physical `Domain/Application/Infrastructure/Presentation/Shared`
  folders**, matching the platform template byte-for-byte. Rejected for now: this service has
  exactly one port and one adapter; folders would add ceremony without adding any real
  boundary the Dependency Rule doesn't already enforce via regex collectors.
- **Wrap `PHPMailerException` in a domain-specific `MailerException`** so the port's contract
  never names a third-party type. Rejected for now: with a single adapter and no plan to swap
  mail providers, the wrapper is speculative generality; revisit if a second `MailerInterface`
  implementation is ever added, since at that point the leak would actually bite.
- **No enforced structure (convention only)** — rejected: conventions erode silently, and
  it's exactly the kind of service where "just call `new Mailer()` from the handler, it's
  faster" quietly creeps in without a test catching it.

## Consequences

- `MessageHandler` and `RetryPolicy` are testable with a fake `MailerInterface` and no real
  AMQP connection or SMTP server.
- Swapping the mail provider only touches `Mailer` and `Config\SmtpConfig`; both delivery
  mechanisms are unaffected.
- The layer table in `ARCHITECTURE.md` and the collectors in `deptrac.yaml`/`phparkitect.php`
  must stay in agreement — checked by `composer arch` locally and in CI.
- The documented `PHPMailerException`-in-Shared exception is a real, minor abstraction leak,
  not an oversight; it is written down here so it doesn't get "fixed" by loosening a rule
  instead of by actually introducing a wrapper type, if that becomes worth doing later.
