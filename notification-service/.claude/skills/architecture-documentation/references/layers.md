# Layers reference (plain PHP)

Canonical description of the layered / clean architecture used in this project. Keep the
`ARCHITECTURE.md` layer table and the enforced tool config (`deptrac.yaml`,
`phparkitect.php`) consistent with everything here.

## Table of contents

1. The Dependency Rule
2. The five layers
3. Ports & adapters
4. Where wiring happens
5. Common mistakes to flag

---

## 1. The Dependency Rule

**Source-code dependencies point inward, toward the domain.** Draw the layers as
concentric rings — Domain at the centre, then Application, then Infrastructure and
Presentation on the outside. A `use` / `new` / type-hint may reference the same ring or a
ring further **in**, never further **out**. When an inner layer needs something from the
outside world (a database, a clock, an email sender), it defines an **interface** and an
outer layer supplies the implementation. This is what keeps business rules testable in
isolation and independent of frameworks, I/O, and delivery mechanisms.

## 2. The five layers

### Domain (`App\Domain`) — the core
The business model and rules, expressed in plain PHP with no outward dependencies (only
`App\Shared`).
- **Contains:** entities, value objects (immutable, self-validating), domain services,
  domain events, and **repository interfaces** (e.g. `UserRepositoryInterface`).
- **Never contains:** SQL/PDO, HTTP, filesystem, framework classes, ORM annotations, or
  any reference to `Application`, `Infrastructure`, or `Presentation`.
- Test in complete isolation — no database, no container.

### Application (`App\Application`) — use cases
Orchestrates the domain to fulfil a single application-specific action.
- **Contains:** use case / command & query handlers (`RegisterUserHandler`), application
  services, DTOs (input/output data structures), and **port interfaces** for things the
  use case needs from the outside (e.g. `Clock`, `Mailer`, `TransactionManager`).
- **May depend on:** `Domain`, `Shared`.
- **Never depends on:** `Infrastructure`, `Presentation`. It speaks to the outside world
  only through interfaces.

### Infrastructure (`App\Infrastructure`) — adapters
Concrete implementations of the interfaces declared inward.
- **Contains:** `PdoUserRepository implements UserRepositoryInterface`, HTTP clients,
  filesystem adapters, queue/mailer/cache adapters, mapping between persistence rows and
  domain objects.
- **May depend on:** `Application`, `Domain`, `Shared` (it implements their interfaces),
  plus third-party libraries.
- This is the only layer that "knows" about specific technologies.

### Presentation (`App\Presentation`) — delivery
How the outside world triggers use cases.
- **Contains:** HTTP controllers/actions, CLI commands, request→DTO and result→response
  mapping, view/templating.
- **May depend on:** `Application` (to invoke use cases), `Domain` (to read value objects
  in responses), `Shared`.
- **Never depends on:** `Infrastructure` directly — it receives collaborators via
  constructor injection wired at the edge.

### Shared (`App\Shared`) — cross-cutting kernel
A deliberately **small** set of things genuinely common to all layers (e.g. a base
`Assert` helper, a shared `Uuid` value type, common contracts). Depends on nothing
internal. Resist the urge to dump grab-bag utilities here; a bloated Shared layer becomes a
hidden coupling point.

## 3. Ports & adapters

- A **port** is an interface owned by an inner layer describing a capability it needs
  (driven port, e.g. `UserRepositoryInterface`) or offers (driving port, e.g. a use case
  interface).
- An **adapter** is an outer-layer class implementing that port for a specific technology
  (`PdoUserRepository`) or translating an external trigger into a use case call
  (`RegisterUserController`).
- The inner layer depends on the port; the adapter depends on the port too. Neither
  depends on the other's concrete class. This inversion is exactly what the dependency
  tests verify.

## 4. Where wiring happens

Concrete adapters are chosen and injected in **one** place at the outermost edge — the
front controller (`public/index.php`) or a small composition root / DI container it uses.
Inner layers receive fully-constructed collaborators through their constructors and never
instantiate an adapter themselves. If you find `new PdoUserRepository(...)` inside a use
case, that's a violation.

## 5. Common mistakes to flag

- **Domain importing infrastructure** — an entity that reaches for PDO or a framework
  request object. The most serious violation; fix first.
- **Application depending on a concrete repository** instead of its interface.
- **Presentation talking to the database** directly, bypassing the use case.
- **Anemic vs god objects** — entities with only getters/setters and all logic in
  services, or a single class doing everything. Note as a design smell.
- **A fat Shared layer** used as a dumping ground, quietly coupling everything.
- **Framework/ORM annotations in the Domain**, tying business rules to a library.

## Further reading

- **Clean Architecture** and the Dependency Rule — Robert C. Martin.
- **Hexagonal Architecture (Ports & Adapters)** — Alistair Cockburn.
- **Onion Architecture** — Jeffrey Palermo.
- **PSR-4** (autoloading) and **PSR-12** (coding style) — https://www.php-fig.org
