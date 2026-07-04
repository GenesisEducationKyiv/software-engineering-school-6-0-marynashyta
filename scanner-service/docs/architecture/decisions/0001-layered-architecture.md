# ADR-0001 — Ports & adapters per feature, enforced by the Dependency Rule

- **Status:** Accepted
- **Date:** 2026-07-04
- **Deciders:** Maryna Shyta

## Context

Scanner Service was extracted from the release-notification monolith as a small,
single-purpose CLI daemon: poll GitHub, compare tags, publish notifications. It was built
feature-first — one package per collaborator (`GitHub`, `Notification`, `Subscription`),
each with a port interface and one adapter — rather than with top-level `Domain/Application/
Infrastructure/Presentation` folders like the platform template and the monolith's modules
use. Without an explicit, enforced rule, nothing stops the single use case
(`Scanner\ReleaseScanner`) from reaching directly into a concrete adapter (`GitHubService`,
`HttpSubscriptionScanClient`, `AmqpNotificationPublisher`), which would make the use case
untestable without a real GitHub/HTTP/AMQP call and couple it to a specific transport.

## Decision

We keep the existing feature-first package layout — it fits a service this small — but
enforce the same Dependency Rule the rest of the platform uses: source-code dependencies
point inward, toward ports and value objects, never toward a concrete adapter. Concretely:

- **Domain** — value objects and domain exceptions per feature (`GitHub\GitHubRelease`,
  `GitHub\Exception\RateLimitException`, `Subscription\Subscription`).
- **Application** — the port interfaces (`*Interface`) plus the one use case,
  `Scanner\ReleaseScanner`, which depends only on those ports and on Domain value objects.
- **Infrastructure** — the concrete adapters, the `CircuitBreaker` resilience helper, and
  typed config (`AmqpConfig`, `ApiConfig`).
- **Shared** — `Config\Env`, the only class with zero internal dependencies used everywhere.
- **Presentation** — outside `src/`: `bin/scanner.php` is the composition root; it is the
  only place a port is bound to its adapter (via `config/container.php`).

Deptrac and PHPArkitect express these as regex layers over namespaces/suffixes (e.g.
`.*Interface$` for ports) instead of physical top-level folders, and both run in CI
(`.github/workflows/architecture.yml`).

## Alternatives considered

- **Restructure into physical `Domain/Application/Infrastructure/Presentation/Shared`
  folders**, matching the platform template and the monolith's modules byte-for-byte.
  Rejected for now: it would touch every file and namespace in a small, working, already
  strangled-out service for a purely cosmetic match with no behavioural benefit; the
  Dependency Rule is fully enforceable via regex collectors without moving a single class.
  Revisit if the service grows enough features that "one port + one adapter" per package
  stops being self-explanatory.
- **No enforced structure (convention only)** — rejected: conventions erode silently, and a
  single-file use case is exactly the kind of code that quietly grows a `new GitHubService()`
  if nothing catches it in review.

## Consequences

- `ReleaseScanner` stays testable with fakes for all three ports and no real HTTP/AMQP call.
- Swapping a transport (e.g. HTTP → AMQP for subscription reads) only touches Infrastructure.
- The layer table in `ARCHITECTURE.md` and the collectors in `deptrac.yaml`/`phparkitect.php`
  must stay in agreement — checked by `composer arch` locally and in CI.
- Because layers are namespace/suffix patterns rather than directories, a class named
  `*Interface` outside the intended ports would silently join the Application layer; new
  contributors should keep that naming convention as the boundary it doubles as.
