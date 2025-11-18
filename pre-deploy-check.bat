@echo off
setlocal enabledelayedexpansion

echo ======================================
echo Pre-Deployment Checks for GCP Cloud Run
echo ======================================
echo.

set ERRORS=0

:: Check 1: Required files
echo 1. Checking required files...
for %%F in (Dockerfile .env.example composer.json composer.lock .dockerignore) do (
    if exist "%%F" (
        echo [OK] %%F exists
    ) else (
        echo [ERROR] %%F is missing!
        set /a ERRORS+=1
    )
)
echo.

:: Check 2: No merge conflicts
echo 2. Checking for merge conflict markers...
findstr /S /C:"<<<<<<< HEAD" *.php *.js *.env* *.json *.yaml *.blade.php >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [ERROR] Merge conflict markers found!
    echo    Run: findstr /S /C:"<<<<<<< HEAD" *.php *.js *.json
    set /a ERRORS+=1
) else (
    echo [OK] No merge conflicts
)
echo.

:: Check 3: .env.example check
echo 3. Checking .env.example...
if exist .env.example (
    findstr /C:"APP_NAME=" .env.example >nul
    if !ERRORLEVEL! EQU 0 (
        echo [OK] APP_NAME exists in .env.example
    ) else (
        echo [WARN] APP_NAME missing from .env.example
    )
) else (
    echo [ERROR] .env.example not found!
    set /a ERRORS+=1
)
echo.

:: Check 4: Docker context size
echo 4. Checking for large directories...
if exist "vendor" (
    echo [WARN] vendor/ directory exists (should be in .dockerignore^)
)
if exist "node_modules" (
    echo [WARN] node_modules/ directory exists (should be in .dockerignore^)
)
echo.

:: Check 5: GCloud CLI
echo 5. Checking GCloud configuration...
where gcloud >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] gcloud CLI is installed
    for /f "tokens=*" %%a in ('gcloud config get-value project 2^>nul') do set PROJECT=%%a
    if defined PROJECT (
        echo [OK] Active project: !PROJECT!
    ) else (
        echo [ERROR] No active GCloud project set!
        set /a ERRORS+=1
    )
) else (
    echo [ERROR] gcloud CLI not found!
    echo    Install from: https://cloud.google.com/sdk/docs/install
    set /a ERRORS+=1
)
echo.

:: Summary
echo ======================================
if %ERRORS% EQU 0 (
    echo [SUCCESS] All checks passed! Ready to deploy.
    echo.
    echo To deploy, run:
    echo   gcloud builds submit --config cloudbuild.yaml
    exit /b 0
) else (
    echo [FAILED] Found %ERRORS% error(s^). Please fix them before deploying.
    exit /b 1
)

