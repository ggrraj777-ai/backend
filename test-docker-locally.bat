@echo off
echo ======================================
echo Testing Docker Image Locally
echo ======================================
echo.

REM Check if Docker is installed
where docker >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Error: Docker is not installed.
    echo Please install Docker Desktop from: https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

echo Step 1: Building Docker image...
docker build -t drivemond-test .

if %ERRORLEVEL% NEQ 0 (
    echo Error: Docker build failed!
    pause
    exit /b 1
)

echo.
echo Step 2: Starting container on port 8080...
echo.
echo The application will be available at: http://localhost:8080
echo Press Ctrl+C to stop the container
echo.

docker run -p 8080:8080 --rm drivemond-test

pause
