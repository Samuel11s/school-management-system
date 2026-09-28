#!/bin/sh
# Boot sequence for the single-container image. Platforms like Render's free
# plan offer no shell or release phase, so first-run tasks happen here. Every
# step is idempotent and safe to repeat on each start.
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set; refusing to start." >&2
    exit 1
fi

sed "s/__PORT__/${PORT:-10000}/" /etc/nginx/nginx.conf.template > /tmp/nginx.conf

php artisan optimize --no-interaction

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
    php artisan db:seed --class=RolesAndPermissionsSeeder --force --no-interaction
fi

# Optional first administrator (skipped when the account already exists).
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    php artisan school:create-admin "$ADMIN_EMAIL" --name="${ADMIN_NAME:-Administrator}" \
        --password-env=ADMIN_PASSWORD --if-missing --no-interaction
fi

# Optional demo data for a public showcase (only loaded into an empty database).
if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    php artisan school:seed-demo --no-interaction
fi

exec supervisord -c /etc/supervisord.conf
