# 🚀 Deploy to Google Cloud Run - Fixed & Ready

## ✅ What Was Fixed

1. ✅ Improved `.dockerignore` to reduce build context
2. ✅ Enhanced Dockerfile with better error handling
3. ✅ Removed `vendor/` and `node_modules/` directories
4. ✅ Resolved all merge conflicts
5. ✅ Added npm build fallbacks

---

## 📋 Prerequisites

1. **Google Cloud SDK** installed
   - Download: https://cloud.google.com/sdk/docs/install
   - After install, restart your terminal

2. **GCP Project** created
   - Go to: https://console.cloud.google.com
   - Create a new project or use existing

3. **Billing** enabled on your GCP project

---

## 🚀 Deploy Now (3 Methods)

### **Method 1: Quick Deploy (Recommended)**

```bash
# 1. Open PowerShell in backend-main directory
cd D:\Gauva-UpdateCode\backend-main

# 2. Login to GCloud
gcloud auth login

# 3. Set your project ID
gcloud config set project YOUR_PROJECT_ID

# 4. Enable required APIs
gcloud services enable cloudbuild.googleapis.com
gcloud services enable run.googleapis.com
gcloud services enable containerregistry.googleapis.com

# 5. Deploy!
gcloud builds submit --config cloudbuild.yaml
```

---

### **Method 2: Using the Deploy Script**

```bash
# Make the script executable (if on Linux/Mac)
chmod +x deploy-to-cloud-run.sh

# Run it
./deploy-to-cloud-run.sh
```

On Windows:
```bash
.\deploy-to-cloud-run.bat
```

---

### **Method 3: Manual Step-by-Step**

```bash
# 1. Build the Docker image locally first (test)
docker build -t drivemond-backend:test .

# 2. If build succeeds, deploy to GCP
gcloud builds submit --tag gcr.io/YOUR_PROJECT_ID/drivemond-backend

# 3. Deploy to Cloud Run
gcloud run deploy gauva \
  --image gcr.io/YOUR_PROJECT_ID/drivemond-backend \
  --region europe-west1 \
  --platform managed \
  --allow-unauthenticated \
  --port 8080 \
  --memory 512Mi
```

---

## 🔧 Troubleshooting

### **Error: "gcloud: command not found"**

**Solution:**
1. Install Google Cloud SDK: https://cloud.google.com/sdk/docs/install
2. Restart your terminal/PowerShell
3. Run: `gcloud init`

---

### **Error: "Authentication failed"**

**Solution:**
```bash
gcloud auth login
gcloud auth application-default login
```

---

### **Error: "Permission denied"**

**Solution:**
```bash
# Grant necessary permissions
gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
  --member=user:YOUR_EMAIL@gmail.com \
  --role=roles/run.admin

gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
  --member=user:YOUR_EMAIL@gmail.com \
  --role=roles/cloudbuild.builds.editor
```

---

### **Error: "Build timeout" or "Out of memory"**

**Solution:** Increase timeout in `cloudbuild.yaml`:
```yaml
timeout: 3600s  # Increase to 1 hour

options:
  machineType: 'E2_HIGHCPU_8'  # Already using high-CPU machine
```

---

## 🔐 Environment Variables

After deployment, set environment variables:

```bash
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars="RAZORPAY_KEY_ID=rzp_test_RVmSxTBdwWng9o,RAZORPAY_KEY_SECRET=L0Q7LVHqXj1seMQut0D87m5S,APP_DEBUG=false"
```

Or in the Cloud Console:
1. Go to Cloud Run → Services → gauva
2. Click "Edit & Deploy New Revision"
3. Go to "Variables & Secrets" tab
4. Add your environment variables

---

## ✅ Verification

After deployment:

```bash
# Get the service URL
gcloud run services describe gauva --region europe-west1 --format 'value(status.url)'

# Test the endpoint
curl https://your-service-url.run.app/api/health
```

---

## 📊 Monitor Deployment

```bash
# View logs
gcloud run services logs read gauva --region europe-west1

# View deployment status
gcloud run services describe gauva --region europe-west1
```

---

## 🎯 Quick Commands

```bash
# View all services
gcloud run services list

# Delete service
gcloud run services delete gauva --region europe-west1

# View builds
gcloud builds list --limit 10

# View specific build logs
gcloud builds log BUILD_ID
```

---

## 🆘 Still Having Issues?

1. Check build logs:
   ```bash
   gcloud builds list --limit 5
   gcloud builds log [BUILD_ID]
   ```

2. Test Docker build locally:
   ```bash
   docker build -t test-build .
   ```

3. Verify files:
   ```bash
   # Run pre-deployment check
   .\pre-deploy-check.bat
   ```

---

## 📞 Support

- GCP Documentation: https://cloud.google.com/run/docs
- Docker Documentation: https://docs.docker.com
- Laravel on Cloud Run: https://cloud.google.com/community/tutorials/run-laravel-on-google-cloud

---

**Your application is now ready to deploy! 🚀**

