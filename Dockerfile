FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql intl zip gd opcache \
    && (php -m | grep -q curl || docker-php-ext-install curl) \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP config
RUN printf "memory_limit=512M\nupload_max_filesize=10M\npost_max_size=10M\n" \
    > /usr/local/etc/php/conf.d/app.ini \
    && printf "opcache.enable=1\nopcache.memory_consumption=256\nopcache.max_accelerated_files=20000\nopcache.validate_timestamps=0\n" \
    > /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy all application files
COPY . .

# 1) Install packages sans scripts (évite cache:clear qui échoue sans DB)
RUN COMPOSER_MEMORY_LIMIT=-1 composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# 2) dump-autoload AVEC scripts → déclenche POST_AUTOLOAD_DUMP
#    → symfony/runtime génère vendor/autoload_runtime.php
RUN COMPOSER_MEMORY_LIMIT=-1 composer dump-autoload \
    --no-dev \
    --optimize \
    --no-interaction \
    && test -f vendor/autoload_runtime.php \
    && echo "✅ vendor/autoload_runtime.php OK"

# 3) Pre-warm cache Symfony (best-effort)
RUN mkdir -p var/cache var/log \
    && chmod -R 777 var/ \
    && APP_ENV=prod php bin/console cache:warmup --no-debug 2>/dev/null || true \
    && chmod -R 777 var/

ENV PORT=8080

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
