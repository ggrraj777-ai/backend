# Use PHP 8.3 with Apache
FROM php:8.3-apache

# Set environment variables
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PORT=8080
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_NO_INTERACTION=1

# Install system dependencies
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    build-essential \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    ca-certificates \
    libicu-dev

# Build PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Update certificates
RUN update-ca-certificates && \
    ln -sf /etc/ssl/certs/ca-certificates.crt /usr/local/etc/ssl/cert.pem

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --no-progress \
    --ignore-platform-reqs

# Copy application files
COPY . /var/www/html

# Remove any existing .env to avoid invalid configuration being baked into the image
RUN rm -f /var/www/html/.env

# Create minimal .env file (will be overridden by environment variables at runtime)
RUN printf 'APP_ENV=production\nAPP_DEBUG=false\nLOG_CHANNEL=stderr\n' > /var/www/html/.env

# Optimize autoloader
RUN composer dump-autoload --optimize --no-scripts

# Install Node.js and build assets, then cleanup
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    if [ -f "package.json" ]; then \
        npm ci --legacy-peer-deps --no-audit --production=false && \
        npm run prod 2>/dev/null || npm run build 2>/dev/null || true; \
    fi && \
    apt-get purge -y --auto-remove nodejs build-essential && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /root/.npm

# Ensure public directory has correct permissions
RUN chmod -R 755 /var/www/html/public

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Configure Apache (port will be set dynamically at runtime)
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
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

# Create production startup script
RUN echo '#!/bin/bash\n\
set -e\n\
export PORT=${PORT:-8080}\n\
\n\
# Configure Apache for dynamic port\n\
echo "Listen $PORT" > /etc/apache2/ports.conf\n\
sed -i "s/<VirtualHost.*>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf\n\
\n\
# Ensure .env exists\n\
if [ ! -f /var/www/html/.env ]; then\n\
    [ -f /var/www/html/.env.example ] && cp /var/www/html/.env.example /var/www/html/.env || \
    printf "APP_ENV=production\nAPP_DEBUG=false\nLOG_CHANNEL=stderr\n" > /var/www/html/.env\n\
fi\n\
\n\
# Set permissions\n\
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/.env 2>/dev/null || true\n\
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true\n\
chmod 644 /var/www/html/.env 2>/dev/null || true\n\
\n\
# Generate APP_KEY if needed\n\
if ! grep -q "APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then\n\
    php artisan key:generate --force --no-interaction 2>/dev/null || true\n\
fi\n\
\n\
# Optimize Laravel for production\n\
php artisan config:clear 2>/dev/null || true\n\
php artisan cache:clear 2>/dev/null || true\n\
php artisan route:clear 2>/dev/null || true\n\
php artisan view:clear 2>/dev/null || true\n\
php artisan storage:link 2>/dev/null || true\n\
\n\
if [ "$APP_ENV" = "production" ]; then\n\
    php artisan config:cache 2>/dev/null || true\n\
    php artisan route:cache 2>/dev/null || true\n\
    php artisan view:cache 2>/dev/null || true\n\
fi\n\
\n\
php artisan package:discover --ansi 2>/dev/null || true\n\
\n\
exec apache2-foreground' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Start Apache
CMD ["/usr/local/bin/start.sh"]
