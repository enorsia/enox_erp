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
RUN_NPM="no"


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
        --npm=yes)
        RUN_NPM="yes"
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
echo -e "${YELLOW}📦 Fetching latest code from master...${NC}"
git fetch origin
git checkout master
git reset --hard origin/master

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
# NPM
########################################
if [ "$RUN_NPM" = "yes" ] && [ "$FAST_MODE" = "no" ]; then
    echo -e "${YELLOW}🧰 Installing npm dependencies...${NC}"
    npm install --omit=dev
else
    echo -e "${GREEN}⏭ Skipping npm install${NC}"
fi

########################################
# Asset Build
########################################
# if [ "$RUN_ASSETS" = "yes" ] && [ "$FAST_MODE" = "no" ]; then

#     echo -e "${YELLOW}🏗 Building assets safely...${NC}"

#     rm -rf public/build_new
#     VITE_BUILD_DIR=build_new npm run build

#     echo -e "${YELLOW}🔄 Swapping build folders atomically...${NC}"

#     rm -rf public/build_old 2>/dev/null || true
#     mv public/build public/build_old 2>/dev/null || true
#     mv public/build_new public/build
#     rm -rf public/build_old

# else
#     echo -e "${GREEN}⏭ Skipping asset build${NC}"
# fi


########################################
# Asset Build
########################################
if [ "$RUN_ASSETS" = "yes" ] && [ "$FAST_MODE" = "no" ]; then

    echo -e "${YELLOW}🏗 Building assets safely...${NC}"

    # Clean previous temporary build
    rm -rf public/build_new

    # Build assets
    if ! VITE_BUILD_DIR=build_new npm run build; then
        echo -e "${RED}❌ Asset compilation failed. Existing build preserved.${NC}"
        exit 1
    fi

    # Validate generated build
    if [ ! -d public/build_new ]; then
        echo -e "${RED}❌ Build directory not found: public/build_new${NC}"
        exit 1
    fi

    if [ -z "$(ls -A public/build_new 2>/dev/null)" ]; then
        echo -e "${RED}❌ Build directory is empty. Existing build preserved.${NC}"
        exit 1
    fi

    echo -e "${GREEN}✅ Asset build completed successfully.${NC}"

    echo -e "${YELLOW}🔄 Swapping build folders...${NC}"

    # Remove previous backup
    rm -rf public/build_old || {
        echo -e "${RED}❌ Failed to remove previous backup.${NC}"
        exit 1
    }

    # Move current build to backup
    if [ -d public/build ] || [ -L public/build ]; then

        if ! mv public/build public/build_old; then
            echo -e "${RED}❌ Failed to backup existing build. Deployment stopped.${NC}"
            exit 1
        fi

    fi

    # Activate new build
    if mv public/build_new public/build; then

        echo -e "${GREEN}✅ New build activated successfully.${NC}"

        # Remove old build after successful activation
        rm -rf public/build_old

        echo -e "${GREEN}✅ Old build backup cleaned successfully.${NC}"

    else

        echo -e "${RED}❌ Failed to activate new build. Starting rollback...${NC}"

        # Restore previous build
        if [ -d public/build_old ]; then

            if mv public/build_old public/build; then
                echo -e "${GREEN}✅ Previous build restored successfully.${NC}"
            else
                echo -e "${RED}❌ CRITICAL: Rollback failed. Manual recovery required.${NC}"
            fi

        fi

        exit 1

    fi

else

    echo -e "${GREEN}⏭ Skipping asset build.${NC}"

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

chown -R ukenorsia:www-data storage bootstrap/cache 2>/dev/null || true
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
# Ensure supervisor worker listens on exports queue:
#   php artisan queue:work --queue=exports,default
########################################
if [ "$RUN_SUPERVISOR" = "yes" ]; then
    echo -e "${YELLOW}🔄 Restarting workers...${NC}"

    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl restart enox_suite_queue:*
    sudo supervisorctl restart tracking-database-worker:*

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
