# Use PHP-FPM as the base image
FROM php:8.2-fpm

# Install Additional System Dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    supervisor

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions including pcntl for Laravel Horizon and gd for PhpSpreadsheet
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql mysqli pdo_pgsql pgsql zip pcntl sockets gd

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set the working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install project dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

# Copy the application code
COPY . .

# Fix git ownership issue
RUN git config --global --add safe.directory /var/www/html || true

# Complete composer installation
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy custom PHP-FPM pool configuration to reduce CPU usage
COPY php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-custom.conf

# Copy supervisor configuration
COPY supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create log directory for supervisor
RUN mkdir -p /var/log/supervisor

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose PHP-FPM port and Reverb port
EXPOSE 9000 8080

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]