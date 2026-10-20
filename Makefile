EXEC          := docker compose exec
EXEC_SYMFONY  := $(EXEC) -it -u www-data symfony
EXEC_COMPOSER := $(EXEC_SYMFONY) composer
PHP           := ${EXEC_SYMFONY} php
# the web client (front/), in a container of its own so that the node service may be stopped
NPM           := docker compose run --rm --no-deps -T node npm

install:
	$(EXEC_COMPOSER) install

update:
	$(EXEC_COMPOSER) update

build:
	docker compose build

reset:
	docker compose down --remove-orphans --volumes
	rm -rf var/ vendor/ public/bundles public/css public/js

up:
	docker compose up --detach

down:
	docker compose down --remove-orphans

sql:
	$(EXEC) -it database psql -U postgres

check-symfony:
	$(PHP) bin/console lint:container

rector:
	$(PHP) vendor/bin/rector

phpstan:
	$(PHP) vendor/bin/phpstan -v --memory-limit=1G

front-install:
	$(NPM) install

# type checking (vue-tsc), ESLint and Prettier
front-lint:
	$(NPM) run lint

front-test:
	$(NPM) test

# production build of the web client in front/dist
front-build:
	$(NPM) run build

lint: check-symfony rector phpstan front-lint

test: test-php front-test

test-php:
	$(PHP) vendor/bin/phpunit

# the database of the tests lives in memory: run it after starting the database_test service
db-test:
	$(PHP) bin/console --env=test doctrine:schema:drop -f
	$(PHP) bin/console --env=test doctrine:schema:create
	$(PHP) bin/console --env=test doctrine:fixtures:load -n

db:
	$(PHP) bin/console doctrine:schema:drop -f
	$(PHP) bin/console doctrine:schema:create
	$(PHP) bin/console doctrine:fixtures:load -n

sh:
	$(EXEC_SYMFONY) bash
