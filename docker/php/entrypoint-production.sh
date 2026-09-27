#!/bin/sh
# Production entrypoint: build Laravel's caches from the runtime environment
# (secrets are injected as environment variables, never baked into the image),
# then hand over to the container command (php-fpm, queue:work, ...).
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set; refusing to start." >&2
    exit 1
fi

php artisan optimize --no-interaction

exec "$@"
