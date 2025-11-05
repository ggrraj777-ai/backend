@echo off
echo ======================================
echo Simple Cloud Run Deployment
echo ======================================
echo.

REM Check if gcloud is installed
where gcloud >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Error: gcloud CLI is not installed.
    pause
    exit /b 1
)

echo Step 1: Building Docker image locally...
docker build -t gcr.io/gauva/drivemond-backend:latest .

if %ERRORLEVEL% NEQ 0 (
    echo Error: Docker build failed!
    pause
    exit /b 1
)

echo.
echo Step 2: Pushing image to Google Container Registry...
docker push gcr.io/gauva/drivemond-backend:latest

if %ERRORLEVEL% NEQ 0 (
    echo Error: Docker push failed!
    echo Make sure you're authenticated: gcloud auth configure-docker
    pause
    exit /b 1
)

echo.
echo Step 3: Deploying to Cloud Run...
gcloud run deploy gauva ^
  --image=gcr.io/gauva/drivemond-backend:latest ^
  --region=europe-west1 ^
  --platform=managed ^
  --allow-unauthenticated ^
  --port=8080 ^
  --memory=512Mi ^
  --cpu=1 ^
  --timeout=300 ^
  --max-instances=10 ^
  --set-env-vars=APP_ENV=production,APP_DEBUG=true,LOG_CHANNEL=stderr

if %ERRORLEVEL% NEQ 0 (
    echo Error: Cloud Run deployment failed!
    pause
    exit /b 1
)

echo.
echo ======================================
echo Deployment Complete!
echo ======================================
echo.
echo Your application is available at:
gcloud run services describe gauva --region=europe-west1 --format="value(status.url)"
echo.
pause
