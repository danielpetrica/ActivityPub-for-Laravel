#!/bin/bash
# dev.sh — Local development stack using compose.override.yml (local builds)

set -e

if [ ! -f .env ]; then
    echo ".env file not found!"
    exit 1
fi

source .env
APP_ENV=${APP_ENV:-local}

echo "=== Starting danielpetrica.com local dev (${APP_ENV}) ==="

echo "[1/4] Ensuring directory permissions..."
sudo mkdir -p .storage .redis .postgres-db
sudo chown -R 33:33 .storage
sudo chown -R 999:999 .redis .postgres-db

echo "[2/4] Building images..."
docker compose -f compose.yml -f compose.override.yml build --pull

echo "[3/4] Starting containers..."
docker compose -f compose.yml -f compose.override.yml up -d --remove-orphans

echo "[4/4] Running migrations & optimize..."
docker compose -f compose.yml -f compose.override.yml exec -T danielpetrica_com php artisan migrate --force
docker compose -f compose.yml -f compose.override.yml exec -u root -T danielpetrica_com chown -R www-data:www-data /app
docker compose -f compose.yml -f compose.override.yml exec -T danielpetrica_com php artisan optimize

echo "=== Dev stack ready ==="
docker compose ps
