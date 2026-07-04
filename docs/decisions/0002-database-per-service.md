# ADR-0002 — Database-per-service

- **Status:** Accepted
- **Date:** YYYY-MM-DD
- **Deciders:** <platform team>

## Context

Sharing a database between services is the most common way independence is lost: two services
bind to the same schema, so neither can change it or deploy without coordinating with the
other. We need each service to fully own its data.

## Decision

Each service owns its database/schema exclusively. No other service reads or writes those
tables, and there are no cross-service joins. When a service needs data owned by another, it
calls that service's API or subscribes to its events; it never reaches into the other's
storage.

## Alternatives considered

- **Shared database, private tables by convention** — rejected: conventions erode and nothing
  prevents a cross-service query at 2am.
- **Shared read replicas** — rejected: still couples consumers to the owner's schema.

## Consequences

- Services can change their schema and deploy independently.
- Data needed across services is obtained by call or event, and sometimes replicated locally
  (eventual consistency) — an accepted trade-off.
- This rule can't be checked by static analysis of one repo; it is enforced by review and
  captured here as a platform invariant.
