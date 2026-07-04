# Project rules — architecture & agent guidance (service)

Source of truth for how work is done in **this service** — one plain PHP (no-framework)
service within a larger microservice platform. Claude reads this at the start of every task.
Two skills under `.claude/skills/` handle documentation and dependency enforcement **inside**
this service. Cross-service concerns live in the platform repo, not here.

---

## 1. Service context

- Language: PHP 8.4+ (`declare(strict_types=1);` in every file).
- No framework. Composer PSR-4 autoloading; namespaces map 1:1 to directories.
- Root namespace: set this service's real namespace (e.g. `Acme\Orders`) → `src/`. Replace
  the placeholder `App\` in `deptrac.yaml` and `phparkitect.php` to match.
- This service owns its own datastore. It communicates with other services only through
  **published contracts** (HTTP/events), never by sharing a database or reaching into
  another service's code. Those rules are enforced at the platform level.

## 2. The architecture model (layered / clean)

Dependencies point **inward, toward the domain**. Inner layers never know about outer ones;
they define interfaces (ports) that outer layers implement (adapters).

| Layer | Namespace suffix | Responsibility | May depend on | Never on |
|---|---|---|---|---|
| Domain | `\Domain` | Entities, value objects, domain services, repository interfaces | `Shared` | Application, Infrastructure, Presentation |
| Application | `\Application` | Use cases / handlers, DTOs, port interfaces | Domain, Shared | Infrastructure, Presentation |
| Infrastructure | `\Infrastructure` | Adapters (PDO, HTTP, filesystem) implementing ports | Application, Domain, Shared | Presentation |
| Presentation | `\Presentation` | HTTP controllers, CLI commands, request/response mapping | Application, Domain, Shared | Infrastructure |
| Shared | `\Shared` | Tiny cross-cutting kernel | — | everything internal |

Suggested layout:

```
src/
  Domain/  Application/  Infrastructure/  Presentation/  Shared/
public/index.php     # front controller — the only place that wires concrete adapters
tests/
docs/architecture/   # ARCHITECTURE.md + decisions/ (ADRs)
```

## 3. Coding conventions

- `declare(strict_types=1);` everywhere. Classes `final` by default. Constructor injection
  only; no service location or global state; no `new` of an adapter inside Domain/Application.
- Depend on interfaces across layer boundaries. Value objects are immutable (`readonly`) and
  self-validating. Keep the Domain free of framework/ORM annotations.
- Names carry intent: `*Controller`, `*Command`, `*Handler`/`*UseCase`, `*RepositoryInterface`,
  `Pdo*Repository`, `*Dto`.

## 4. Skill routing

- **architecture-documentation** — map/diagram/describe this service; fill `ARCHITECTURE.md`
  and write ADRs.
- **architecture-dependency-tests** — configure Deptrac/PHPArkitect, run `composer arch`.
- Anything about boundaries **between** services (contracts, who-calls-whom, the landscape)
  is out of scope here — that belongs to the platform repo's `platform-architecture` skill.

## 5. Task playbook — map architecture + layer breakdown + dependency tests

1. **Survey** `src/`; fix the namespace→layer mapping for this service.
2. **Document** (architecture-documentation): fill `docs/architecture/ARCHITECTURE.md`
   (context, C4 views, layer table, key flows, cross-cutting concerns) and confirm ADR-0001.
3. **Enforce** (architecture-dependency-tests): tune `deptrac.yaml` + `phparkitect.php` to
   the real namespace; keep them identical to the doc's layer table.
4. **Wire** composer scripts (§ above) and confirm CI runs on push/PR.
5. **Verify**: `composer arch`. Fix violations or baseline-and-document — never loosen a rule.

### Definition of Done

- `ARCHITECTURE.md` filled; its `## 1. Overview` reads well standalone (the platform
  landscape reuses it); layer table matches the enforced config.
- ADR-0001 records the layering decision.
- `deptrac.yaml` + `phparkitect.php` cover every layer, no uncovered files.
- `composer arch` passes (or fails only on genuine, listed violations); CI green on push/PR.

## 6. Where the references live

Kept lean on purpose. Tool docs, install commands, and best-practice sources are
agent-loaded, next to the skill that needs them:

- Tooling (Deptrac, PHPArkitect) — `.claude/skills/architecture-dependency-tests/references/`
- Architecture theory & the layer model — `.claude/skills/architecture-documentation/references/layers.md`
- C4 diagrams & ADRs — `.claude/skills/architecture-documentation/references/c4-and-adr.md`
