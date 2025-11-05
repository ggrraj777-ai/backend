@echo off
echo ======================================
echo Diagnosing 500 Error on Cloud Run
echo ======================================
echo.

REM Check if gcloud is installed
where gcloud >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Error: gcloud CLI is not installed.
    pause
    exit /b 1
)

echo Step 1: Checking Cloud Run service status...
echo.
gcloud run services describe gauva --region europe-west1 --format="value(status.url)"
echo.

echo Step 2: Fetching recent logs (last 100 lines)...
echo.
gcloud run services logs read gauva --region europe-west1 --limit 100
echo.

echo ======================================
echo Log Analysis Complete
echo ======================================
echo.
echo Look for these common errors:
echo - "No application encryption key" = Missing APP_KEY
echo - "SQLSTATE" or "Connection refused" = Database issue
echo - "Permission denied" = File permission issue
echo - "Class not found" = Autoload or dependency issue
echo.
echo Next steps:
echo 1. If you see APP_KEY error, redeploy with: gcloud builds submit --config cloudbuild.yaml
echo 2. If you see database error, set up Cloud SQL or configure DB env vars
echo 3. Check FIX_500_ERROR.md for detailed solutions
echo.
pause
