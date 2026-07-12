# Data Ownership & DB Migration Reference

## Table of Contents
1. [Data Ownership Principles](#1-data-ownership-principles)
2. [Migration Strategy: Three Phases](#2-migration-strategy-three-phases)
3. [Shared DB (Transitional) — SQL Examples](#3-shared-db-transitional--sql-examples)
4. [Schema Separation in the Same DB](#4-schema-separation-in-the-same-db)
5. [Full DB Separation](#5-full-db-separation)
6. [Data Synchronisation Scripts](#6-data-synchronisation-scripts)
7. [Zero-Downtime Table Migration](#7-zero-downtime-table-migration)

---

## 1. Data Ownership Principles

- **One service writes; others may read via events.** If two services write to the same table, you have a problem.
- **No cross-service JOINs.** Ever. If you need aggregated data, build a read model via events.
- **Identify the "system of record."** For every table/entity, ask: "Which service is the single authoritative source of truth?" That service owns the table.
- **Reference by ID, not by foreign key across services.** Store `order_id UUID` in the Billing DB, but never a foreign key constraint pointing to the Orders DB.

| Data | Owns (writes) | References (reads via events) |
|------|---------------|-------------------------------|
| Invoices | Billing | Orders (order_id), Identity (customer_id) |
| Orders | Orders | Catalog (product_id snapshot at time of order) |
| Customers | Identity | — |
| Products | Catalog | — |

---

## 2. Migration Strategy: Three Phases

### Phase 1 — Shared Database

Both monolith and new service connect to the same DB. The new service reads/writes directly.

**When to use**: First weeks of extraction. Zero data migration needed.
**When to exit**: Once the new service is stable; you can plan a proper separation.

```
Monolith ──► shared_db.invoices ◄── Billing Service
```

Risks:
- Schema changes require coordinating both apps
- No independent scaling of data layer
- Accidental coupling through DB queries

Mitigate by adding `X-` prefix on new service's new columns so you can track ownership.

### Phase 2 — Schema-per-Service in the Same DB

Each service operates in its own MySQL schema (`billing`, `orders`, `notifications`).
No direct cross-schema SQL from the application layer (enforce via DB users with limited GRANTs).

```sql
-- Create per-service schemas
CREATE SCHEMA IF NOT EXISTS billing;
CREATE SCHEMA IF NOT EXISTS orders;

-- Create service-specific DB users with access only to their schema
CREATE USER 'billing_svc'@'%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON billing.* TO 'billing_svc'@'%';
-- billing_svc has NO access to orders.* or the legacy monolith schema
```

**When to use**: Medium-term steady state. Easy operationally (one DB server), but still coupled at infrastructure level.

### Phase 3 — Separate Database Process (target)

Each service has its own DB server (or RDS instance, or managed DB).
Services share no DB infrastructure.

Use for: services with different scaling profiles, compliance isolation, or independent backup/restore requirements.

---

## 3. Shared DB (Transitional) — SQL Examples

### Data audit before migration

Before deciding ownership, run a query to see what tables exist and their size:

```sql
SELECT
    table_name,
    ROUND((data_length + index_length) / 1024 / 1024, 1) AS size_mb,
    table_rows
FROM information_schema.tables
WHERE table_schema = 'monolith_db'
ORDER BY size_mb DESC;
```

### Identify cross-table dependencies

```sql
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'monolith_db'
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME;
```

This shows you which tables will be hard to separate (lots of FK references = tight coupling).

---

## 4. Schema Separation in the Same DB

### Step 1 — Create the new schema and migrate tables

```sql
-- Create new billing schema
CREATE SCHEMA billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Copy table structure (no data yet)
CREATE TABLE billing.invoices LIKE monolith_db.invoices;

-- Copy data
INSERT INTO billing.invoices SELECT * FROM monolith_db.invoices;

-- Verify row counts match
SELECT
    (SELECT COUNT(*) FROM monolith_db.invoices) AS legacy_count,
    (SELECT COUNT(*) FROM billing.invoices) AS new_count;
```

### Step 2 — Add synchronisation trigger (temporary, during dual-write phase)

```sql
DELIMITER $$
CREATE TRIGGER monolith_db.sync_invoice_insert
AFTER INSERT ON monolith_db.invoices
FOR EACH ROW
BEGIN
    INSERT INTO billing.invoices VALUES (NEW.id, NEW.amount, NEW.status, NEW.created_at, NEW.updated_at)
    ON DUPLICATE KEY UPDATE
        amount = NEW.amount,
        status = NEW.status,
        updated_at = NEW.updated_at;
END$$
DELIMITER ;
```

Remove the trigger once the monolith stops writing to `monolith_db.invoices`.

---

## 5. Full DB Separation

### PHP migration script: copy data to new DB

```php
<?php
// bin/migrate-billing-data.php

declare(strict_types=1);

$source = new PDO(
    getenv('MONOLITH_DB_DSN'),
    getenv('MONOLITH_DB_USER'),
    getenv('MONOLITH_DB_PASS')
);
$target = new PDO(
    getenv('BILLING_DB_DSN'),
    getenv('BILLING_DB_USER'),
    getenv('BILLING_DB_PASS')
);

$batchSize = 1000;
$offset = 0;
$total = 0;

$target->beginTransaction();

do {
    $rows = $source->query(
        "SELECT id, order_id, amount_cents, currency, status, created_at, updated_at
         FROM invoices
         LIMIT $batchSize OFFSET $offset"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) break;

    $placeholders = implode(',', array_fill(0, count($rows), '(?,?,?,?,?,?,?)'));
    $stmt = $target->prepare(
        "INSERT IGNORE INTO invoices
         (id, order_id, amount_cents, currency, status, created_at, updated_at)
         VALUES $placeholders"
    );

    $values = [];
    foreach ($rows as $row) {
        array_push($values, ...array_values($row));
    }
    $stmt->execute($values);

    $total += count($rows);
    $offset += $batchSize;
    echo "Migrated $total rows...\n";

} while (count($rows) === $batchSize);

$target->commit();

echo "Migration complete. Total rows: $total\n";
```

Run this during a low-traffic window or while the monolith is in read-only mode.

---

## 6. Data Synchronisation Scripts

### Verify data parity after migration

```php
<?php
// bin/verify-parity.php

$sourceCount = (int) $source->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$targetCount = (int) $target->query('SELECT COUNT(*) FROM invoices')->fetchColumn();

if ($sourceCount !== $targetCount) {
    echo "MISMATCH: source=$sourceCount, target=$targetCount\n";
    exit(1);
}

// Spot-check a sample of rows using a checksum
$sourceChecksum = $source->query(
    'SELECT MD5(GROUP_CONCAT(id, amount_cents, status ORDER BY id)) AS chk FROM invoices LIMIT 1000'
)->fetchColumn();

$targetChecksum = $target->query(
    'SELECT MD5(GROUP_CONCAT(id, amount_cents, status ORDER BY id)) AS chk FROM invoices LIMIT 1000'
)->fetchColumn();

if ($sourceChecksum !== $targetChecksum) {
    echo "CHECKSUM MISMATCH in first 1000 rows\n";
    exit(1);
}

echo "Parity OK: $sourceCount rows, checksum match\n";
```

---

## 7. Zero-Downtime Table Migration

When you need to rename a column or change a type on a live table without downtime:

### The Expand–Contract pattern

**Phase 1 (Expand)**: Add the new column alongside the old one.
```sql
ALTER TABLE invoices ADD COLUMN amount_minor_units INT UNSIGNED AFTER amount;
```

Deploy code that writes to BOTH columns. Read from the old column.

**Phase 2 (Migrate)**: Backfill the new column.
```php
// Run in batches to avoid locking
$pdo->exec('UPDATE invoices SET amount_minor_units = amount * 100 WHERE amount_minor_units IS NULL LIMIT 10000');
```

**Phase 3 (Contract)**: Deploy code that reads from the NEW column. Writes still go to both.

**Phase 4 (Drop)**: Once confident, stop writing to old column. Drop it.
```sql
ALTER TABLE invoices DROP COLUMN amount;
```

Never use `ALTER TABLE` with `RENAME COLUMN` directly on large tables in production —
it blocks reads/writes for the duration of the migration on MySQL < 8.0 without `ALGORITHM=INSTANT`.

For MySQL 8.0+, most `ADD COLUMN` and `DROP COLUMN` operations support `ALGORITHM=INSTANT`:
```sql
ALTER TABLE invoices
    ADD COLUMN amount_minor_units INT UNSIGNED AFTER amount,
    ALGORITHM=INSTANT;
```