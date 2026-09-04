FROM dunglas/frankenphp:1.12-php8.4

# Install additional PHP extensions
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    sockets \
    redis \
    intl \
    zip

# mysql client (mysqldump) — dibutuhkan spatie/laravel-backup (backup:run)
# dan langkah "backup wajib" pada fitur Pemutihan Data.
RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8080

# Workers=2 for VPS (adjust if more CPU/RAM available)
CMD ["php", "artisan", "octane:start", \
     "--server=frankenphp", \
     "--host=0.0.0.0", \
     "--port=8080", \
     "--workers=2", \
     "--max-requests=500"]
