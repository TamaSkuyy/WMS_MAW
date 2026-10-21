# ── Base image: WAJIB FrankenPHP >= 1.13.2 ───────────────────────────────────
# PHP2Shell (CVE-2026-109618 & CVE-2026-109619, CVSS 9.2, RCE tanpa autentikasi)
# diperbaiki di PHP 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12 (rilis darurat 21-22 Okt
# 2026). FrankenPHP 1.13.2 membangun ulang image-nya dengan PHP versi tsb.
#
# JANGAN kembali ke tag `1.12-php8.4`: tag itu TIDAK di-rebuild upstream
# (terakhir 2026-09-25, masih PHP 8.4.25 = RENTAN), jadi `docker pull` /
# rebuild dengan tag itu tetap menghasilkan image yang belum dipatch.
# Docker Hub `1.13-php8.4` saat ini ber-digest sama dengan `1.13.2-php8.4.27`.
#
# Pin versi lengkap (bukan `1.13-php8.4`) supaya PHP di dalam image bisa
# diaudit. Patch PHP berikutnya = ubah baris ini dengan sengaja, lalu:
#   ./deploy-production.sh --build
FROM dunglas/frankenphp:1.13.2-php8.4.27

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
