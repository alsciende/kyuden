COMPOSER := docker run --rm --interactive --tty --volume ${PWD}:/app --workdir /app composer
EXEC := docker compose exec --interactive php
PHP := ${EXEC} php

composer:
	docker run --rm --interactive --tty --volume ${PWD}:/app --workdir /app composer bash

up: install build

install:
	$(COMPOSER) install

build:
	docker compose up --build --detach

down:
	docker compose down --remove-orphans

reset:
	docker compose down --remove-orphans --volumes
	rm -rf vendor

check-symfony:
	$(PHP) bin/console lint:container

ecs:
	$(PHP) vendor/bin/ecs --fix

rector:
	$(PHP) vendor/bin/rector

phpstan:
	$(PHP) vendor/bin/phpstan -v --memory-limit=1G

lint: check-symfony ecs rector phpstan

test:
	$(PHP) vendor/bin/phpunit

db:
	$(PHP) bin/console doctrine:schema:drop -f
	$(PHP) bin/console doctrine:schema:create
	$(PHP) bin/console doctrine:fixtures:load -n

sh:
	$(EXEC) bash
