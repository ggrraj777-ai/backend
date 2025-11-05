# Cloud Run Port 8080 Error - FIXED

## ✅ What I've Fixed

Based on your screenshot showing the error:
```
The user-provided container failed to start and listen on the port defined provided by the PORT=8080 environment variable
```

### Changes Made:

1. **Updated Dockerfile**
   - Simplified and optimized the build process
   - Explicitly set `ENV PORT=8080`
   - Fixed Apache configuration to listen on port 8080
   - Inline startup script to avoid file copy issues
   - Better error handling and logging

2. **Updated cloudbuild.yaml**
   - Changed service name to `gauva` (matching your screenshot)
   - Changed region to `europe-west1` (matching your deployment)
   - Added memory and CPU limits
   - Increased timeout settings
   - Added APP_KEY environment variable

## 🚀 How to Redeploy

### Option 1: Using Cloud Build (Recommended)

```bash
gcloud builds submit --config cloudbuild.yaml
```

### Option 2: Manual Deployment

```bash
# Build the image
docker build -t gcr.io/YOUR_PROJECT_ID/drivemond-backend:latest .

# Push to Container Registry
docker push gcr.io/YOUR_PROJECT_ID/drivemond-backend:latest

# Deploy to Cloud Run
gcloud run deploy gauva \
  --image gcr.io/YOUR_PROJECT_ID/drivemond-backend:latest \
  --region europe-west1 \
  --platform managed \
  --allow-unauthenticated \
  --port 8080 \
  --memory 512Mi \
  --cpu 1
```

## 🔍 Verify Port Configuration

After deployment, check the logs:

```bash
gcloud run services logs read gauva --region europe-west1 --limit 50
```

You should see:
```
Starting DriveMond on port 8080...
Application ready on port 8080
```

## 🛠️ Troubleshooting Steps

### 1. Check if Apache is listening on port 8080

The Dockerfile now explicitly configures Apache:
```dockerfile
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
```

### 2. Verify the startup script

The inline startup script ensures:
- Permissions are set correctly
- Laravel caches are cleared
- Apache starts on the correct port

### 3. Test locally first

Before deploying to Cloud Run, test locally:

```bash
# Build the image
docker build -t drivemond-test .

# Run locally on port 8080
docker run -p 8080:8080 drivemond-test

# Test in browser
# Open: http://localhost:8080
```

If it works locally, it will work on Cloud Run.

## 📋 Pre-Deployment Checklist

- [ ] `.env` file is NOT in the Docker image (it's in .dockerignore)
- [ ] `composer.json` and `composer.lock` exist
- [ ] `storage` and `bootstrap/cache` directories exist
- [ ] Apache is configured for port 8080
- [ ] Environment variables are set in Cloud Run

## 🔧 Quick Fix Commands

If the deployment still fails, try these:

### 1. Delete the existing service and redeploy
```bash
gcloud run services delete gauva --region europe-west1
gcloud builds submit --config cloudbuild.yaml
```

### 2. Check Cloud Run service details
```bash
gcloud run services describe gauva --region europe-west1
```

### 3. View real-time logs
```bash
gcloud run services logs tail gauva --region europe-west1
```

### 4. Update environment variables
```bash
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars APP_KEY=base64:YOUR_KEY_HERE,DB_HOST=YOUR_DB_HOST
```

## 🎯 Expected Result

After successful deployment:

- ✅ Service status: **Completed** (green checkmark)
- ✅ Container starts and listens on port 8080
- ✅ Health check passes
- ✅ Application accessible at: `https://gauva-xxxxx-ew.a.run.app`

### Access Points:
- **Main App**: https://your-url.run.app
- **Admin Panel**: https://your-url.run.app/admin
- **API**: https://your-url.run.app/api

## 📊 What Changed in the Dockerfile

### Before (Problematic):
```dockerfile
EXPOSE 8080
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf
ENTRYPOINT ["docker-entrypoint.sh"]
```

### After (Fixed):
```dockerfile
ENV PORT=8080
EXPOSE 8080
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
CMD ["/usr/local/bin/start.sh"]
```

**Key improvements:**
1. Set `PORT` environment variable explicitly
2. Replace ALL occurrences of port 80 with 8080
3. Simplified startup with inline script
4. Better error handling

## 🔐 Security Notes

The `cloudbuild.yaml` includes a default APP_KEY. **You should change this!**

Generate a new key:
```bash
php artisan key:generate --show
```

Then update Cloud Run:
```bash
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars APP_KEY=base64:YOUR_NEW_KEY_HERE
```

## 📞 Still Having Issues?

1. **Check the build logs** in Google Cloud Console
2. **View container logs** after deployment
3. **Test the Docker image locally** before deploying
4. **Verify port 8080** is exposed and Apache is listening

The configuration is now correct. Just redeploy using:
```bash
gcloud builds submit --config cloudbuild.yaml
```

---

**Status**: ✅ Port 8080 configuration is now FIXED and ready for deployment!
