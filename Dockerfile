# Use PHP 8.3 with Apache
FROM php:8.3-apache

# Set environment variables
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PORT=8080
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_NO_INTERACTION=1

# Install system dependencies with error handling
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
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
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && update-ca-certificates \
    && ln -sf /etc/ssl/certs/ca-certificates.crt /usr/local/etc/ssl/cert.pem \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies with robust error handling
RUN set -ex && \
    echo "=== Installing Composer dependencies ===" && \
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --no-progress \
        --ignore-platform-reqs \
    && echo "✓ Composer dependencies installed successfully" \
    || (echo "ERROR: Composer install failed!" && composer diagnose && exit 1)

# Copy application files
COPY . /var/www/html

# Remove any existing .env to avoid invalid configuration being baked into the image
RUN rm -f /var/www/html/.env

# Ensure .env file exists (create default .env since we just deleted it)
RUN if [ ! -f /var/www/html/.env ]; then \
    echo "Creating .env file..." && \
    printf 'APP_NAME="%s"\nAPP_ENV="%s"\nAPP_KEY="%s"\nAPP_DEBUG="%s"\nAPP_URL="%s"\nAPP_TIMEZONE="%s"\nAPP_LOCALE="%s"\nLOG_CHANNEL="%s"\nLOG_LEVEL="%s"\n\nDB_CONNECTION="%s"\nDB_HOST="%s"\nDB_PORT="%s"\nDB_DATABASE="%s"\nDB_USERNAME="%s"\nDB_PASSWORD="%s"\n\nBROADCAST_DRIVER="%s"\nCACHE_DRIVER="%s"\nFILESYSTEM_DISK="%s"\nQUEUE_CONNECTION="%s"\nSESSION_DRIVER="%s"\nSESSION_LIFETIME="%s"\n\nMEMCACHED_HOST="%s"\nREDIS_HOST="%s"\nREDIS_PASSWORD="%s"\nREDIS_PORT="%s"\n\nMAIL_MAILER="%s"\nMAIL_HOST="%s"\nMAIL_PORT="%s"\nMAIL_USERNAME="%s"\nMAIL_PASSWORD="%s"\nMAIL_ENCRYPTION="%s"\nMAIL_FROM_ADDRESS="%s"\nMAIL_FROM_NAME="%s"\n\nAWS_ACCESS_KEY_ID="%s"\nAWS_SECRET_ACCESS_KEY="%s"\nAWS_DEFAULT_REGION="%s"\nAWS_BUCKET="%s"\n\nRAZORPAY_KEY_ID="%s"\nRAZORPAY_KEY_SECRET="%s"\nRECAPTCHA_SITE_KEY="%s"\nRECAPTCHA_SECRET_KEY="%s"\n\nPUSHER_APP_ID="%s"\nPUSHER_APP_KEY="%s"\nPUSHER_APP_SECRET="%s"\nPUSHER_HOST="%s"\nPUSHER_PORT="%s"\nPUSHER_SCHEME="%s"\nPUSHER_APP_CLUSTER="%s"\n' \
        "${APP_NAME:-Gauva}" \
        "${APP_ENV:-production}" \
        "${APP_KEY:-}" \
        "${APP_DEBUG:-false}" \
        "${APP_URL:-http://localhost}" \
        "${APP_TIMEZONE:-UTC}" \
        "${APP_LOCALE:-en}" \
        "${LOG_CHANNEL:-stderr}" \
        "${LOG_LEVEL:-info}" \
        "${DB_CONNECTION:-mysql}" \
        "${DB_HOST:-127.0.0.1}" \
        "${DB_PORT:-3306}" \
        "${DB_DATABASE:-homestead}" \
        "${DB_USERNAME:-homestead}" \
        "${DB_PASSWORD:-secret}" \
        "${BROADCAST_DRIVER:-log}" \
        "${CACHE_DRIVER:-file}" \
        "${FILESYSTEM_DISK:-public}" \
        "${QUEUE_CONNECTION:-sync}" \
        "${SESSION_DRIVER:-file}" \
        "${SESSION_LIFETIME:-120}" \
        "${MEMCACHED_HOST:-127.0.0.1}" \
        "${REDIS_HOST:-127.0.0.1}" \
        "${REDIS_PASSWORD:-null}" \
        "${REDIS_PORT:-6379}" \
        "${MAIL_MAILER:-smtp}" \
        "${MAIL_HOST:-smtp.mailtrap.io}" \
        "${MAIL_PORT:-2525}" \
        "${MAIL_USERNAME:-null}" \
        "${MAIL_PASSWORD:-null}" \
        "${MAIL_ENCRYPTION:-null}" \
        "${MAIL_FROM_ADDRESS:-hello@example.com}" \
        "${MAIL_FROM_NAME:-Gauva}" \
        "${AWS_ACCESS_KEY_ID:-}" \
        "${AWS_SECRET_ACCESS_KEY:-}" \
        "${AWS_DEFAULT_REGION:-us-east-1}" \
        "${AWS_BUCKET:-}" \
        "${RAZORPAY_KEY_ID:-}" \
        "${RAZORPAY_KEY_SECRET:-}" \
        "${RECAPTCHA_SITE_KEY:-}" \
        "${RECAPTCHA_SECRET_KEY:-}" \
        "${PUSHER_APP_ID:-}" \
        "${PUSHER_APP_KEY:-}" \
        "${PUSHER_APP_SECRET:-}" \
        "${PUSHER_HOST:-}" \
        "${PUSHER_PORT:-}" \
        "${PUSHER_SCHEME:-}" \
        "${PUSHER_APP_CLUSTER:-mt1}" > /var/www/html/.env; \
    fi

# Run composer scripts after all files are copied
RUN set -ex && \
    echo "=== Running Composer scripts ===" && \
    composer dump-autoload --optimize --no-scripts \
    && echo "✓ Autoload optimized successfully"

# Install Node.js (with error handling)
RUN set -ex && \
    echo "=== Installing Node.js ===" && \
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    node --version && npm --version && \
    echo "✓ Node.js installed successfully" \
    || echo "Warning: Node.js installation skipped"

# Install Node dependencies and build assets (optional, won't fail build)
RUN if [ -f "package.json" ]; then \
        echo "=== Building frontend assets ===" && \
        npm install --legacy-peer-deps --no-audit 2>&1 && \
        (npm run prod 2>&1 || npm run build 2>&1 || echo "Warning: Asset build skipped") && \
        echo "✓ Frontend assets processed"; \
    else \
        echo "No package.json found, skipping npm build"; \
    fi || echo "Warning: Frontend build failed but continuing..."

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
# Ensure .env file exists (should already be created in Dockerfile, but verify)\n\
if [ ! -f /var/www/html/.env ]; then\n\
    echo "Creating minimal .env file..."\n\
    if [ -f /var/www/html/.env.example ]; then\n\
        cp /var/www/html/.env.example /var/www/html/.env\n\
    else\n\
        echo "APP_NAME=Gauva" > /var/www/html/.env\n\
        echo "APP_ENV=production" >> /var/www/html/.env\n\
        echo "APP_KEY=" >> /var/www/html/.env\n\
        echo "APP_DEBUG=false" >> /var/www/html/.env\n\
    fi\n\
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
php artisan package:discover --ansi || true\n\
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
