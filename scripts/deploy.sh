#!/bin/bash
# deploy.sh — Pull pre-built images and perform zero-downtime deployment

set -e
source .env

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="/home/ubuntu/traefik/danielpetrica/danielpetrica.com"
NTFY_URL="${NTFY_URL:-}"

DEPLOY_START=$(date +%s)

if [ -n "$NTFY_URL" ]; then
  curl -s -H "Content-Type: application/json" \
    -d '{"message":"danielpetrica.com deployment started","title":"\360\237\232\200 Deploy Started","priority":"low"}' \
    "$NTFY_URL" > /dev/null 2>&1 || true
fi

echo -e "${YELLOW}=== Deploying danielpetrica.com ===${NC}"

cd "${REPO_DIR}" || exit
echo -e "${GREEN}[1/7] Pulling latest code...${NC}"
git pull || true

cd "${APP_DIR}" || exit
echo -e "${GREEN}[2/7] Copying compose file & pulling images...${NC}"
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

echo -e "${GREEN}[6/7] Running migrations & optimize...${NC}"
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
    -d "{\"message\":\"danielpetrica.com deployed in ${DURATION}s\",\"title\":\"\342\234\205 Deploy Complete\"}" \
    "$NTFY_URL" > /dev/null 2>&1 || true
fi
