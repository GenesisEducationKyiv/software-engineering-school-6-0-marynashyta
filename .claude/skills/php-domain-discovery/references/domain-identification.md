# Domain Identification Reference

## Heuristics for Finding Domain Boundaries

### 1. The "different teams" heuristic
If you imagine scaling your company, which parts of this codebase would different teams own?
Marketing doesn't care how payments work; Finance doesn't care about the product catalog.
Each team's area of ownership ≈ one bounded context.

### 2. The "data dictionary" heuristic
List every noun in the codebase. Group nouns that always travel together and have the same
lifecycle. Nouns that have very different lifecycles (created/deleted at different times,
by different actors) belong to different domains.

### 3. The "who changes this?" heuristic
When a business rule changes, which files need to change together? If changing a concept
(e.g. "how discounts work") always touches files in 3+ different top-level folders, those
folders are probably one domain split incorrectly.

### 4. The "event storming" shortcut
List all domain **events** your system produces or reacts to (past-tense business facts):
- `OrderPlaced`, `OrderShipped`, `OrderCancelled` → Orders domain
- `InvoiceCreated`, `PaymentReceived`, `RefundIssued` → Billing domain
- `UserRegistered`, `PasswordReset`, `AccountDeleted` → Identity domain
- `EmailSent`, `SMSDelivered`, `PushOpened` → Notifications domain

Each event cluster = one domain candidate.

---

## Common Anti-Patterns to Avoid

### "Technical" domain splitting (wrong)
```
src/
├── Models/          ← All Eloquent models from all domains
├── Controllers/     ← All controllers from all domains
├── Services/        ← All service classes from all domains
└── Repositories/    ← All repos from all domains
```
This groups by **technical role** rather than **business concern**. Any change to a feature
touches 4+ directories. This is the main thing to fix.

### God-module
One module called `Core`, `Common`, or `Shared` that contains 60% of the business logic.
This is still a monolith inside the module system.

**Fix**: `SharedKernel` should contain only truly cross-cutting technical abstractions
(base Entity, DomainEvent interface, EventBus interface). Business logic always goes
in a specific domain module.

### Chatty cross-domain coupling
```php
// Orders module calling directly into Billing module internals — WRONG
use App\Modules\Billing\Infrastructure\StripeGateway;
$gateway = new StripeGateway();
```
**Fix**: Declare an interface in `SharedKernel` or in the `Orders` module, inject it, and
let the `Billing` module register its implementation.

---

## Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Domain / module folder | `PascalCase` noun | `Billing`, `OrderManagement` |
| Bounded context namespace | `App\Modules\{Domain}` | `App\Modules\Billing` |
| Domain events | Past-tense fact | `InvoicePaid`, `OrderShipped` |
| Commands (application layer) | Imperative + noun | `CreateInvoice`, `ShipOrder` |
| Queries | `Get` / `Find` + noun | `GetInvoiceById`, `FindOrdersByCustomer` |
| Repositories (interface) | Noun + `Repository` | `InvoiceRepository` |
| Domain services | Noun + `Service` | `DiscountCalculator`, `TaxRateResolver` |

---

## Example: E-Commerce Bounded Context Map

```
┌─────────────────┐     reads user     ┌──────────────────┐
│    Identity     │◄───────────────────│     Orders       │
│  User, Session  │                    │  Cart, Checkout  │
└─────────────────┘                    └────────┬─────────┘
                                                │ OrderPlaced event
┌─────────────────┐     invoice for    ┌────────▼─────────┐
│    Catalog      │     order items    │    Billing       │
│ Product, Price  │◄───────────────────│ Invoice, Payment │
└─────────────────┘                    └────────┬─────────┘
                                                │ InvoicePaid event
┌─────────────────┐◄───────────────────────────┘
│  Notifications  │
│  Email, SMS     │
└─────────────────┘
```

Arrows show **data flow direction**, not dependency direction.
Each box owns its own tables and publishes events on state changes.

---

## Domain Scorecard

Score each domain candidate on these dimensions (1–5 scale):

| Dimension | Question |
|-----------|---------|
| **Cohesion** | Do all concepts in this group change for the same reasons? |
| **Encapsulation** | Can I understand this domain without reading other domains? |
| **Stability** | Does this domain's public contract change infrequently? |
| **Testability** | Can I test this domain in isolation? |
| **Team alignment** | Does this map to how humans actually talk about the business? |

Domains scoring < 3 on Cohesion need to be split further.
Domains scoring < 3 on Stability are poor microservice candidates (too risky to extract).