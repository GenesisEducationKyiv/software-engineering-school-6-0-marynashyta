---
name: php-testing-docs
description: Use when writing or generating a testing.md file for a PHP project. Triggers on "testing.md", "test instructions", "test documentation", "how to run tests", or when the user asks for a guide covering how to run unit, integration, and E2E tests. Always apply when the user wants developer docs for tests — even if they say "write a readme for tests" or "document how to run tests".
---

# PHP Testing Docs

Generate a `testing.md` at the project root covering all three test types.
The full template lives in `template.md` — copy and fill in project-specific values.

## Core Workflow

1. **Inspect** the project before writing:
    - `composer.json` `"scripts"` — use real script names, not placeholders
    - `docker-compose.e2e.yml` — find the host port
    - `src/` — find the health endpoint path (`/health`, `/ping`, etc.)
2. **Generate** `testing.md` from `template.md`
3. **Offer** a `Makefile` if none exists

## Reference Guide

| Topic | File | Load When |
|-------|------|-----------|
| Full `testing.md` template + Makefile | `template.md` | Always — load before generating output |

## Quick Reference Card

| Type | Command | Time | Needs Docker |
|---|---|---|---|
| Unit | `composer test:unit` | ~30 s | No |
| Integration | `composer test:integration` | ~3–5 min | Yes (full stack) |
| E2E | `composer test:e2e` | ~10 min | Yes (app only) |
| All | `make test` | — | Yes |

## Constraints

### MUST DO
- Read actual `composer.json` scripts before writing — never invent script names
- List **only** git, docker, and node as prerequisites (not PHP/Composer locally)
- Include one-command (`make test`) and per-type commands
- Note that first Docker build takes longer; subsequent runs use cache

### MUST NOT DO
- Copy placeholders into output — inspect and replace every `{{value}}`
- Create a separate `e2e/` Node.js project — tests are pure PHP
- List more than the three prerequisite tools