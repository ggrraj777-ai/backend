@echo off
echo ========================================
echo   GAUVA Platform - Laravel Server
echo   WiFi Network Access Mode
echo ========================================
echo.
echo Starting Laravel development server...
echo.
echo Server will be available at:
echo   - Local:   http://localhost:8000
echo   - Network: http://192.168.1.33:8000
echo.
echo Your PC IP: 192.168.1.33
echo Server Port: 8000
echo.
echo Make sure your mobile device is connected to the same WiFi
echo ========================================
echo.
echo Press Ctrl+C to stop the server
echo.

php artisan serve --host=0.0.0.0 --port=8000

