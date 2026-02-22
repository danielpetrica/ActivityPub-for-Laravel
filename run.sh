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

# Ensure correct ownership
sudo chown -R 82:82 .storage
sudo chown -R 999:999 .redis .mysql-db

echo "*** Building docker."
docker compose build && \
    docker compose exec -u root danielpetrica_com_web_${APP_ENV} php artisan down --retry 30 --refresh 30 && \
    docker compose up -d --force-recreate && \
    docker compose exec -u root danielpetrica_com_web_${APP_ENV} chown -R www-data:www-data /app && \
    docker compose exec -u root danielpetrica_com_web_${APP_ENV} php artisan up && \
    docker compose exec danielpetrica_com_web_${APP_ENV} php artisan optimize && \
    docker compose exec danielpetrica_com_web_${APP_ENV} php artisan migrate --force

echo -en "\007"
