#!/usr/bin/env sh
# the database is brought up to date on every start, before PHP-FPM serves anything: a failed step stops the container
set -e

bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
bin/console app:cards:import --no-interaction
bin/console app:house-bot:create --no-interaction

exec "$@"
