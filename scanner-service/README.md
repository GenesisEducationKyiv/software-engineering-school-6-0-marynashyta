# Service architecture template

Copy the **contents** of this folder into the root of a plain-PHP service repo to give it a
documented, enforced layered architecture and the Claude rules/skills to maintain it.

## What you get

```
CLAUDE.md                              # service-scoped rules, routing, task playbook
deptrac.yaml                           # layer-boundary enforcement (ready to use)
phparkitect.php                        # fine-grained rules (ready to use)
.github/workflows/architecture.yml     # runs both checks on push/PR
docs/architecture/
  ARCHITECTURE.md                      # fill this in
  decisions/0001-layered-architecture.md
  decisions/adr-0000-template.md
.claude/skills/
  architecture-documentation/          # SKILL.md + references/
  architecture-dependency-tests/       # SKILL.md + references/
```

## Adopt in 4 steps

1. Copy these files into the service repo root (merge `CLAUDE.md` if one already exists).
2. Replace the placeholder namespace `App\` with the service's real root namespace in
   `CLAUDE.md`, `deptrac.yaml`, and `phparkitect.php`.
3. Install tooling and add the composer scripts (see `CLAUDE.md` §5):
   ```bash
   composer require --dev deptrac/deptrac phparkitect/phparkitect
   ```
4. Register the service in the platform manifest (`platform/architecture.php`) so it appears
   in the compiled landscape.

Then ask Claude to "map this service's architecture and add the dependency tests" — it runs
the playbook in `CLAUDE.md`.

## Scope

These skills enforce boundaries **inside** one service. Boundaries **between** services
(contracts, database-per-service, the system landscape) live in the platform repo — see
`../platform/`.
