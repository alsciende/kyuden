COMPOSER := docker run --rm --interactive --tty --volume ${PWD}:/app --workdir /app composer
EXEC := docker compose exec --interactive php
PHP := ${EXEC} php

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

lint:
	$(PHP) bin/console lint:container
	$(PHP) vendor/bin/ecs --fix
#	$(PHP) vendor/bin/rector
	$(PHP) vendor/bin/phpstan -v --memory-limit=1G

test:
	$(PHP) vendor/bin/phpunit

db:
	$(PHP) bin/console doctrine:schema:drop -f
	$(PHP) bin/console doctrine:schema:create
	$(PHP) bin/console doctrine:fixtures:load -n
