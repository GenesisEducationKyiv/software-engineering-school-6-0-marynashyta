# System landscape reference

How to model the platform's system landscape and drive the compiler from the manifest.

## Table of contents

1. What the landscape shows
2. Nodes: services and shared trees
3. Edges: dependencies between services
4. The C4 System-Landscape / Context view
5. How the compiler reads `architecture.php`

---

## 1. What the landscape shows

The landscape is the highest-level view: every deployable service, who owns it, the data it
owns, and how the services communicate. It answers "what are all the moving parts and how do
they fit together?" — the question no single service repo can answer on its own. Detail
about the inside of any one service stays in that service's own `ARCHITECTURE.md`; the
landscape links out to it rather than duplicating it.

## 2. Nodes: services and shared trees

Each node is either a **service** (its own repo/checkout with `src/` and an
`ARCHITECTURE.md`) or the optional **shared** source tree (versioned contracts / a shared
kernel every service may use). Give each node: a name, an owning team, its PSR-4 namespace,
and paths to its root, `src/`, and architecture doc. Ownership matters — a service without a
clear owner is an organisational smell worth surfacing on the diagram.

## 3. Edges: dependencies between services

An edge is a declared dependency `from → to` with a `via` label and a `style`:

- **sync** — a synchronous call (HTTP/gRPC). Rendered as a solid arrow. Creates temporal
  coupling: if `to` is down, `from` is affected. Prefer to minimise these on critical paths.
- **async** — an event/message (`OrderPlaced event`). Rendered dashed. Looser coupling; the
  producer doesn't wait on the consumer.
- **lib** — use of the shared contracts package. Rendered dashed. This is the *only*
  sanctioned form of code sharing; it must be versioned.

Point the edge in the direction the dependency runs (the caller/consumer → the provider).
An edge to a node not in the manifest is an error — the compiler rejects it, which catches
typos and undeclared services.

## 4. The C4 System-Landscape / Context view

This is C4's top level. Keep it readable: group by bounded context if you have many
services, label external systems (payment gateways, identity providers) distinctly from
internal services, and don't try to show every call — show the ones that define the
architecture. The compiled Mermaid diagram is generated from the manifest edges, so curate
the edges rather than drawing by hand.

## 5. How the compiler reads `architecture.php`

`bin/compile-architecture.php` requires the manifest (a PHP file returning an array) and, for
each node:

- resolves `path`/`src`/`docs` (relative paths are relative to the manifest file),
- verifies the source tree and `ARCHITECTURE.md` exist — a missing one is a failure unless
  `--allow-missing` is passed (useful in polyrepo CI where not every repo is checked out),
- scans `src/` for the standard layer folders (Domain, Application, Infrastructure,
  Presentation, Shared) and counts `.php` files in each,
- lifts the `## 1. Overview` section from the service's `ARCHITECTURE.md`.

It then writes `docs/LANDSCAPE.md`: the Mermaid landscape (from `edges`), a registry table,
and per-service summaries with relative links back to full docs. Flags: `--manifest=PATH`,
`--check` (validate without writing), `--allow-missing` (warn instead of fail). Run it in CI
so the landscape stays current and manifest drift fails the build.

The compiler validates the manifest's *declared* edges. It does not by itself stop code from
violating them — for that, in a monorepo, pair it with `deptrac.services.yaml`
(`composer arch:services`), which statically forbids a service importing another service's
namespace. See `service-contracts.md` for why that boundary matters and what it doesn't cover
(runtime/API coupling still needs contract tests).
