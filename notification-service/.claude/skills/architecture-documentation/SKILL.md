---
name: architecture-documentation
description: >-
  Map and document the architecture of a single plain PHP (no-framework) service: fill in
  docs/architecture/ARCHITECTURE.md, draw C4 diagrams, write the layered breakdown table,
  and record ADRs. Use this whenever the user wants to "map the architecture", "document the
  layers", "write an ADR", "diagram the service", or produce an architecture overview for
  THIS service — even without the word "documentation". Pairs with
  architecture-dependency-tests (which enforces the layers) and, at the platform level, with
  the platform-architecture skill (which aggregates every service into one landscape).
---

# Architecture documentation (per service)

Document one service's structure: its `docs/architecture/ARCHITECTURE.md`, C4 diagrams, the
**layer breakdown**, and ADRs explaining *why*. The documentation must describe the same
layer model the dependency tests enforce; if they disagree, that is a bug — reconcile them.
Keep the doc's `## 1. Overview` section tight and self-contained: the platform compiler
lifts it verbatim into the system landscape, so it should read well out of context.

## Scaffold already present

This service was scaffolded from the platform template, so these already exist at the root —
edit them in place rather than recreating:

- `docs/architecture/ARCHITECTURE.md` — the document to fill.
- `docs/architecture/decisions/0001-layered-architecture.md` — the first ADR (adjust dates/owners).
- `docs/architecture/decisions/adr-0000-template.md` — copy for each new ADR.

## Workflow

1. **Survey before writing.** Inspect `src/`; map each namespace/directory onto a layer
   (Domain / Application / Infrastructure / Presentation / Shared). Note anything that
   violates the Dependency Rule — those become findings here and failures in the tests.
2. **Fill `ARCHITECTURE.md`.** Complete every section; leave no `TODO` placeholders in a
   delivered doc. Keep the layer table byte-for-byte consistent with `deptrac.yaml` /
   `phparkitect.php` at the root.
3. **Draw the views with Mermaid** (Context, Container, Component). Arrows show the
   direction dependencies actually point — inward. Snippets: `references/c4-and-adr.md`.
4. **Write the layer breakdown** using the canonical descriptions in `references/layers.md`.
5. **Record decisions as ADRs**, numbered sequentially, immutable once accepted (supersede
   rather than edit). What makes a good ADR: `references/c4-and-adr.md`.
6. **Cross-link** to the ADR index and note that the layers are enforced via `composer arch`.

## Quality bar

- One skimmable `ARCHITECTURE.md` a new contributor could read to grasp the service.
- Diagrams show dependency direction; the layer table matches the enforced config exactly.
- A crisp `## 1. Overview` (the platform landscape reuses it).
- ADRs explain the *why*, including rejected alternatives.

## Reference files

- `references/layers.md` — canonical layer descriptions, the Dependency Rule, ports &
  adapters, common mistakes, and further reading. Read before writing the layer table.
- `references/c4-and-adr.md` — C4 levels with Mermaid snippets and how to write a good ADR.
  Read before drawing diagrams or writing decisions.
