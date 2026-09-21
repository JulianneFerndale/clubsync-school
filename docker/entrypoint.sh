#!/bin/sh
set -e

# Render (and most PaaS) inject the port to listen on via $PORT.
: "${PORT:=8080}"

# Render the nginx server block with the runtime port.
sed "s/__PORT__/${PORT}/g" \
    /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

# Apply any pending migrations on boot (idempotent). Best-effort: if the DB is
# unreachable we still start so the app can serve the offline page rather than
# crash-loop. Disable by setting RUN_MIGRATIONS=false.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force || echo "entrypoint: migrate skipped/failed, continuing"
fi

# Cache config + views for production performance (best-effort).
# NOTE: route:cache is intentionally skipped — routes/web.php uses closures
# (e.g. /ping, /offline), which cannot be serialised by the route cache.
php artisan config:cache || true
php artisan view:cache   || true

# Start php-fpm (background) and nginx (foreground = container's main process).
php-fpm -D
exec nginx -g 'daemon off;'
