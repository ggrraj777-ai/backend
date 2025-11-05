@echo off
echo ======================================
echo GCP Cloud Run Debug Deployment
echo ======================================
echo.

:: Check if gcloud is installed
where gcloud >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] gcloud CLI not found!
    echo.
    echo Please install Google Cloud SDK from:
    echo https://cloud.google.com/sdk/docs/install
    echo.
    echo After installation:
    echo 1. Close this window
    echo 2. Open a new PowerShell/Command Prompt
    echo 3. Run: gcloud init
    echo 4. Then try this script again
    pause
    exit /b 1
)

echo [OK] gcloud CLI found
echo.

:: Get project ID
for /f "tokens=*" %%a in ('gcloud config get-value project 2^>nul') do set PROJECT_ID=%%a
if not defined PROJECT_ID (
    echo [ERROR] No GCP project set!
    echo.
    echo Please set your project:
    echo   gcloud config set project YOUR_PROJECT_ID
    echo.
    echo Or run: gcloud init
    pause
    exit /b 1
)

echo [OK] Using project: %PROJECT_ID%
echo.

:: Confirm deployment
echo This will deploy to: %PROJECT_ID%
echo.
set /p CONFIRM="Continue with deployment? (y/n): "
if /i not "%CONFIRM%"=="y" (
    echo Deployment cancelled.
    exit /b 0
)
echo.

:: Enable required APIs
echo Enabling required APIs...
gcloud services enable cloudbuild.googleapis.com --project=%PROJECT_ID%
gcloud services enable run.googleapis.com --project=%PROJECT_ID%
gcloud services enable containerregistry.googleapis.com --project=%PROJECT_ID%
echo.

:: Clean build artifacts
echo Cleaning local build artifacts...
if exist vendor (
    echo Removing vendor/ directory...
    rmdir /s /q vendor 2>nul
)
if exist node_modules (
    echo Removing node_modules/ directory...
    rmdir /s /q node_modules 2>nul
)
echo.

:: Show what will be uploaded
echo === Files to be uploaded ===
dir /b
echo.

:: Start deployment with debug config
echo ======================================
echo Starting Cloud Build (with detailed logs)...
echo ======================================
echo.
echo This may take 10-20 minutes...
echo You can view progress at:
echo https://console.cloud.google.com/cloud-build/builds?project=%PROJECT_ID%
echo.

gcloud builds submit --config cloudbuild-debug.yaml --project=%PROJECT_ID%

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ======================================
    echo [SUCCESS] Deployment completed!
    echo ======================================
    echo.
    echo Your application is available at:
    echo https://gauva-798219755346.europe-west1.run.app
    echo.
    echo Admin panel:
    echo https://gauva-798219755346.europe-west1.run.app/admin
    echo.
    echo To view logs:
    echo gcloud run services logs read gauva --region europe-west1
    echo.
) else (
    echo.
    echo ======================================
    echo [FAILED] Deployment failed!
    echo ======================================
    echo.
    echo Troubleshooting steps:
    echo.
    echo 1. View build logs:
    echo    gcloud builds list --limit 5
    echo    gcloud builds log [BUILD_ID]
    echo.
    echo 2. Check your permissions:
    echo    You need Cloud Build Editor and Cloud Run Admin roles
    echo.
    echo 3. Check billing:
    echo    Ensure billing is enabled on your project
    echo.
    echo 4. Common fixes:
    echo    - Increase timeout in cloudbuild-debug.yaml
    echo    - Check composer.json for beta packages
    echo    - Verify .env.example exists
    echo.
)

pause

