#!/bin/sh
set -e

# Remove Vite dev server marker if it somehow exists in production
rm -f /app/public/hot

php artisan view:clear

php artisan optimize

exec php artisan octane:frankenphp \
    --host=0.0.0.0 \
    --port=80 \
    --workers="${OCTANE_WORKERS:-4}" \
    --log-level=error \
    --max-requests=1000
