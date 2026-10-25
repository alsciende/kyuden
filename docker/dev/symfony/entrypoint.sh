#!/usr/bin/env sh

if [ ! -f vendor/autoload.php ]; then
    composer install
fi

# FrankenPHP listens on port 80 (docker/dev/symfony/Caddyfile)
frankenphp run --config /etc/frankenphp/Caddyfile

# FrankenPHP could not start: keep the container up, so that "docker compose exec symfony ..." can be used to fix it
echo "frankenphp failed: the container stays up for debugging"
exec tail -f /dev/null
