# ADR-0001 — Service boundaries and contracts-only communication

- **Status:** Accepted
- **Date:** YYYY-MM-DD
- **Deciders:** <platform team>

## Context

The system is built as multiple independently deployable services. The main risk is
accidentally coupling them into a distributed monolith — where a change in one forces a
coordinated deploy of others — which loses the whole point of the split. We need an explicit,
enforceable rule for how services may and may not depend on each other.

## Decision

A service's internals (domain model, database, classes) are private. Services communicate
**only** through published contracts: synchronous HTTP/gRPC APIs, asynchronous events, or the
shared, versioned contracts package. No service imports another service's code, and no service
reads another service's database. Every cross-service dependency is declared as an edge in
`architecture.php` and appears in the compiled landscape for review.

## Alternatives considered

- **Shared library of domain code** — rejected: couples services to each other's release
  cadence and internal models.
- **Shared database with agreed tables** — rejected: schema changes break consumers; kills
  independent deployability. See ADR-0002.

## Consequences

- Services deploy independently; internal refactors don't ripple outward.
- Every dependency is visible in the manifest/landscape and reviewable.
- Some data must be fetched via calls or replicated via events rather than joined — more
  moving parts, mitigated by contract tests (see ADR-0003) and clear ownership.
