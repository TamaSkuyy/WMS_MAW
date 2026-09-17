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

# Batas PHP untuk import & halaman besar (memory_limit, upload, max_input_vars).
# Isinya ada di docker/php/zz-wms.ini — file yang SAMA juga di-bind-mount oleh
# docker-compose.prod.yml, jadi ops bisa mengubah limit tanpa rebuild image.
# Copy di sini supaya image tetap benar walau dijalankan tanpa compose
# (mis. `docker run` langsung).
COPY docker/php/zz-wms.ini /usr/local/etc/php/conf.d/zz-wms.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Buang cache bootstrap lama dari konteks build (mis. routes-v7.php/config.php)
# supaya rute terbaru — termasuk /health/ping — tidak tertimpa cache stale.
# Sekaligus siapkan direktori runtime storage yang di-ignore .dockerignore.
RUN rm -f bootstrap/cache/*.php \
    && mkdir -p storage/logs \
       storage/framework/cache/data \
       storage/framework/sessions \
       storage/framework/views \
       storage/framework/testing \
       storage/app/private/imports \
    && chmod -R 775 storage bootstrap/cache

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
