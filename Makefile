.PHONY: test test-unit test-integration test-e2e lint cs cs-fix proto-lint proto-generate

COMPOSE_TEST = docker compose -f docker-compose.test.yml

test: test-unit
	$(COMPOSE_TEST) up -d --build --wait
	vendor/bin/phpunit -c phpunit.integration.xml --colors=always && \
	vendor/bin/phpunit -c phpunit.e2e.xml --colors=always; \
	$(COMPOSE_TEST) down -v

test-unit:
	composer test

test-integration:
	$(COMPOSE_TEST) up -d --build --wait
	vendor/bin/phpunit -c phpunit.integration.xml --colors=always; \
	EXIT=$$?; $(COMPOSE_TEST) down -v; exit $$EXIT

test-e2e:
	$(COMPOSE_TEST) up -d --build --wait
	vendor/bin/phpunit -c phpunit.e2e.xml --colors=always; \
	EXIT=$$?; $(COMPOSE_TEST) down -v; exit $$EXIT

lint:
	composer lint

cs:
	composer cs

cs-fix:
	composer cs-fix

proto-lint:
	buf lint

proto-generate:
	buf generate
	@echo "PHP stubs written to generated/"
