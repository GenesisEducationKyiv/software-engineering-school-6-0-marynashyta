---
name: php-ci-pipelines
description: Use when setting up GitHub Actions CI pipelines for a PHP project. Triggers on "CI", "pipeline", "GitHub Actions", "workflow", or automating tests on push/PR. Always apply when separating test types into independent jobs or spinning up Docker Compose in CI — even if the user just says "add CI". Does not cover writing the tests themselves.
---

# PHP CI Pipelines

Senior DevOps engineer setting up GitHub Actions with one workflow file per test type.
Unit, integration, and E2E run independently — different speed, different triggers.

## Core Workflow

1. **Create** three separate workflow files in `.github/workflows/`
2. **Unit** — bare runner, no Docker, fastest feedback
3. **Integration** — delegates to `docker-compose.test.yml` entirely
4. **E2E** — starts Docker app stack, then runs `phpunit -c phpunit.e2e.xml`

## Reference Guide

| Topic | File | Load When |
|-------|------|-----------|
| Complete YAML for all three workflows | `workflows.md` | Writing or updating any workflow file |

## Why Three Separate Files

| Pipeline | Time | Docker | Trigger |
|---|---|---|---|
| Unit | < 1 min | No | Every push / PR |
| Integration | 2–5 min | Full stack | Every push / PR |
| E2E | 5–15 min | App only | Push to `main` + PRs to `main` |

Separate files mean one pipeline failing doesn't block the others.

## Key Patterns

**Integration** — let Compose own everything:
```yaml
- run: |
    docker compose -f docker-compose.test.yml up \
      --build --abort-on-container-exit --exit-code-from test-runner
```

**E2E** — health poll before tests:
```yaml
- run: |
    for i in $(seq 1 30); do
      curl -sf http://localhost:8080/health && exit 0
      sleep 3
    done; exit 1
```

**Playwright browsers** — cache to avoid 200 MB re-download:
```yaml
- uses: actions/cache@v4
  with:
    path: ~/.cache/ms-playwright
    key: playwright-${{ runner.os }}-${{ hashFiles('composer.lock') }}
```

## Constraints

### MUST DO
- Use `--exit-code-from test-runner` so PHPUnit's exit code reaches CI
- Always `docker compose down -v` in an `if: always()` step
- Cache Composer deps (`vendor/`) keyed on `composer.lock`
- Upload E2E report as artifact on `if: always()`

### MUST NOT DO
- Run unit tests inside Docker — add unnecessary build time
- Mix test types in a single workflow job
- Hardcode secrets — use `${{ secrets.* }}`