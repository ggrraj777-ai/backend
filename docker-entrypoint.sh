#!/bin/bash
set -e

# Wait for database to be ready (if needed)
echo "Starting DriveMond application..."

# Set proper permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache configuration
echo "Optimizing application..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache configurations for production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Run database migrations (optional, uncomment if needed)
# php artisan migrate --force

# Create storage link if not exists
php artisan storage:link || true

echo "Application ready! Listening on port 8080"

# Execute the main command
exec "$@"
