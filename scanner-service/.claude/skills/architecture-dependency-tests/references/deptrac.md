# Deptrac reference

`deptrac/deptrac` — static analysis that enforces dependency rules between architectural
layers. Requires PHP 8.1+. Docs: https://deptrac.github.io/deptrac/ · Repo:
https://github.com/deptrac/deptrac

## Install

```bash
composer require --dev deptrac/deptrac
```

If it conflicts with project dependencies, use the PHAR from the GitHub releases page or
PHIVE instead.

## How it works

Three concepts:

- **Layers** — named groups of classes, defined by **collectors** (a collector matches
  class-like tokens, e.g. by namespace regex).
- **Ruleset** — for each layer, the list of layers it is *allowed* to depend on. Anything
  not listed is a violation. `~` (null) means "may depend on nothing".
- **Violations** — a dependency from layer A to layer B where the ruleset doesn't permit it.

Deptrac parses `src/`, assigns each class to a layer via collectors, then checks every
dependency against the ruleset.

## Config file (`deptrac.yaml`)

Root key is `deptrac`. Minimal shape for the five-layer model:

```yaml
deptrac:
  paths:
    - ./src
  layers:
    - name: Domain
      collectors:
        - type: classLike
          value: ^App\\Domain\\.*
    # ... one layer per architectural layer ...
  ruleset:
    Domain:
      - Shared
    Application:
      - Domain
      - Shared
    # Domain not listed under itself as a dependency is fine — same-layer is always allowed.
    Shared: ~
```

Notes:
- `classLike` collectors match the fully-qualified class name by regex. Escape backslashes
  (`\\`) in YAML. This is the most robust collector for PSR-4 plain-PHP projects.
- Same-layer dependencies are always allowed; you only list *other* layers a layer may use.
- There are other collector types (`directory`, `bool`, `implements`, `extends`,
  `attribute`, …) if you need to slice differently — see the Collectors page in the docs.

## Commands

```bash
# Analyse (British and American spellings both work)
vendor/bin/deptrac analyse
vendor/bin/deptrac analyze

# Fail if any file isn't assigned to a layer (recommended in CI)
vendor/bin/deptrac analyse --fail-on-uncovered

# Report format (text is default; others include table, json, graphviz, mermaid, ...)
vendor/bin/deptrac analyse --formatter=mermaidjs
```

## Uncovered code

By default, code not matched by any layer is reported as "Uncovered", not failed. That's a
gap: unassigned code can violate rules invisibly. Always define layers that cover the whole
source tree and run with `--fail-on-uncovered` so gaps surface as failures.

## Baselines

If an existing codebase already has violations you can't fix immediately, generate a
baseline so current violations are ignored while **new** ones still fail:

```bash
vendor/bin/deptrac analyse --formatter=baseline > deptrac.baseline.yaml
```

Reference it from the config and treat every baselined item as tech debt to burn down —
ideally with an ADR explaining why it exists.

## Per-module boundaries (optional, advanced)

A single layer ruleset treats `Domain` across *all* modules as one layer, so it won't stop
module A's domain from being used by module B. If the project is a modular monolith, add a
second Deptrac config that defines a layer per **module** and a ruleset for which modules
may integrate. Run both configs. See the Deptrac docs on multiple rulesets.
