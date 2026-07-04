# Service contracts reference

The rules that keep microservices decoupled at runtime — the seams the landscape draws.
Static tools (Deptrac/PHPArkitect) enforce boundaries *inside* a service; these boundaries
*between* services are enforced by discipline, ADRs, and contract tests.

## Table of contents

1. Database-per-service
2. Communicate only through published contracts
3. Versioning contracts and events
4. Consumer-driven contract testing
5. The shared contracts package

---

## 1. Database-per-service

Each service owns its data and its schema; no other service reads or writes those tables. A
shared database is the most common way a "microservice" architecture quietly becomes a
distributed monolith — a schema change in one service breaks another, and neither team can
deploy independently. If service B needs data owned by A, it asks A (a call) or subscribes to
A's events; it never joins A's tables. Record this as a platform ADR, because no static
analyser can see a cross-service SQL connection string.

## 2. Communicate only through published contracts

A service's **public contract** is the deliberately published surface others may depend on:
its HTTP/gRPC API and the events it emits. Everything else — its domain model, its database,
its internal classes — is private. Consumers bind to the contract, not to the provider's
internals. In this template that means:

- **No** `use Acme\Orders\Domain\...` from inside the Catalog service. Cross-service PHP
  coupling is forbidden; the only shared code is the versioned contracts package.
- Synchronous needs → call the provider's API. Reactive needs → subscribe to its events.
- Every such dependency is declared as an edge in `architecture.php`, so it shows up in the
  landscape and can be reviewed.

## 3. Versioning contracts and events

Contracts change; consumers must not break when they do.

- **Additive, backward-compatible changes** (new optional field, new event type) are safe.
- **Breaking changes** require a new version (`/v2`, or a new event schema version) with the
  old one supported until consumers migrate.
- Events carry a schema version; consumers ignore fields they don't understand rather than
  failing (tolerant reader).

Capture the chosen strategy in an ADR so every team versions the same way.

## 4. Consumer-driven contract testing

Declared edges say what *should* be true; contract tests verify it stays true. In
consumer-driven contract testing (e.g. a Pact-style flow), each consumer publishes the shape
of the interaction it expects; the provider runs those expectations in its own CI and fails
if it would break a consumer. This catches breakage at build time instead of in production,
and it makes the manifest's edges executable rather than aspirational. Wire provider
verification into each service's pipeline; track which consumer expectations a provider must
honour.

## 5. The shared contracts package

The one sanctioned form of code sharing is a small, **versioned** contracts package
(DTOs/interfaces/event schemas), depended on via Composer with a version constraint — never a
path reference into a sibling repo. Keep it minimal: the more you put in it, the more you
couple every service to its release cadence. In the manifest it appears as the `shared` node
and every service links to it with a `lib` edge, so the coupling is visible and intentional.
