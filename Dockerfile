FROM dunglas/frankenphp:8.2-php

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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create storage symlink for public access
RUN php artisan storage:link || true

# Optimize Laravel
RUN php artisan optimize || true

EXPOSE 8080

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8080", "--workers=4", "--max-requests=500"]
