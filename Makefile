.PHONY: test test-unit test-integration test-e2e lint cs cs-fix

COMPOSE_TEST = docker compose -f docker-compose.test.yml
WAIT_API     = until curl -so /dev/null http://localhost:8080/; do echo "Waiting for API..."; sleep 2; done

INT_ENV  = API_BASE_URL=http://localhost:8080 DB_HOST=127.0.0.1 DB_PORT=3306 \
           DB_NAME=release_notifications DB_USER=app DB_PASS=secret API_KEY=""
E2E_ENV  = APP_URL=http://localhost:8080 API_KEY=""

test: test-unit
	$(COMPOSE_TEST) up -d --build
	@$(WAIT_API)
	$(INT_ENV) vendor/bin/phpunit -c phpunit.integration.xml --colors=always && \
	$(E2E_ENV) vendor/bin/phpunit -c phpunit.e2e.xml --colors=always; \
	$(COMPOSE_TEST) down -v

test-unit:
	composer test

test-integration:
	$(COMPOSE_TEST) up -d --build
	@$(WAIT_API)
	$(INT_ENV) vendor/bin/phpunit -c phpunit.integration.xml --colors=always
	$(COMPOSE_TEST) down -v

test-e2e:
	$(COMPOSE_TEST) up -d --build
	@$(WAIT_API)
	$(E2E_ENV) vendor/bin/phpunit -c phpunit.e2e.xml --colors=always
	$(COMPOSE_TEST) down -v

lint:
	composer lint

cs:
	composer cs

cs-fix:
	composer cs-fix
