---
name: php-domain-discovery
description: Analyse a PHP codebase to identify bounded contexts and business domains — triggers on "find our domains", "map bounded contexts", "where does X belong", "DDD for PHP", "too much coupling — where do we start", or any request to audit architecture before refactoring.
---

# PHP Domain Discovery

Analyse the codebase and produce a bounded-context map before any restructuring begins.
This is Step 1 of the modular refactoring pipeline — output feeds directly into `php-module-structure`.

---

## Process

### 1. Read the codebase

Inspect all of the following before drawing any conclusions:
- `composer.json` → `autoload.psr-4` (namespace → path mapping)
- Directory tree 2–3 levels deep
- Entry points: `public/index.php`, `routes/`, `artisan`, `bin/console`
- Existing `Services/`, `Repositories/`, `Models/` folders
- Migration files — table names reveal domain ownership better than class names

Also run git history analysis to find files that change together:
```bash
git log --oneline --stat | grep -E '^\s+\S+\.php' | sort | uniq -c | sort -rn | head -30
# files that always change together = same domain
```

### 2. Collect business nouns

List every significant noun from class names, table names, route paths, and method names.
Ignore purely technical nouns (`Manager`, `Helper`, `Utils`, `Handler` on their own).

Examples of business nouns: `User`, `Order`, `Invoice`, `Product`, `Shipment`, `Notification`.

### 3. Cluster into bounded contexts

Group nouns by **shared lifecycle and shared reason to change**:
- Nouns created/deleted together → same domain
- Nouns changed by different business rules or different teams → different domains

Standard domain candidates in web apps:

| Domain | Typical nouns |
|--------|--------------|
| Identity / Auth | User, Role, Session, Token, Permission |
| Catalog | Product, Category, Attribute, Price |
| Orders | Cart, Order, OrderLine, Checkout |
| Billing | Invoice, Payment, Refund, Receipt |
| Notifications | Email, SMS, Template, Delivery |
| Reporting | Report, Export, Metric, Dashboard |

### 4. For each domain, document

```
Domain: Billing
  Owns:     invoices table, payments table
  Reads:    orders.order_id, identity.customer_id
  Emits:    InvoicePaid, RefundIssued
  Receives: OrderPlaced (triggers invoice creation)
```

### 5. Draw the context map

Produce a dependency diagram showing event/data flow between domains.
Arrows show direction of data flow, not class imports.

```
Identity ◄── Orders ──► Billing ──► Notifications
              │                         ▲
              └── Catalog               │
                                   InvoicePaid
```

### 6. Flag pain points

Note any of the following and which domain they contaminate:
- God classes (> 500 lines, > 10 public methods on a single concept)
- Cross-domain table writes (`OrderController` writing to `invoices` table)
- Missing domain — behaviour spread across 5+ files with no clear home
- Naming drift — same concept called different things in different files

### 7. Assess whether the codebase already has clean structure

If modules already exist and boundaries are respected, say so explicitly.
Do not invent problems. A well-structured codebase needs confirmation, not restructuring.

---

## Output format

Save the output to `docs/architecture/domain-map.md` and commit it — ephemeral analysis is lost analysis.

The document must contain:
1. **Domain list** — name, owns, reads, emits, receives
2. **Context map** — ASCII diagram
3. **Pain point inventory** — file/class → what's wrong → which domain owns the fix
4. **Assessment** — is the codebase already well-structured, partially structured, or a monolith?
5. **Recommended next step** — `php-module-structure` to restructure, or `php-microservice-extraction` if a domain is already isolated

---

## Done checklist

- [ ] `docs/architecture/domain-map.md` written and committed
- [ ] Every table assigned to exactly one domain
- [ ] Every god class flagged with its owning domain
- [ ] Context map diagram included
- [ ] Next skill identified and noted at the bottom of the document

---

## Reference

→ `references/domain-identification.md` — heuristics, anti-patterns, event-storming shortcut,
domain scorecard, and an annotated e-commerce example.