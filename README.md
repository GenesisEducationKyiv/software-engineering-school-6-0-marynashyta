# Release Notification API

A monolithic PHP service that lets users subscribe to GitHub repository release notifications via email.

## How it works

1. A user subscribes with their email and a GitHub repository (`owner/repo`).
2. The service validates the repository exists via the GitHub API, then saves a pending subscription and sends a confirmation email.
3. Once the user clicks the confirmation link, the subscription becomes active and the current latest release tag is stored as `last_seen_tag` — preventing notifications for releases that already existed at subscription time.
4. A background scanner runs every 5 minutes, fetches the latest release for each active subscription, and sends an email when a new tag is detected.
5. Every notification email contains a one-click unsubscribe link.

## Prerequisites

- Docker and Docker Compose

## Quick start

```bash
cp .env.example .env
docker compose up --build
```

| Service                 | URL                          |
| ----------------------- | ---------------------------- |
| API                     | <http://localhost:8080>      |
| Swagger UI              | <http://localhost:8090>      |
| Mailpit (email preview) | <http://localhost:8025>      |
| Prometheus              | <http://localhost:9090>      |
| Grafana                 | <http://localhost:3000>      |

### ELK log aggregation (optional)

Start the ELK stack on top of the base stack:

```bash
docker compose -f docker-compose.yml -f docker-compose.elk.yml up -d
```

| Service       | URL                     |
| ------------- | ----------------------- |
| Kibana        | <http://localhost:5601> |
| Elasticsearch | <http://localhost:9200> |

Filebeat ships logs from both the `api` and `scanner` containers into Elasticsearch under the index pattern `release-api-logs-*`. If the Kibana data view is missing after first start: **Stack Management → Data Views → Create data view** → index pattern `release-api-logs-*`, time field `@timestamp`.

## API reference

See [swagger.yaml](swagger.yaml) for the full contract. Quick summary:

| Method   | Path                        | Description                               |
| -------- | --------------------------- | ----------------------------------------- |
| `POST`   | `/api/subscribe`            | Subscribe an email to a repository        |
| `GET`    | `/api/confirm/{token}`      | Confirm a pending subscription            |
| `GET`    | `/api/unsubscribe/{token}`  | Unsubscribe                               |
| `GET`    | `/api/subscriptions?email=` | List confirmed subscriptions for an email |
| `GET`    | `/metrics`                  | Prometheus metrics (always public)        |

### Subscribe

```bash
curl -X POST http://localhost:8080/api/subscribe \
  -H "Content-Type: application/json" \
  -d '{"email": "you@example.com", "repo": "owner/repo"}'
```

### List subscriptions

```bash
curl "http://localhost:8080/api/subscriptions?email=you@example.com"
```

## GitHub rate limiting

The scanner handles `429 Too Many Requests` responses from GitHub gracefully — it reads the `Retry-After` header and sleeps for that duration before continuing. GitHub API responses are also cached in Redis with a 10-minute TTL, significantly reducing the number of outbound requests.

Without a `GITHUB_TOKEN` the limit is 60 requests/hour. With a token it is 5 000/hour.

## Running tests

Three test suites with increasing scope:

```bash
composer test          # unit tests — fast, no Docker
make test-integration  # integration tests — starts Docker stack automatically
make test-e2e          # E2E browser tests — starts Docker stack + Playwright (PHP)
make test              # all three suites in one go
```

Integration and E2E tests use `docker-compose.test.yml` as the stack.
See [docs/testing.md](docs/testing.md) for full setup instructions and coverage details.

## CI

Four independent GitHub Actions workflows — each reports a separate status check:

| Workflow | Trigger | What it does |
| --- | --- | --- |
| `ci.yml` | Every push / PR | PHPStan level 9 + PHPCS → Docker build & Trivy scan |
| `unit.yml` | Every push / PR | PHPUnit unit tests (~30 s, no Docker) |
| `integration.yml` | Every push / PR | API integration tests against real MySQL + Redis |
| `e2e.yml` | Push / PR to `main` | Playwright browser tests |
