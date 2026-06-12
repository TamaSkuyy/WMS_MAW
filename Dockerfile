FROM dunglas/frankenphp:1.4-php8.4

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

# Create storage symlink for public access (done at runtime via deploy script)
EXPOSE 8080

# Workers=2 for VPS (adjust if more CPU/RAM available)
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8080", "--workers=2", "--max-requests=500"]
