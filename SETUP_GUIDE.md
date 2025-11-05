# DriveMond Application Setup Guide

## Prerequisites Installed ✓
- PHP 8.2.12
- Composer 2.8.9
- Node.js v20.18.0
- npm 10.8.2

## Setup Steps

### 1. Environment Configuration
The `.env` file should be configured with:
```
APP_NAME=DriveMond
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gauva_db
DB_USERNAME=root
DB_PASSWORD=
```

### 2. Installation Commands
Run these commands in order:

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Generate application key
php artisan key:generate

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run database migrations (make sure MySQL is running)
php artisan migrate

# Build frontend assets
npm run dev

# Start the development server
php artisan serve --port=8080
```

### 3. Access Points
Once the server is running:

- **Main Application**: http://localhost:8080
- **Admin Panel**: http://localhost:8080/admin
- **Admin Dashboard**: http://localhost:8080/admin/dashboard
- **API Documentation**: Check `/docs` or `/api/documentation` routes

### 4. Database Setup
1. Open XAMPP Control Panel
2. Start Apache and MySQL
3. Open phpMyAdmin: http://localhost/phpmyadmin
4. Create a new database named `gauva_db`
5. Run migrations: `php artisan migrate`

### 5. Troubleshooting

#### Port Already in Use
If port 8080 is already in use, try:
```bash
php artisan serve --port=8000
```

#### Database Connection Error
- Verify MySQL is running in XAMPP
- Check database credentials in `.env`
- Ensure database `gauva_db` exists

#### Permission Errors (Windows)
Run Command Prompt as Administrator and execute:
```bash
icacls storage /grant "IUSR:(OI)(CI)F" /T
icacls bootstrap\cache /grant "IUSR:(OI)(CI)F" /T
```

#### Clear All Caches
```bash
php artisan optimize:clear
```

### 6. Production Deployment Notes
For production deployment (like Firebase/Google Cloud):
- Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- Run `npm run prod` to build optimized assets
- Ensure the web server points to the `public` directory
- The `.htaccess` files have been configured for proper routing

## File Changes Made
1. Created `/public/.htaccess` - Handles routing for Laravel
2. Updated `/.htaccess` - Redirects requests to public directory
3. Updated `/server.php` - Configured for port 8080 support
4. Created this setup guide

## Next Steps
1. Run the installation commands above
2. Access the admin panel at http://localhost:8080/admin
3. Configure your application settings
4. Start developing!
