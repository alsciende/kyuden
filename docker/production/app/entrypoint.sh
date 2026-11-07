#!/usr/bin/env sh
# the database is brought up to date on every start of the application, before FrankenPHP serves anything: a failed step
# stops the container. The worker (another command: messenger:consume) starts once the application is healthy
# (compose.yaml), and does not run these steps again
set -e

if [ "$1" = "frankenphp" ]; then
    bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    bin/console app:cards:import --no-interaction
    bin/console app:house-bot:create --no-interaction
fi

exec "$@"
