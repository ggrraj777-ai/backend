# Fixing Cloud Build Step 2 Error

## 🚨 Error Message
```
ERROR: build step 2 "gcr.io/google.com/cloudsdktool/cloud-sdk:slim" failed: step exited with non-zero status: 1
```

## 🔍 What This Means

Step 2 is the Cloud Run deployment step. The build and push succeeded, but the deployment failed.

Common causes:
1. Insufficient permissions
2. Service configuration issues
3. Cloud SDK version incompatibility
4. Region or service name conflicts

---

## ✅ Fixes Applied

I've updated `cloudbuild.yaml` to:
1. Use `gcr.io/cloud-builders/gcloud` instead of `cloudsdktool/cloud-sdk:slim`
2. Fix argument formatting (use `--key=value` format)
3. Simplify the deployment step

---

## 🚀 Solution Options

### Option 1: Use Updated cloudbuild.yaml (Recommended)

The cloudbuild.yaml has been fixed. Try again:

```bash
gcloud builds submit --config cloudbuild.yaml
```

### Option 2: Use Simple Deployment Script (Easier)

I've created a simpler deployment script that builds locally:

```bash
deploy-simple.bat
```

This approach:
- ✅ Builds Docker image on your machine
- ✅ Pushes to Google Container Registry
- ✅ Deploys to Cloud Run
- ✅ Avoids Cloud Build complexity

### Option 3: Manual Deployment (Most Control)

```bash
# 1. Build the image
docker build -t gcr.io/gauva/drivemond-backend:latest .

# 2. Configure Docker for GCR
gcloud auth configure-docker

# 3. Push the image
docker push gcr.io/gauva/drivemond-backend:latest

# 4. Deploy to Cloud Run
gcloud run deploy gauva \
  --image=gcr.io/gauva/drivemond-backend:latest \
  --region=europe-west1 \
  --platform=managed \
  --allow-unauthenticated \
  --port=8080 \
  --memory=512Mi \
  --cpu=1 \
  --timeout=300 \
  --set-env-vars=APP_ENV=production,APP_DEBUG=true,LOG_CHANNEL=stderr
```

---

## 🔧 Troubleshooting Steps

### 1. Check Cloud Build Logs

```bash
# List recent builds
gcloud builds list --limit=5

# View specific build logs
gcloud builds log BUILD_ID
```

Look for the actual error message in step 2.

### 2. Verify Permissions

Make sure Cloud Build has the necessary permissions:

```bash
# Get project number
PROJECT_NUMBER=$(gcloud projects describe gauva --format="value(projectNumber)")

# Grant Cloud Run Admin role to Cloud Build
gcloud projects add-iam-policy-binding gauva \
  --member=serviceAccount:${PROJECT_NUMBER}@cloudbuild.gserviceaccount.com \
  --role=roles/run.admin

# Grant Service Account User role
gcloud projects add-iam-policy-binding gauva \
  --member=serviceAccount:${PROJECT_NUMBER}@cloudbuild.gserviceaccount.com \
  --role=roles/iam.serviceAccountUser
```

### 3. Check if Service Exists

```bash
# List existing services
gcloud run services list --region=europe-west1

# If service exists with issues, delete and redeploy
gcloud run services delete gauva --region=europe-west1
```

### 4. Test Docker Image Locally

```bash
# Build locally
docker build -t test-image .

# Run locally
docker run -p 8080:8080 test-image

# If it works locally, the issue is with deployment, not the image
```

---

## 🎯 Recommended Approach

**Use the simple deployment script:**

```bash
deploy-simple.bat
```

This bypasses Cloud Build and deploys directly, which is:
- ✅ Faster
- ✅ Easier to debug
- ✅ More reliable for initial deployments
- ✅ Gives you immediate feedback

---

## 📋 Common Error Messages & Solutions

### Error: "Permission denied"
**Solution:**
```bash
gcloud auth login
gcloud config set project gauva
```

### Error: "Service account does not have permission"
**Solution:**
```bash
# Grant necessary permissions (see step 2 above)
```

### Error: "Image not found"
**Solution:**
```bash
# Make sure the image was pushed successfully
gcloud container images list --repository=gcr.io/gauva
```

### Error: "Region not found"
**Solution:**
```bash
# List available regions
gcloud run regions list

# Use a different region if needed
--region=us-central1
```

---

## 🔄 Quick Recovery Steps

1. **Try the simple deployment:**
   ```bash
   deploy-simple.bat
   ```

2. **If that fails, check Docker:**
   ```bash
   docker build -t test .
   docker run -p 8080:8080 test
   ```

3. **If Docker works, the issue is with GCP:**
   ```bash
   # Check authentication
   gcloud auth list
   
   # Check project
   gcloud config get-value project
   
   # Check permissions
   gcloud projects get-iam-policy gauva
   ```

4. **If still failing, use manual deployment (Option 3 above)**

---

## 📊 What Should Happen

### Successful Build Output:
```
Step 0: Building Docker image...
✓ Step 0 completed successfully
Step 1: Pushing to Container Registry...
✓ Step 1 completed successfully
Step 2: Deploying to Cloud Run...
✓ Step 2 completed successfully

Service [gauva] revision [gauva-00001-xxx] has been deployed
Service URL: https://gauva-xxxxx-ew.a.run.app
```

### Successful Deployment:
- ✅ Service is live
- ✅ URL is accessible
- ✅ No 500 errors (or if there are, they're Laravel errors, not deployment errors)

---

## 💡 Pro Tip

For faster iterations during development, use the simple deployment script:
```bash
deploy-simple.bat
```

Once everything is working, you can switch back to Cloud Build for automated deployments.

---

## 🚀 Next Steps

1. **Try the simple deployment:**
   ```bash
   deploy-simple.bat
   ```

2. **If successful, test the application:**
   ```
   https://gauva-798219755346.europe-west1.run.app
   ```

3. **Check logs for any 500 errors:**
   ```bash
   gcloud run services logs read gauva --region=europe-west1 --limit=50
   ```

4. **Once working, you can switch back to Cloud Build if needed**

---

**Status**: 🟢 Alternative deployment methods ready
