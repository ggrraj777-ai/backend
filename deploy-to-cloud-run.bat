@echo off
echo ======================================
echo DriveMond Cloud Run Deployment
echo ======================================
echo.

REM Check if gcloud is installed
where gcloud >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Error: gcloud CLI is not installed.
    echo Please install it from: https://cloud.google.com/sdk/docs/install
    pause
    exit /b 1
)

REM Get project ID
set /p PROJECT_ID="Enter your Google Cloud Project ID: "

if "%PROJECT_ID%"=="" (
    echo Error: Project ID cannot be empty
    pause
    exit /b 1
)

echo.
echo Setting project to: %PROJECT_ID%
gcloud config set project %PROJECT_ID%

echo.
echo Enabling required APIs...
gcloud services enable cloudbuild.googleapis.com
gcloud services enable run.googleapis.com
gcloud services enable containerregistry.googleapis.com

echo.
echo Building and deploying to Cloud Run...
gcloud builds submit --config cloudbuild.yaml

echo.
echo ======================================
echo Deployment Complete!
echo ======================================
echo.
echo Your application should be available at:
echo https://drivemond-backend-xxxxx.run.app
echo.
echo To view your services:
echo gcloud run services list --platform managed
echo.
echo To set environment variables:
echo gcloud run services update drivemond-backend --region us-central1 --update-env-vars KEY=VALUE
echo.
echo Admin Panel: https://your-url.run.app/admin
echo API Docs: https://your-url.run.app/docs
echo.
pause
