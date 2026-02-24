#!/bin/bash

GITFOLDER="../danielpetrica.com"
LOCALFOLDER=$(pwd)

source "$LOCALFOLDER/.env"

echo "*** Pulling repo."
cd "$GITFOLDER" || exit 1
git pull
cd "$LOCALFOLDER" || exit 1

echo "*** Copying files."
cp -f "${GITFOLDER}/compose.yml" "$LOCALFOLDER/compose.yml"
cp -f "$LOCALFOLDER/.env" "${GITFOLDER}/.env.${APP_ENV}"
cp -f "$LOCALFOLDER/run.sh" "${GITFOLDER}/run.sh"


echo "*** Check dirrect call."
# if script is called without --direct, re-invoke it with --direct and stop processing here
if [ "${1:-}" != "--direct" ]; then
    echo "*** Re-invoking run.sh with --direct to continue after self-update..."
    exec bash "$LOCALFOLDER/run.sh" --direct
fi

mkdir -p .storage .redis .postgres-db
# Ensure correct ownership
sudo chown -R 82:82 .storage
sudo chown -R 999:999 .redis .postgres-db

echo "*** Building docker."
SERVICE="danielpetrica_com_web_${APP_ENV}"

# Build images
docker compose build && \
    echo "*** Checking if $SERVICE is running to put app in maintenance..." && \
    if docker compose ps -q "$SERVICE" >/dev/null 2>&1 && [ -n "$(docker compose ps -q "$SERVICE")" ] && docker inspect -f '{{.State.Running}}' "$(docker compose ps -q "$SERVICE")" 2>/dev/null | grep -q true; then \
        docker compose exec -u root "$SERVICE" php artisan down --retry 2 --refresh 30; \
    else \
        echo "Service $SERVICE not running yet, skipping 'artisan down'."; \
    fi; \
    echo "*** Starting containers..." && \
    docker compose up -d --force-recreate && \
    echo "*** Waiting for $SERVICE to become healthy..." && \
    CID="$(docker compose ps -q "$SERVICE")" && \
    if [ -n "$CID" ]; then \
        # Wait up to ~5 minutes for health to be healthy (20x15s in compose.yml); use our own 180s max here.
        SECONDS_WAITED=0; \
        until [ "$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}running{{end}}' "$CID" 2>/dev/null)" = "healthy" ]; do \
            if [ $SECONDS_WAITED -ge 180 ]; then \
                echo "Timed out waiting for $SERVICE to be healthy."; \
                break; \
            fi; \
            sleep 5; \
            SECONDS_WAITED=$((SECONDS_WAITED+5)); \
        done; \
    fi && \
    docker compose exec -u root "$SERVICE" chown -R www-data:www-data /app && \
    docker compose exec "$SERVICE" php artisan migrate --force && \
    docker compose exec "$SERVICE" php artisan optimize && \
    docker compose exec -u root "$SERVICE" php artisan up


echo -en "\007"
