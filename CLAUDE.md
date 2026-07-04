# Platform rules — architecture & agent guidance (platform)

Source of truth for the **platform** view across all services. This repo holds the system
landscape, the service manifest, the compiler, and platform-wide decisions. Per-service
internals are governed by each service's own `CLAUDE.md`; do not duplicate them here.

---

## 1. Scope

- This repo is the **platform / docs** repo for a set of plain PHP (no-framework)
  microservices. It owns the cross-service picture, not any service's internals.
- One skill lives here: **platform-architecture** — maintain the manifest, compile the
  landscape, model contracts, and record platform ADRs.
- Golden rule of the boundary: a service's internals are private; only its **published,
  versioned contracts** are shared. Enforce layering *inside* a service in that service's
  repo; enforce decoupling *between* services here, via the manifest, ADRs, and contract tests.

## 2. Platform invariants

These hold for every service and are recorded as ADRs in `docs/decisions/`:

- **Database-per-service** — each service owns its schema; no shared tables, no cross-service
  joins. (ADR-0002)
- **Contracts-only communication** — services talk via HTTP/events or the shared contracts
  package; never by importing another service's code or reading its database. (ADR-0001,
  statically enforced in a monorepo by `deptrac.services.yaml` / `composer arch:services`)
- **Versioned contracts** — breaking changes ship as a new version; old versions run until
  consumers migrate. (ADR-0003)

## 3. The manifest, the compiler, and service-boundary enforcement

- `architecture.php` is the single source of truth for which services exist and how they
  depend on each other. Keep it accurate; the compiler fails on missing sources/docs so drift
  surfaces in CI.
- `bin/compile-architecture.php` regenerates `docs/LANDSCAPE.md` from the manifest plus each
  service's `src/` and `ARCHITECTURE.md`. Never hand-edit `LANDSCAPE.md`.
- `deptrac.services.yaml` — **monorepo only.** Statically forbids any service importing
  another service's namespace directly; every service may depend only on the shared kernel.
  Add a layer + ruleset entry for every new service. This is what turns ADR-0001's rule from
  a convention into an enforced test.
  ```bash
  composer install                          # installs deptrac for platform-level checks
  composer arch:services                    # deptrac analyse --config-file=deptrac.services.yaml --fail-on-uncovered
  composer arch:landscape                   # validates the manifest without writing
  composer arch                             # both, in order
  php bin/compile-architecture.php --manifest=architecture.php            # write LANDSCAPE.md
  php bin/compile-architecture.php --manifest=architecture.php --allow-missing  # polyrepo CI
  ```

## 4. Task playbook — keep the platform picture current

1. **Reconcile the manifest** with reality: add/rename/re-own services, update `edges` for any
   changed call/event/contract dependency.
2. **Compile**: run the compiler; commit the regenerated `docs/LANDSCAPE.md`.
3. **Contracts**: for every edge, confirm it maps to a real, versioned contract — not hidden
   coupling. Where feasible, back it with a consumer-driven contract test.
4. **Decisions**: record any new cross-cutting choice as a platform ADR.

### Definition of Done

- `architecture.php` matches reality; `docs/LANDSCAPE.md` is freshly compiled and committed.
- Every edge corresponds to a published, versioned contract.
- In a monorepo, `composer arch:services` passes — no service imports another service directly.
- New platform-wide decisions are captured as ADRs.
- CI compiles the landscape and (in a monorepo) runs the service-boundary Deptrac check.

## 5. Where the references live

Kept lean. Agent-loaded references sit next to the skill:

- Landscape modelling & the compiler — `.claude/skills/platform-architecture/references/system-landscape.md`
- Contracts, database-per-service, versioning, contract testing —
  `.claude/skills/platform-architecture/references/service-contracts.md`

Per-service architecture rules and tooling references live in each **service** repo (adopted
from `service-template/`), not here.
