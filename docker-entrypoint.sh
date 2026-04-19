#!/bin/bash
set -e

echo "==> Starting EcoFast Hotel..."

# Ensure var directory exists
mkdir -p var/cache var/log
chmod -R 777 var/ 2>/dev/null || true

# Clear cache
php bin/console cache:clear --env=prod --no-debug 2>/dev/null || true

# Run database setup (only if DATABASE_URL is set)
if [ -n "$DATABASE_URL" ]; then
    echo "==> Running database setup..."
    php bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null || true

    # schema:update gère tout : création initiale ET nouvelles colonnes
    php bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || true

    USER_COUNT=$(php bin/console doctrine:query:sql "SELECT COUNT(*) as c FROM user" --no-interaction 2>/dev/null | grep -o '[0-9]*' | head -1 || echo "0")
    if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
        echo "==> Loading fixtures..."
        php bin/console doctrine:fixtures:load --no-interaction 2>/dev/null || true
    fi

    # Toujours s'assurer que le compte admin principal existe
    echo "==> Initializing admin account..."
    php bin/console app:init-admin --no-interaction 2>/dev/null || true
else
    echo "==> No DATABASE_URL set, skipping database setup"
fi

echo "==> Starting PHP server on port ${PORT:-8080}..."
exec php -S 0.0.0.0:${PORT:-8080} -t public public/index.php
