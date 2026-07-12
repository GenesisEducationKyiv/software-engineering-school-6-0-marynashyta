---
name: platform-architecture
description: >-
  Maintain the PLATFORM-level architecture across many plain PHP microservices: keep the
  service manifest current, compile the system landscape document from every service plus
  the shared source tree, model cross-service dependencies and contracts, and record
  platform-wide ADRs (database-per-service, communication style, contract versioning). Use
  whenever the user asks about the "big picture", "system landscape", "context map",
  "how services talk to each other", "which service depends on which", "compile the
  architecture", or boundaries BETWEEN services. For the internals of a single service, use
  that service's own architecture-documentation / architecture-dependency-tests skills.
---

# Platform architecture

Own the view **across** services that no single service repo can see: the system landscape,
who-calls-whom, the contracts between them, and the platform-wide decisions. The per-service
layering is enforced inside each service; this skill governs the seams between them and keeps
one compiled picture of the whole.

## What lives here

```
architecture.php                 # manifest: services + shared src + dependency edges
deptrac.services.yaml             # monorepo only: forbids direct service-to-service imports
composer.json                    # platform tooling (deptrac) + `composer arch` scripts
bin/compile-architecture.php     # compiles services + src into docs/LANDSCAPE.md
docs/
  LANDSCAPE.md                   # generated — do not hand-edit
  decisions/                     # platform-wide ADRs
.github/workflows/landscape.yml  # recompiles/validates on push/PR
```

## Workflow

1. **Keep the manifest true.** When a service is added, renamed, re-owned, or changes how it
   talks to another service, update `architecture.php`: its `services[]` entry (name, owner,
   namespace, path, src, docs) and the `edges[]` (from → to, `via`, `style`). The compiler
   fails on a service whose source or `ARCHITECTURE.md` is missing, so the manifest can't
   silently drift. See `references/system-landscape.md` for how to model nodes and edges.

2. **Compile the landscape.**
   ```bash
   php bin/compile-architecture.php --manifest=architecture.php
   # CI / partial checkout:
   php bin/compile-architecture.php --manifest=architecture.php --check --allow-missing
   ```
   This regenerates `docs/LANDSCAPE.md`: a Mermaid landscape from the edges, a service
   registry table (with layers detected under each `src/`), and a per-service summary that
   lifts each service's `## 1. Overview`. Never hand-edit the output — change the manifest or
   the services and recompile.

3. **Model contracts, not code coupling.** Services must depend only on **published,
   versioned contracts** (HTTP/events/a shared contracts package), never a shared database or
   a reach into another service's internals. Capture the rules and the versioning approach
   per `references/service-contracts.md`. Where feasible, back them with consumer-driven
   contract tests so the manifest's edges are verified at runtime, not just declared.

4. **Enforce "no direct service-to-service coupling" statically (monorepo only).** If
   services live in the same repo, `deptrac.services.yaml` treats each service directory as a
   layer and forbids any service importing another service's namespace directly — the only
   permitted dependency is the shared kernel. This makes rule #3 an executable test instead of
   just a convention:
   ```bash
   composer arch:services   # vendor/bin/deptrac analyse --config-file=deptrac.services.yaml --fail-on-uncovered
   ```
   Add a `layers` block and a ruleset entry for every new service directory. In a polyrepo this
   file doesn't apply — the code simply isn't present to import, so skip it.

5. **Record platform decisions as ADRs.** Cross-cutting choices — database-per-service,
   synchronous vs event-driven communication, contract/event versioning, service boundaries
   — go in `docs/decisions/` (see the seeded 0001–0003). These are distinct from any single
   service's ADRs.

## Quality bar

- `architecture.php` matches reality; `docs/LANDSCAPE.md` is freshly compiled and committed.
- Every declared edge corresponds to a real, versioned contract — no hidden coupling.
- In a monorepo, `composer arch:services` passes — no service imports another service's code.
- The landscape diagram distinguishes synchronous calls from asynchronous/event flows.
- Platform-wide invariants (database-per-service, contracts-only) are stated in ADRs and, where
  possible, enforced by tests.

## Reference files

- `references/system-landscape.md` — modelling the landscape: nodes, ownership, sync vs
  async edges, the C4 System-Landscape/Context view, and how the compiler reads the manifest.
- `references/service-contracts.md` — database-per-service, contracts-only communication,
  versioning strategies, and consumer-driven contract testing.
