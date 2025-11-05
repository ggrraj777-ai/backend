@echo off
echo ========================================
echo   GAUVA Platform - Laravel Server
echo ========================================
echo.
echo Starting Laravel development server...
echo Server will be available at: http://127.0.0.1:8000
echo Admin Panel: http://127.0.0.1:8000/admin
echo.
echo Press Ctrl+C to stop the server
echo ========================================
echo.

cd /d "%~dp0"
php artisan serve --host=127.0.0.1 --port=8000

