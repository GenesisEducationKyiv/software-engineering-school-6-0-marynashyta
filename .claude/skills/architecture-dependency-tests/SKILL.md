---
name: architecture-dependency-tests
description: >-
  Turn the layered architecture of a plain PHP (no-framework) project into executable,
  enforced rules using Deptrac and PHPArkitect: define layers, forbid the wrong
  dependencies, add naming rules, wire composer scripts and CI. Use this whenever the user
  wants to "test the architecture", "enforce layer boundaries", "add architecture tests",
  "stop the domain depending on infrastructure", or mentions Deptrac or PHPArkitect — even
  if they don't name a tool. Pairs with the architecture-documentation skill: the docs
  describe the layers, these tests make the description true and keep it true.
---

# Architecture dependency tests

Encode the project's layer model (from `CLAUDE.md` and `ARCHITECTURE.md`) as **executable
checks** that fail the build when the Dependency Rule is broken. Use two complementary
tools:

- **Deptrac** (`deptrac/deptrac`) — coarse **layer boundaries** via `deptrac.yaml`
  (collectors define layers; a ruleset says which layer may depend on which). Also
  visualizes and reports uncovered code. This is the primary layer-dependency test.
- **PHPArkitect** (`phparkitect/phparkitect`) — fine-grained rules as fluent PHP in
  `phparkitect.php` (naming conventions, "depends only on these namespaces", value objects
  immutable, etc.), each with a human-readable `->because()` reason.

Both are dev dependencies and both run in CI. Keep their layer definitions identical to the
layer breakdown table in `ARCHITECTURE.md` — if they drift, the docs are lying.

## Workflow

1. **Read the model.** Take the exact namespace→layer mapping and allowed-dependency matrix
   from `CLAUDE.md` / `ARCHITECTURE.md`. Do not invent a different one here.

2. **Install the tools.**
   ```bash
   composer require --dev deptrac/deptrac phparkitect/phparkitect
   ```
   If either conflicts with project dependencies, fall back to the PHAR (see the reference
   files).

3. **Configure Deptrac.** Copy `assets/deptrac.yaml` to the project root. Define one layer
   per architectural layer using `classLike` collectors on the namespace, and a `ruleset`
   that lists allowed dependencies. `Domain` allows only `Shared`; `Shared` allows nothing.
   Run with `--fail-on-uncovered` so any file not assigned to a layer is a failure, not a
   silent gap. Details and options: `references/deptrac.md`.

4. **Configure PHPArkitect.** Copy `assets/phparkitect.php` to the project root. Add:
   - the inward-only dependency rules (`Domain` depends only on `Domain` + `Shared`;
     `Application` not on `Infrastructure`/`Presentation`; `Domain` not on any outer layer),
   - naming rules (`*Controller`, `*RepositoryInterface`, `*Handler`, `*Dto`),
   - any project-specific constraints (e.g. DTOs are `final` and `readonly`).
   Vocabulary and patterns: `references/phparkitect.md`.

5. **Add composer scripts.**
   ```json
   "scripts": {
     "arch:layers": "deptrac analyse --fail-on-uncovered",
     "arch:rules": "phparkitect check",
     "arch": ["@arch:layers", "@arch:rules"]
   }
   ```

6. **Add CI.** Copy `assets/ci-architecture.yml` to `.github/workflows/` (or adapt to the
   project's CI) so `deptrac` and `phparkitect` run on every push and pull request.

7. **Run and resolve.**
   ```bash
   composer arch
   ```
   For each violation, either **fix the dependency** (usually: introduce/inject an interface
   so the arrow points inward) or, if it's an intentional temporary exception, add it to a
   **baseline** — never by loosening the rule itself. Record any deliberate exception in an
   ADR. A baseline stops the bleeding while letting new violations still fail.

## Rules of engagement

- **The docs and the config are one thing.** Layers, namespaces, and allowed dependencies
  in `deptrac.yaml` / `phparkitect.php` must match `ARCHITECTURE.md` exactly.
- **No uncovered code.** Every file belongs to a layer; run Deptrac with
  `--fail-on-uncovered`.
- **Fix, don't weaken.** Making the build green by deleting a rule or widening an allowed
  dependency defeats the purpose. Fix the code or baseline-and-document.
- **Reasons are mandatory.** Every PHPArkitect rule has a `->because()` that explains the
  constraint to whoever hits it.

## Reference files

- `references/deptrac.md` — collectors, ruleset, commands, uncovered handling, baselines,
  and per-module rules. Read before editing `deptrac.yaml`.
- `references/phparkitect.md` — rule vocabulary (`DependsOnlyOnTheseNamespaces`,
  `NotDependsOnTheseNamespaces`, `ResideInOneOfTheseNamespaces`, naming, `IsFinal`,
  `IsReadonly`), baselines, and CLI. Read before editing `phparkitect.php`.

## Assets

- `assets/deptrac.yaml` — ready layer + ruleset config for the five-layer model.
- `assets/phparkitect.php` — ready fluent rules for the same model.
- `assets/ci-architecture.yml` — GitHub Actions job that runs both checks.
