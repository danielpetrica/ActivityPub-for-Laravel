#!/bin/bash
# deploy.sh — Pull pre-built images and perform zero-downtime deployment

set -e

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="/home/ubuntu/traefik/danielpetrica/danielpetrica.com"

# Self-update: if the repo has a newer version, replace and re-execute.
# This runs AFTER git pull (step 1), so the repo is always current.
SELF_SRC="$REPO_DIR/scripts/deploy.sh"
if [ -f "$SELF_SRC" ] && [ "$SELF_SRC" -nt "$APP_DIR/deploy.sh" ]; then
    cp "$SELF_SRC" "$APP_DIR/deploy.sh"
    exec "$APP_DIR/deploy.sh" "$@"
fi

source .env

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

NTFY_URL="${NTFY_URL:-}"

DEPLOY_START=$(date +%s)

if [ -n "$NTFY_URL" ]; then
  curl -s -H "Content-Type: application/json" \
    -d '{"message":"danielpetrica.com deployment started","title":"Deploy Started","priority":"low"}' \
    "$NTFY_URL" > /dev/null 2>&1 || true
fi

echo -e "${YELLOW}=== Deploying danielpetrica.com ===${NC}"

cd "${REPO_DIR}" || exit
echo -e "${GREEN}[1/7] Pulling latest code...${NC}"
git pull || true

# Self-update check again after pull (in case script was updated)
SELF_SRC="$REPO_DIR/scripts/deploy.sh"
if [ -f "$SELF_SRC" ] && [ "$SELF_SRC" -nt "$APP_DIR/deploy.sh" ]; then
    echo -e "${YELLOW}  (deploy.sh updated, restarting...)${NC}"
    cp "$SELF_SRC" "$APP_DIR/deploy.sh"
    exec "$APP_DIR/deploy.sh" "$@"
fi

cd "${APP_DIR}" || exit

# Ensure storage directories are writable by containers (Alpine-based: www-data = UID 82)
mkdir -p .storage/{logs,app,framework}
chown -R 82:82 .storage/ 2>/dev/null || true

# CI handles docker login before this script. For manual runs, allow GHCR_TOKEN.
echo -e "${GREEN}[2/7] Pulling images...${NC}"
if [ -n "${GHCR_TOKEN}" ]; then
    printf '%s\n' "${GHCR_TOKEN}" | docker login ghcr.io -u danielpetrica --password-stdin
fi
cp "$REPO_DIR/compose.yml" "$APP_DIR/compose.yml"
docker compose pull

echo -e "${GREEN}[3/7] Enabling maintenance mode...${NC}"
docker compose exec -T danielpetrica_com php artisan down --retry=60 2>/dev/null || \
  echo -e "${YELLOW}  (app not running yet, skipping)${NC}"

echo -e "${GREEN}[4/7] Stopping worker & scheduler...${NC}"
docker compose stop -t 30 worker scheduler 2>/dev/null || \
  echo -e "${YELLOW}  (not running yet, skipping)${NC}"

echo -e "${GREEN}[5/7] Starting containers...${NC}"
docker compose up -d --remove-orphans

echo -e "${GREEN}[6/7] Running migrations...${NC}"
docker compose exec -T danielpetrica_com php artisan migrate --force

echo -e "${GREEN}[7/7] Starting worker & scheduler...${NC}"
docker compose start worker scheduler

echo -e "${GREEN}  Disabling maintenance mode...${NC}"
docker compose exec -T danielpetrica_com php artisan up

DEPLOY_END=$(date +%s)
DURATION=$((DEPLOY_END - DEPLOY_START))

echo -e "${GREEN}=== Deployment complete! (${DURATION}s) ===${NC}"
docker compose ps

if [ -n "$NTFY_URL" ]; then
  curl -s -H "Content-Type: application/json" \
    -d "{\"message\":\"danielpetrica.com deployed in ${DURATION}s\",\"title\":\"Deploy Complete\"}" \
    "$NTFY_URL" > /dev/null 2>&1 || true
fi
