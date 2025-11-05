@echo off
cls
color 0A
echo.
echo ==========================================
echo    GAUVA CLOUD DEPLOYMENT - FINAL CHECK
echo ==========================================
echo.

set READY=1

:: Check 1
echo [CHECK 1] Dockerfile...
if exist Dockerfile (
    echo   PASS - Dockerfile found
) else (
    echo   FAIL - Dockerfile missing!
    set READY=0
)

:: Check 2
echo [CHECK 2] composer.json...
if exist composer.json (
    findstr /C:"laravel/reverb" composer.json | findstr /C:"@beta" >nul 2>&1
    if %ERRORLEVEL% EQU 0 (
        echo   WARNING - Beta package found! Should be fixed...
    ) else (
        echo   PASS - No beta packages
    )
) else (
    echo   FAIL - composer.json missing!
    set READY=0
)

:: Check 3
echo [CHECK 3] .env.example...
if exist .env.example (
    echo   PASS - .env.example found
) else (
    echo   FAIL - .env.example missing!
    set READY=0
)

:: Check 4
echo [CHECK 4] Build context...
if exist vendor (
    echo   WARNING - vendor/ exists (will be excluded)
) else (
    echo   PASS - vendor/ not present
)
if exist node_modules (
    echo   WARNING - node_modules/ exists (will be excluded)
) else (
    echo   PASS - node_modules/ not present
)

:: Check 5
echo [CHECK 5] cloudbuild.yaml...
if exist cloudbuild.yaml (
    echo   PASS - cloudbuild.yaml found
) else (
    echo   FAIL - cloudbuild.yaml missing!
    set READY=0
)

:: Check 6
echo [CHECK 6] Google Cloud SDK...
where gcloud >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo   PASS - gcloud CLI installed
    for /f "tokens=*" %%a in ('gcloud config get-value project 2^>nul') do set GCP_PROJECT=%%a
    if defined GCP_PROJECT (
        echo   PASS - Project set: %GCP_PROJECT%
    ) else (
        echo   FAIL - No GCP project configured!
        echo   Run: gcloud config set project YOUR_PROJECT_ID
        set READY=0
    )
) else (
    echo   FAIL - gcloud CLI not found!
    echo   Install: https://cloud.google.com/sdk/docs/install
    set READY=0
)

echo.
echo ==========================================

if %READY% EQU 1 (
    color 0A
    echo   ALL CHECKS PASSED!
    echo ==========================================
    echo.
    echo   Ready to deploy to: %GCP_PROJECT%
    echo   Region: europe-west1
    echo   Estimated time: 15-20 minutes
    echo.
    set /p DEPLOY="Start deployment now? (y/n): "
    if /i "!DEPLOY!"=="y" (
        echo.
        echo Starting deployment...
        echo.
        gcloud builds submit --config cloudbuild.yaml --project=%GCP_PROJECT%
        
        if !ERRORLEVEL! EQU 0 (
            echo.
            color 0A
            echo ==========================================
            echo   DEPLOYMENT SUCCESSFUL!
            echo ==========================================
            echo.
            echo   Your app is live at:
            echo   https://gauva-798219755346.europe-west1.run.app
            echo.
            echo   Admin: https://gauva-798219755346.europe-west1.run.app/admin
            echo.
        ) else (
            color 0C
            echo.
            echo ==========================================
            echo   DEPLOYMENT FAILED
            echo ==========================================
            echo.
            echo   Get detailed logs:
            echo   1. gcloud builds list --limit 5
            echo   2. gcloud builds log [BUILD_ID]
            echo.
        )
    ) else (
        echo.
        echo Deployment cancelled.
    )
) else (
    color 0C
    echo   SOME CHECKS FAILED!
    echo ==========================================
    echo.
    echo   Please fix the issues above before deploying.
    echo.
    echo   Need help?
    echo   - Review DEPLOY_READY.md
    echo   - Review GCP_DEPLOYMENT_FIX.md
    echo.
)

pause

