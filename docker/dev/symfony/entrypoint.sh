#!/usr/bin/env sh

if [ ! -f vendor/autoload.php ]; then
    composer install
fi

# PHP-FPM listens on port 9000, behind the nginx service
php-fpm --nodaemonize

# PHP-FPM could not start: keep the container up, so that "docker compose exec symfony ..." can be used to fix it
echo "php-fpm failed: the container stays up for debugging"
exec tail -f /dev/null
