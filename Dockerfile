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

# Install PHP dependencies with better error handling
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist || \
    (echo "Composer install failed! Trying with --no-scripts..." && \
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist)

# Copy application files
COPY . /var/www/html

# Create .env file from .env.example
RUN cp .env.example .env || echo "APP_NAME=DriveMond" > .env

# Set APP_URL in .env for proper asset URLs
RUN sed -i 's|APP_URL=.*|APP_URL=https://gauva-798219755346.europe-west1.run.app|g' .env || true

# Run composer scripts
RUN composer dump-autoload --optimize

# Install Node.js (required for asset compilation)
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

# Install Node dependencies and build assets (with fallback)
RUN if [ -f "package.json" ]; then \
    echo "Installing Node dependencies..." && \
    npm install --legacy-peer-deps || npm install || echo "NPM install skipped" && \
    npm run prod || npm run build || echo "NPM build skipped"; \
    fi

# Ensure public directory has correct permissions
RUN chmod -R 755 /var/www/html/public

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

# Add custom Apache configuration
RUN echo '<Directory /var/www/html/public>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n\
\n\
# Enable serving static files\n\
<FilesMatch "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$">\n\
    Header set Cache-Control "max-age=31536000, public"\n\
</FilesMatch>' >> /etc/apache2/sites-available/000-default.conf

# Expose port 8080
EXPOSE 8080

# Create startup script inline
RUN echo '#!/bin/bash\n\
set -e\n\
echo "Starting DriveMond on port 8080..."\n\
\n\
# Ensure .env file exists\n\
if [ ! -f /var/www/html/.env ]; then\n\
    echo "Creating .env file..."\n\
    cp /var/www/html/.env.example /var/www/html/.env || echo "APP_NAME=DriveMond" > /var/www/html/.env\n\
fi\n\
\n\
# Set permissions\n\
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/.env\n\
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache\n\
chmod 644 /var/www/html/.env\n\
\n\
# Generate APP_KEY if not set\n\
if ! grep -q "APP_KEY=base64:" /var/www/html/.env; then\n\
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
# Optimize for production\n\
if [ "$APP_ENV" = "production" ]; then\n\
    php artisan config:cache || true\n\
    php artisan route:cache || true\n\
    php artisan view:cache || true\n\
fi\n\
\n\
echo "Application ready on port 8080"\n\
echo "APP_ENV: $APP_ENV"\n\
echo "APP_DEBUG: $APP_DEBUG"\n\
echo "Checking Apache..."\n\
apache2ctl -t || echo "Apache config test failed but continuing..."\n\
\n\
exec apache2-foreground' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

# Start Apache
CMD ["/usr/local/bin/start.sh"]
