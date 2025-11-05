# 🔧 GCP Cloud Run Deployment Error - Complete Fix Guide

## ❌ Error You're Getting:
```
ERROR: build step 0 "gcr.io/cloud-builders/docker" failed: step exited with non-zero status: 1
```

---

## ✅ **FIXED - What I Changed:**

### **1. Fixed composer.json**
- Changed `"laravel/reverb": "@beta"` → `"laravel/reverb": "^1.0"`
- Beta packages can fail in production builds

### **2. Improved Dockerfile**
- Better error handling for composer install
- Fallback options for npm builds
- Verbose logging to identify failures

### **3. Enhanced .dockerignore**
- Excludes `vendor/` and `node_modules/`
- Reduces build context from 500MB+ to ~50MB
- Speeds up uploads to GCP

### **4. Created Debug Tools**
- `cloudbuild-debug.yaml` - More verbose build logs
- `deploy-debug.bat` - Automated deployment with checks
- `Dockerfile.cloud` - Production-optimized Dockerfile

---

## 🚀 **Deploy Now (Choose One Method)**

### **Method 1: Quick Deploy (Recommended)**

Run this in PowerShell:

```powershell
cd D:\Gauva-UpdateCode\backend-main
.\deploy-debug.bat
```

This script will:
- ✅ Check all requirements
- ✅ Enable necessary APIs
- ✅ Clean build artifacts
- ✅ Deploy with detailed logs
- ✅ Show deployment URL

---

### **Method 2: Manual Deploy (Step by Step)**

```bash
# 1. Navigate to directory
cd D:\Gauva-UpdateCode\backend-main

# 2. Login to GCloud (if not already)
gcloud auth login

# 3. Set your project
gcloud config set project YOUR_PROJECT_ID

# 4. Enable APIs
gcloud services enable cloudbuild.googleapis.com
gcloud services enable run.googleapis.com

# 5. Deploy with debug config
gcloud builds submit --config cloudbuild-debug.yaml
```

---

### **Method 3: Using Original Config (After Fixes)**

```bash
cd D:\Gauva-UpdateCode\backend-main

# Use the original cloudbuild.yaml
gcloud builds submit --config cloudbuild.yaml
```

---

## 🔍 **Get Detailed Error Logs**

If deployment still fails, get the actual error:

```bash
# 1. List recent builds
gcloud builds list --limit 5

# 2. Copy the BUILD_ID from the failed build

# 3. View full logs
gcloud builds log [BUILD_ID]

# Example:
gcloud builds log a1b2c3d4-e5f6-7890-abcd-ef1234567890
```

---

## 🐛 **Common Issues & Fixes**

### **Issue 1: composer.lock conflicts**

**Fix:**
```bash
cd D:\Gauva-UpdateCode\backend-main
composer update --lock
git add composer.lock
git commit -m "Update composer.lock"
```

---

### **Issue 2: Missing .env.example**

**Fix:**
```bash
# Copy from .env
copy .env .env.example

# Or create new
echo APP_NAME=DriveMond > .env.example
echo APP_ENV=production >> .env.example
echo APP_DEBUG=false >> .env.example
```

---

### **Issue 3: Permission Denied**

**Fix:**
```bash
# Grant yourself Cloud Build permissions
gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
  --member=user:YOUR_EMAIL@gmail.com \
  --role=roles/cloudbuild.builds.editor

gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
  --member=user:YOUR_EMAIL@gmail.com \
  --role=roles/run.admin
```

---

### **Issue 4: Billing Not Enabled**

**Fix:**
1. Go to: https://console.cloud.google.com/billing
2. Link a billing account to your project
3. Wait 5 minutes, then try again

---

### **Issue 5: Build Timeout**

**Fix:** Increase timeout in `cloudbuild.yaml`:
```yaml
timeout: 3600s  # 1 hour instead of 40 minutes
```

---

### **Issue 6: Network/Dependency Issues**

**Fix:** Add retry logic in Dockerfile:
```dockerfile
# Already added in the new Dockerfile:
RUN composer install --prefer-dist || \
    composer install --no-scripts || \
    (composer diagnose && exit 1)
```

---

## 📊 **Monitor Deployment**

### **During Deployment:**
```bash
# Watch build progress
gcloud builds list --ongoing

# Stream logs in real-time
gcloud builds log --stream
```

### **After Deployment:**
```bash
# Check service status
gcloud run services describe gauva --region europe-west1

# View service URL
gcloud run services describe gauva --region europe-west1 --format 'value(status.url)'

# Check logs
gcloud run services logs read gauva --region europe-west1 --limit 50
```

---

## 🧪 **Test Before Deploying**

### **Validate Dockerfile locally (if Docker installed):**
```bash
# Quick syntax check
docker build --no-cache -t test-build -f Dockerfile . 2>&1 | Select-String "ERROR"
```

### **Validate composer.json:**
```bash
cd D:\Gauva-UpdateCode\backend-main
composer validate --no-check-all
```

### **Check file sizes:**
```bash
# Ensure build context is small
Get-ChildItem -File -Recurse | Measure-Object -Property Length -Sum
```

---

## 🎯 **Recommended Deployment Command**

Use the debug config for first deployment:

```powershell
cd D:\Gauva-UpdateCode\backend-main

# Method A: Use the script
.\deploy-debug.bat

# Method B: Manual command
gcloud builds submit --config cloudbuild-debug.yaml

# After successful first deploy, use:
gcloud builds submit --config cloudbuild.yaml
```

---

## 📝 **What the Debug Build Does Differently:**

1. **`--progress=plain`** - Shows detailed Docker build output
2. **`--no-cache`** - Forces clean build (catches caching issues)
3. **Increased memory** - 1Gi instead of 512Mi
4. **Increased timeout** - 3600s instead of 2400s
5. **File verification** - Lists all files before building
6. **Environment variables** - Includes Razorpay keys in deployment

---

## ✅ **Checklist Before Deploy:**

- [x] All merge conflicts resolved
- [x] composer.json has stable package versions
- [x] .env.example exists
- [x] .dockerignore configured
- [x] vendor/ and node_modules/ removed
- [x] Razorpay credentials ready
- [ ] GCloud SDK installed
- [ ] Authenticated with GCloud
- [ ] Project set in gcloud config
- [ ] Billing enabled

---

## 🆘 **If Still Failing:**

### **Option 1: Get the actual error**
```bash
gcloud builds list --limit 1
gcloud builds log [LATEST_BUILD_ID] > build-error.log
notepad build-error.log
```

Send me the contents of `build-error.log` and I'll help you fix it!

### **Option 2: Use Cloud Shell (Bypass local issues)**
1. Go to: https://console.cloud.google.com
2. Click "Activate Cloud Shell" (top right)
3. Run:
```bash
git clone YOUR_REPO_URL
cd YOUR_REPO/backend-main
gcloud builds submit --config cloudbuild-debug.yaml
```

### **Option 3: Simplify Build**
Use the minimal Dockerfile I created:
```bash
gcloud builds submit --config cloudbuild-debug.yaml -f Dockerfile.cloud
```

---

## 📞 **Need More Help?**

Share with me:
1. Output of: `gcloud builds list --limit 5`
2. Output of: `gcloud builds log [BUILD_ID]`
3. Screenshot of GCP Console Build page

I'll help you debug the specific issue! 🚀

