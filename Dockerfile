FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create user sail (matches script)
RUN groupadd -g 1000 sail && useradd -u 1000 -ms /bin/bash -g sail sail

WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R sail:sail /var/www/html

# Copy RoadRunner
COPY --from=ghcr.io/roadrunner-server/roadrunner:2023.3 /usr/bin/rr /usr/local/bin/rr

USER sail

RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8080

CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8080", "--rpc-port=6001"]
