#!/bin/bash

set -e
trap 'echo -e "\e[31m❌ Error occurred at line $LINENO\e[0m"; exit 1' ERR

START_TIME=$(date +%s)

########################################
# Colors
########################################
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

########################################
# Default flags
########################################
RUN_COMPOSER="no"
RUN_ASSETS="no"
RUN_MIGRATIONS="no"
FAST_MODE="no"
RUN_SUPERVISOR="no"

########################################
# Read parameters
########################################
for arg in "$@"
do
    case $arg in
        --composer=yes)
        RUN_COMPOSER="yes"
        ;;
        --assets=yes)
        RUN_ASSETS="yes"
        ;;
        --migrate=yes)
        RUN_MIGRATIONS="yes"
        ;;
        --supervisor=yes)
        RUN_SUPERVISOR="yes"
        ;;
        --fast)
        FAST_MODE="yes"
        ;;
    esac
done

echo -e "${GREEN}🚀 Starting deployment...${NC}"

########################################
# Project directory
########################################
cd /srv/enox_erp || { echo -e "${RED}❌ Project folder not found${NC}"; exit 1; }

########################################
# Git update
########################################
echo -e "${YELLOW}📦 Fetching latest code...${NC}"
git fetch origin
BRANCH=$(git rev-parse --abbrev-ref HEAD)
echo -e "${YELLOW}🔀 Switching to branch $BRANCH...${NC}"
git reset --hard origin/$BRANCH

########################################
# Composer
########################################
if [ "$RUN_COMPOSER" = "yes" ] && [ "$FAST_MODE" = "no" ]; then
    echo -e "${YELLOW}🧰 Installing composer dependencies...${NC}"
    composer install --no-dev --optimize-autoloader
else
    echo -e "${GREEN}⏭ Skipping composer install${NC}"
fi

########################################
# Asset Build
########################################
if [ "$RUN_ASSETS" = "yes" ] && [ "$FAST_MODE" = "no" ]; then

    echo -e "${YELLOW}🏗 Building assets safely...${NC}"

    rm -rf public/build_new
    VITE_BUILD_DIR=build_new npm run build

    echo -e "${YELLOW}🔄 Swapping build folders atomically...${NC}"

    rm -rf public/build_old 2>/dev/null || true
    mv public/build public/build_old 2>/dev/null || true
    mv public/build_new public/build
    rm -rf public/build_old

else
    echo -e "${GREEN}⏭ Skipping asset build${NC}"
fi

########################################
# Custom Migrations
########################################
if [ "$RUN_MIGRATIONS" = "yes" ] && [ "$FAST_MODE" = "no" ]; then

    echo -e "${YELLOW}🛠 Running migrations...${NC}"

    bash run_migrations.sh

else
    echo -e "${GREEN}⏭ Skipping migrations${NC}"
fi

########################################
# Fix permissions
########################################
echo -e "${YELLOW}⚙️ Fixing permissions...${NC}"

chown -R enorsiauk_server_2:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chmod -R g+rw storage/logs/*.log 2>/dev/null || true

########################################
# Clear Laravel caches
########################################
echo -e "${YELLOW}🧹 Clearing caches...${NC}"

sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan event:clear

########################################
# Rebuild optimized caches
########################################
echo -e "${YELLOW}📦 Building optimized caches...${NC}"

sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache


########################################
# restart queue workers (optional)
########################################
if [ "$RUN_SUPERVISOR" = "yes" ]; then
    echo -e "${YELLOW}🔄 Restarting workers...${NC}"

    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl restart enox_suite_queue:*

    echo -e "${GREEN}✅ Worker restarted${NC}"
else
    echo -e "${GREEN}⏭ Skipping supervisor restart${NC}"
fi


########################################
# Deployment Time
########################################
END_TIME=$(date +%s)
DEPLOY_TIME=$((END_TIME - START_TIME))

echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo -e "${GREEN}⏱ Deploy time: ${DEPLOY_TIME} seconds${NC}"
