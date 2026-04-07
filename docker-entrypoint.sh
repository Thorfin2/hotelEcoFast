#!/bin/bash
set -e

echo "==> Starting EcoFast Hotel..."

# Fix Apache MPM at runtime - force only prefork
rm -f /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_event.load
rm -f /etc/apache2/mods-enabled/mpm_worker.conf /etc/apache2/mods-enabled/mpm_worker.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf 2>/dev/null || true
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load 2>/dev/null || true

# Set Apache to listen on Railway's PORT
sed -i "s/Listen 80/Listen ${PORT:-8080}/g" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT:-8080}/g" /etc/apache2/sites-available/000-default.conf

# Ensure var directory exists
mkdir -p var/cache var/log
chown -R www-data:www-data var/ 2>/dev/null || true

# Clear cache
php bin/console cache:clear --env=prod --no-debug 2>/dev/null || true

# Run database setup (only if DATABASE_URL is set)
if [ -n "$DATABASE_URL" ]; then
    echo "==> Running database setup..."
    php bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null || true
    php bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || true

    USER_COUNT=$(php bin/console doctrine:query:sql "SELECT COUNT(*) as c FROM user" --no-interaction 2>/dev/null | grep -o '[0-9]*' | head -1 || echo "0")
    if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
        echo "==> Loading fixtures..."
        php bin/console doctrine:fixtures:load --no-interaction 2>/dev/null || true
    fi
else
    echo "==> No DATABASE_URL set, skipping database setup"
fi

chown -R www-data:www-data var/ 2>/dev/null || true

echo "==> Starting Apache on port ${PORT:-8080}..."
exec "$@"
