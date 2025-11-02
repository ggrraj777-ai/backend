# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Set environment variables
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PORT=8080

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application files
COPY . /var/www/html

# Run composer scripts
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Configure Apache for port 8080
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Enable Apache modules
RUN a2enmod rewrite headers

# Add Apache configuration for Laravel
RUN echo '<Directory /var/www/html/public>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/sites-available/000-default.conf

# Expose port 8080
EXPOSE 8080

# Create startup script inline
RUN echo '#!/bin/bash\n\
set -e\n\
echo "Starting DriveMond on port 8080..."\n\
\n\
# Set permissions\n\
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache\n\
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache\n\
\n\
# Generate APP_KEY if not set\n\
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:aj6UCF3URvpY7oC92LcoKuDKWJqP2u5LKgSOBTP8mFQ=" ]; then\n\
    echo "Generating new APP_KEY..."\n\
    php artisan key:generate --force --no-interaction\n\
fi\n\
\n\
# Clear all caches\n\
php artisan config:clear || true\n\
php artisan cache:clear || true\n\
php artisan route:clear || true\n\
php artisan view:clear || true\n\
\n\
# Create storage link\n\
php artisan storage:link || true\n\
\n\
# Cache config for production\n\
if [ "$APP_ENV" = "production" ]; then\n\
    php artisan config:cache || true\n\
    php artisan route:cache || true\n\
    php artisan view:cache || true\n\
fi\n\
\n\
echo "Application ready on port 8080"\n\
echo "APP_ENV: $APP_ENV"\n\
echo "APP_DEBUG: $APP_DEBUG"\n\
\n\
exec apache2-foreground' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

# Start Apache
CMD ["/usr/local/bin/start.sh"]
