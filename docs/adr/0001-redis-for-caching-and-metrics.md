# ADR-001: Use Redis for Caching and Metrics Storage

**Status:** Accepted

**Date:** 2026-05-07

**Author:** Maryna Shyta

## Context

The service needs a fast, shared store accessible by two separate processes (the HTTP API container and the background scanner container) for two purposes:

- Caching GitHub API responses to stay within rate limits (60 req/hour unauthenticated, 5,000 with a token)
- Storing Prometheus-style counters (HTTP requests, GitHub API calls, notifications sent) with atomic increment semantics

## Considered Options

1. **Redis**
   - Pros: Fast in-memory store, native atomic `INCR`/`HINCRBY` for labeled counters, TTL support for cache expiry, shared across containers, mature ecosystem
   - Cons: Extra infrastructure dependency, counters lost on restart (no disk persistence by default), single node is a SPOF

2. **APCu**
   - Pros: Zero extra infrastructure, very fast (in-process)
   - Cons: Memory is per-process — the API container and scanner container cannot share it; not viable for cross-process caching or metrics

3. **Memcached**
   - Pros: Simple, fast, shared across containers, good for key-value caching
   - Cons: No atomic hash-increment operation (`HINCRBY` equivalent), so labeled metrics are not possible without application-level locking; no TTL-based counter storage

## Decision

Redis was chosen.

Used for two roles with a single instance:

- **Caching**: `SETEX`/`GET` with a 10-minute TTL per repository; a sentinel value (`__null__`) prevents repeated cache misses for repos with no releases
- **Metrics**: `INCR` for scalar counters, `HINCRBY`/`HGETALL` on hashes for labeled counters (HTTP method/route/status, GitHub endpoint/cache hit)

The client wrapper (`RedisCache`) degrades silently to no-ops on any Redis failure, so a Redis outage causes cache misses and zeroed metrics — not a service outage.

## Implications

**Positives:**

- GitHub API call volume is cut proportionally to cache hit rate, keeping the service within rate limits at scale
- Atomic Redis commands eliminate race conditions between concurrent requests and between the API and scanner processes
- No write pressure added to MySQL for metrics
- One infrastructure dependency serves both caching and metrics needs

**Cons:**

- Metrics counters reset to zero if Redis restarts, causing a discontinuity in Prometheus time series
- Adds operational overhead (health checks, monitoring) that a metrics-less design would not require
