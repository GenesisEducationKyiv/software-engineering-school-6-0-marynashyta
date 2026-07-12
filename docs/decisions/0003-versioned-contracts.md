# ADR-0003 — Versioned contracts and consumer-driven contract testing

- **Status:** Accepted
- **Date:** YYYY-MM-DD
- **Deciders:** <platform team>

## Context

If services depend on each other's contracts, a change to a contract can break consumers.
Declaring dependencies in the manifest says what *should* hold, but doesn't stop a provider
from shipping a breaking change. We need a versioning rule and a way to verify contracts at
build time.

## Decision

Contracts (APIs, events, the shared contracts package) are versioned. Additive changes are
backward compatible; breaking changes ship as a new version with the old one supported until
consumers migrate. Consumers are tolerant readers (ignore unknown fields). Where feasible,
each consumer publishes its expectations and the provider verifies them in CI
(consumer-driven contract testing), so a breaking change fails the provider's build rather
than production.

## Alternatives considered

- **Unversioned contracts, coordinate changes manually** — rejected: reintroduces the
  coordinated-deploy coupling the architecture exists to avoid.
- **Only integration tests in a shared environment** — rejected: slow, flaky, and finds
  breakage late.

## Consequences

- Providers get fast, build-time feedback before breaking a consumer.
- Contract versioning adds process overhead, justified by independent deployability.
- The manifest's declared edges become executable expectations, not just documentation.
