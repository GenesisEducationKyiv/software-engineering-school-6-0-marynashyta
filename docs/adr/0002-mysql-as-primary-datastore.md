# ADR-002: Use MySQL as the Primary Datastore

**Status:** Accepted

**Date:** 2026-05-07

**Author:** Maryna Shyta

## Context

The service needs persistent storage for subscription records. Each record contains:

- Subscriber email and GitHub repository (`owner/repo`)
- Confirmation and unsubscribe tokens (unique, randomly generated, 64-char hex)
- Subscription state (`confirmed`, `last_seen_tag`)

The data is strictly relational: uniqueness constraints are required across `(email, repo)` pairs and across both token columns. The background scanner reads all confirmed subscriptions in bulk every 5 minutes and updates `last_seen_tag` per row when a new release is detected.

## Considered Options

1. **MySQL 8.0**
   - Pros: ACID transactions, UNIQUE constraint enforcement at the DB level, `InnoDB` row-level locking for concurrent scanner writes, `utf8mb4` charset for full Unicode support.
   - Cons: Slightly heavier operational footprint than SQLite; horizontal write scaling requires sharding (not needed at this scale)

2. **PostgreSQL**
   - Pros: Richer feature set (better JSON, full-text search, CTEs), strong ACID guarantees
   - Cons: No meaningful feature advantage for this schema — the queries are simple CRUD and a bulk SELECT; no functional gain at current scale

3. **SQLite**
   - Pros: Zero-config, file-based, no separate service needed
   - Cons: Concurrent writes from the scanner and HTTP API processes are serialised at the file level; not suitable when two containers write simultaneously

4. **MongoDB**
   - Pros: Flexible document schema, easy horizontal scaling
   - Cons: The data model is naturally relational (unique constraints across fields, foreign-key-like token lookups); enforcing uniqueness and atomicity requires application-level logic that a relational database provides natively; no advantage for this access pattern

## Decision

MySQL 8.0 was chosen.

The schema is a single `subscriptions` table with `InnoDB` engine, `utf8mb4` charset, and three `UNIQUE KEY` constraints (`email+repo`, `confirm_token`, `unsubscribe_token`). All access goes through PDO prepared statements. The scanner bulk-reads confirmed rows and updates `last_seen_tag` in individual transactions — operations that require no advanced database features.

## Implications

**Positives:**

- Uniqueness constraints are enforced at the database level, preventing duplicate subscriptions even under concurrent requests
- `InnoDB` row-level locking lets the scanner and API write to different rows simultaneously without blocking each other
- Simple schema and access patterns make migrations, backups, and operational tooling straightforward

**Cons:**

- Adds a containerised database dependency; the service cannot run without MySQL being healthy
- Vertical scaling eventually hits limits — horizontal write scaling would require sharding, which is far beyond current needs
