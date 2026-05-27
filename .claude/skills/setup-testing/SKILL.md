---
name: setup-testing
description: Full PHP testing setup — integration, unit, E2E, CI, and docs in one go
disable-model-invocation: true
---

Complete the full testing setup for this PHP project in order:

**Step 1 — Integration tests**
/php-integration-tests
Write integration tests for every API endpoint found in src/.

**Step 2 — Unit tests**
/php-unit-tests
Identify complex domain classes and write unit tests for them.

**Step 3 — E2E tests**
/php-e2e-playwright
Write E2E tests for every page that has a user flow.

**Step 4 — CI pipelines**
/php-ci-pipelines
Create .github/workflows/ with separate unit, integration, and E2E pipelines.

**Step 5 — Docs**
/php-testing-docs
Generate testing.md. Inspect composer.json and docker-compose files first.

After each step, confirm the files were created before moving to the next.