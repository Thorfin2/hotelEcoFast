FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql intl zip gd opcache \
    && (php -m | grep -q curl || docker-php-ext-install curl) \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP config (avant composer pour le memory_limit)
RUN printf "memory_limit=512M\nupload_max_filesize=10M\npost_max_size=10M\n" \
    > /usr/local/etc/php/conf.d/app.ini \
    && printf "opcache.enable=1\nopcache.memory_consumption=256\nopcache.max_accelerated_files=20000\nopcache.validate_timestamps=0\n" \
    > /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies (no dev) — memory_limit élevé pour éviter les OOM sur Railway
RUN COMPOSER_MEMORY_LIMIT=-1 composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# Copy the rest of the application
COPY . .

# Create var directory and pre-warm cache (best-effort)
RUN mkdir -p var/cache var/log \
    && chmod -R 777 var/ \
    && APP_ENV=prod COMPOSER_MEMORY_LIMIT=-1 composer run-script post-install-cmd --no-interaction 2>/dev/null || true \
    && chmod -R 777 var/

ENV PORT=8080

# Entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
