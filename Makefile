.PHONY: up down migrate reset-db scenarios smoke openapi mysql-shell php-shell redis-shell

up:
	test -f .env || cp .env.example .env
	docker compose up -d --build

down:
	docker compose down

migrate:
	docker compose exec php-fpm php bin/migrate.php

reset-db:
	@set -eu; \
	trap 'docker compose start worker nginx' EXIT; \
	docker compose stop worker nginx; \
	docker compose exec -T redis redis-cli FLUSHDB; \
	docker compose exec -T mysql sh -lc 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE" -e "DROP TABLE IF EXISTS payment_attempts; DROP TABLE IF EXISTS payments;"'; \
	docker compose exec -T php-fpm php bin/migrate.php

scenarios:
	docker compose exec -e APP_BASE_URL=http://nginx php-fpm php client/scenarios.php

openapi:
	docker compose exec php-fpm ./vendor/bin/openapi src -o openapi.yaml

mysql-shell:
	docker compose exec mysql sh -lc 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

php-shell:
	docker compose exec php-fpm sh

redis-shell:
	docker compose exec redis redis-cli
