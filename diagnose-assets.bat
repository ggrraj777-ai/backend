@echo off
echo ======================================
echo Diagnosing Asset Loading Issues
echo ======================================
echo.

echo Step 1: Checking if assets exist locally...
echo.
dir /s /b public\assets\admin-module\css\style.css
echo.

echo Step 2: Testing asset URL directly...
echo.
echo Open this URL in your browser:
echo https://gauva-798219755346.europe-west1.run.app/assets/admin-module/css/style.css
echo.
echo If you get 404, assets aren't being served correctly.
echo If you see CSS code, the assets are accessible but not loading in the page.
echo.

echo Step 3: Checking Cloud Run logs for asset requests...
echo.
gcloud run services logs read gauva --region=europe-west1 --limit=100 | findstr /i "css js assets"
echo.

echo Step 4: Checking service configuration...
echo.
gcloud run services describe gauva --region=europe-west1 --format="value(spec.template.spec.containers[0].env)"
echo.

echo ======================================
echo Diagnosis Complete
echo ======================================
echo.
echo Common issues:
echo 1. Assets return 404 = Apache not serving static files correctly
echo 2. Assets load but page unstyled = Wrong asset paths in templates
echo 3. Mixed content errors = HTTP assets on HTTPS site
echo.
pause
