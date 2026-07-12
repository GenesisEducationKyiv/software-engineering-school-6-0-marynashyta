---
name: architecture-dependency-tests
description: >-
  Turn a single plain PHP service's layered architecture into executable, enforced rules
  with Deptrac and PHPArkitect: define layers, forbid the wrong dependencies, add naming
  rules, wire composer scripts and CI. Use whenever the user wants to "test the
  architecture", "enforce layer boundaries", "add architecture tests", "stop the domain
  depending on infrastructure", or mentions Deptrac or PHPArkitect. Pairs with
  architecture-documentation (the docs these tests make true). For rules BETWEEN services,
  use the platform-architecture skill instead.
---

# Architecture dependency tests (per service)

Encode this service's layer model as **executable checks** that fail the build when the
Dependency Rule is broken, using two complementary tools:

- **Deptrac** (`deptrac/deptrac`) — coarse **layer boundaries** via `deptrac.yaml`.
- **PHPArkitect** (`phparkitect/phparkitect`) — fine-grained per-class rules in
  `phparkitect.php` (naming, "depends only on these namespaces"), each with a `->because()`.

Both run in CI. Keep their layer definitions identical to the layer table in
`ARCHITECTURE.md`. This skill governs dependencies **inside** the service; cross-service
boundaries are the platform-architecture skill's job.

## Scaffold already present

Scaffolded from the platform template, so these exist at the root — edit in place:

- `deptrac.yaml` — layers + ruleset for the five-layer model.
- `phparkitect.php` — inward-only dependency rules + naming conventions.
- `.github/workflows/architecture.yml` — runs both checks on push/PR.

## Workflow

1. **Match the model.** Take the namespace→layer mapping and allowed-dependency matrix from
   `CLAUDE.md` / `ARCHITECTURE.md`. Adjust `App\` in both config files to the real namespace.
2. **Install the tools** (if not already):
   ```bash
   composer require --dev deptrac/deptrac phparkitect/phparkitect
   ```
   On dependency conflicts, use the PHARs (see the reference files).
3. **Tune `deptrac.yaml`** so every layer is covered; run with `--fail-on-uncovered` so
   unassigned files fail rather than hide. Details: `references/deptrac.md`.
4. **Tune `phparkitect.php`** for inward-only deps and project naming rules. Vocabulary:
   `references/phparkitect.md`.
5. **Add composer scripts:**
   ```json
   "scripts": {
     "arch:layers": "deptrac analyse --fail-on-uncovered",
     "arch:rules": "phparkitect check",
     "arch": ["@arch:layers", "@arch:rules"]
   }
   ```
6. **Run and resolve:** `composer arch`. For each violation, fix the dependency (usually:
   inject an interface so the arrow points inward) or baseline-and-document it in an ADR —
   never loosen a rule to go green.

## Rules of engagement

- Docs and config are one contract — layers/namespaces/allowed-deps must match `ARCHITECTURE.md`.
- No uncovered code (`--fail-on-uncovered`).
- Fix, don't weaken. Baseline honestly and record deliberate exceptions as ADRs.
- Every PHPArkitect rule carries a `->because()` explaining the constraint.

## Reference files

- `references/deptrac.md` — collectors, ruleset, commands, uncovered handling, baselines,
  and per-module rules. Read before editing `deptrac.yaml`.
- `references/phparkitect.md` — rule vocabulary, baselines, CLI. Read before editing
  `phparkitect.php`.
