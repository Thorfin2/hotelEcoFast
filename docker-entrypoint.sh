#!/bin/bash
set -e

# Clear and warm up cache for production
php bin/console cache:clear --env=prod --no-debug 2>/dev/null || true
php bin/console cache:warmup --env=prod --no-debug 2>/dev/null || true

# Run database migrations
php bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null || true
php bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || true

# Load fixtures if database is empty (first deploy only)
TABLE_COUNT=$(php bin/console doctrine:query:sql "SELECT COUNT(*) as c FROM information_schema.tables WHERE table_schema = DATABASE()" --no-interaction 2>/dev/null | grep -o '[0-9]*' | tail -1 || echo "0")
if [ "$TABLE_COUNT" -lt "5" ]; then
    php bin/console doctrine:fixtures:load --no-interaction 2>/dev/null || true
fi

# Fix permissions
chown -R www-data:www-data var/ 2>/dev/null || true

exec "$@"
