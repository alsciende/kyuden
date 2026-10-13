EXEC          := docker compose --env-file proxy.env exec
EXEC_SYMFONY  := $(EXEC) -it -u www-data symfony
EXEC_COMPOSER := $(EXEC_SYMFONY) composer
PHP           := ${EXEC_SYMFONY} php

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

lint: check-symfony rector phpstan

test:
	$(PHP) vendor/bin/phpunit

db:
	$(PHP) bin/console doctrine:schema:drop -f
	$(PHP) bin/console doctrine:schema:create
	$(PHP) bin/console doctrine:fixtures:load -n

sh:
	$(EXEC_SYMFONY) bash
