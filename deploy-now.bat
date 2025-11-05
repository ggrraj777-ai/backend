@echo off
cls
echo.
echo ==========================================
echo   GAUVA - Cloud Run Deployment Script
echo ==========================================
echo.

:: Set your project ID here (or it will use current gcloud config)
set PROJECT_ID=

:: Auto-detect project if not set
if "%PROJECT_ID%"=="" (
    for /f "tokens=*" %%a in ('gcloud config get-value project 2^>nul') do set PROJECT_ID=%%a
)

if "%PROJECT_ID%"=="" (
    echo [ERROR] No GCP project configured!
    echo.
    echo Please run one of these:
    echo   1. gcloud init
    echo   2. gcloud config set project YOUR_PROJECT_ID
    echo   3. Edit this script and set PROJECT_ID at the top
    echo.
    pause
    exit /b 1
)

echo [INFO] Using GCP Project: %PROJECT_ID%
echo [INFO] Region: europe-west1
echo [INFO] Service: gauva
echo.

:: Pre-flight checks
echo [STEP 1/5] Pre-flight checks...
where gcloud >nul 2>&1 || (
    echo [ERROR] gcloud CLI not found!
    echo Install from: https://cloud.google.com/sdk/docs/install
    pause
    exit /b 1
)
echo   - gcloud CLI: OK

if not exist "Dockerfile" (
    echo [ERROR] Dockerfile not found!
    pause
    exit /b 1
)
echo   - Dockerfile: OK

if not exist "composer.json" (
    echo [ERROR] composer.json not found!
    pause
    exit /b 1
)
echo   - composer.json: OK

if not exist ".env.example" (
    echo [ERROR] .env.example not found!
    pause
    exit /b 1
)
echo   - .env.example: OK
echo.

:: Clean build context
echo [STEP 2/5] Cleaning build context...
if exist "vendor" (
    echo   Removing vendor/...
    rmdir /s /q vendor 2>nul
)
if exist "node_modules" (
    echo   Removing node_modules/...
    rmdir /s /q node_modules 2>nul
)
if exist "storage\logs\*.log" (
    echo   Removing log files...
    del /q storage\logs\*.log 2>nul
)
echo   - Build context cleaned
echo.

:: Enable APIs
echo [STEP 3/5] Enabling required APIs...
echo   This may take a moment...
gcloud services enable cloudbuild.googleapis.com --project=%PROJECT_ID% --quiet 2>nul
gcloud services enable run.googleapis.com --project=%PROJECT_ID% --quiet 2>nul
gcloud services enable containerregistry.googleapis.com --project=%PROJECT_ID% --quiet 2>nul
echo   - APIs enabled
echo.

:: Build and deploy
echo [STEP 4/5] Building and deploying to Cloud Run...
echo.
echo   This will take 10-20 minutes. Please wait...
echo   Progress: https://console.cloud.google.com/cloud-build/builds?project=%PROJECT_ID%
echo.

gcloud builds submit --config cloudbuild.yaml --project=%PROJECT_ID%

if %ERRORLEVEL% EQU 0 (
    echo.
    echo [STEP 5/5] Deployment successful!
    echo.
    echo ==========================================
    echo   SUCCESS - Application is LIVE!
    echo ==========================================
    echo.
    echo Your application URL:
    echo   https://gauva-798219755346.europe-west1.run.app
    echo.
    echo Admin Panel:
    echo   https://gauva-798219755346.europe-west1.run.app/admin
    echo.
    echo API Base:
    echo   https://gauva-798219755346.europe-west1.run.app/api
    echo.
    echo View logs:
    echo   gcloud run services logs read gauva --region europe-west1
    echo.
    echo Monitor service:
    echo   https://console.cloud.google.com/run/detail/europe-west1/gauva
    echo.
) else (
    echo.
    echo ==========================================
    echo   DEPLOYMENT FAILED
    echo ==========================================
    echo.
    echo To debug:
    echo   1. View build logs:
    echo      gcloud builds list --limit 5
    echo      gcloud builds log [BUILD_ID]
    echo.
    echo   2. Check the GCP Console:
    echo      https://console.cloud.google.com/cloud-build/builds?project=%PROJECT_ID%
    echo.
    echo   3. Common fixes:
    echo      - Ensure billing is enabled
    echo      - Check IAM permissions
    echo      - Verify composer.lock is valid
    echo      - Review Dockerfile syntax
    echo.
    echo   4. Get help:
    echo      Open GCP_DEPLOYMENT_FIX.md for detailed troubleshooting
    echo.
)

pause

