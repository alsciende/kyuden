EXEC          := docker compose exec
EXEC_SYMFONY  := $(EXEC) -it -u www-data -e http_proxy=${http_proxy} -e https_proxy=${https_proxy} -e no_proxy=${no_proxy} symfony
EXEC_COMPOSER := $(EXEC_SYMFONY) composer
PHP           := ${EXEC_SYMFONY} php

install:
	$(COMPOSER) install

update:
	$(COMPOSER) update

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
	$(EXEC) -it mysql mysql -u root -ppasswd

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
	$(EXEC_SYMFONY) bash
