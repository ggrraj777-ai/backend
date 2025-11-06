# 🔧 Fix HTTP 500 Error on Cloud Run

## ❌ **Problem:** 
Your app is deployed but shows "HTTP ERROR 500"

---

## ✅ **SOLUTION - Run These Commands**

### **Option 1: Via Cloud Shell (Recommended)**

1. Go to: https://console.cloud.google.com
2. Click the **Cloud Shell** icon (>_) at top right
3. Copy and paste these commands:

```bash
# Get current environment variables
gcloud run services describe gauva --region europe-west1 --format export > service.yaml

# Generate a new APP_KEY
APP_KEY=$(openssl rand -base64 32)

# Update the service with APP_KEY
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars="APP_KEY=base64:$APP_KEY"

# Wait 30 seconds for deployment
sleep 30

# Test the URL
curl -I https://backend-798219755346.europe-west1.run.app
```

---

### **Option 2: Via GCP Console (Manual)**

1. **Go to:** https://console.cloud.google.com/run/detail/europe-west1/gauva

2. **Click:** "EDIT & DEPLOY NEW REVISION" (top of page)

3. **Click:** "VARIABLES & SECRETS" tab

4. **Add/Update these environment variables:**

```
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
DB_CONNECTION=mysql
DB_HOST=your-database-host
DB_PORT=3306
DB_DATABASE=your-database-name
DB_USERNAME=your-database-user
DB_PASSWORD=your-database-password
```

5. **To generate APP_KEY:**
   - Open Cloud Shell
   - Run: `php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"`
   - Copy the output

6. **Click:** "DEPLOY" at bottom

7. **Wait:** 1-2 minutes for new revision to deploy

8. **Refresh** your browser

---

## 🔍 **Check What's Actually Wrong**

### **View Logs in GCP Console:**

1. Go to: https://console.cloud.google.com/run/detail/europe-west1/gauva
2. Click **"LOGS"** tab
3. Look for errors like:
   - `"No application encryption key has been specified"`
   - `"SQLSTATE[HY000] [2002] Connection refused"`
   - `"Class not found"`
   - `"Permission denied"`

---

## 🐛 **Common HTTP 500 Causes & Fixes**

### **1. Missing APP_KEY**
**Error:** "No application encryption key has been specified"

**Fix:**
```bash
# Via Cloud Shell
gcloud run services update gauva --region europe-west1 \
  --update-env-vars="APP_KEY=base64:$(openssl rand -base64 32)"
```

---

### **2. Database Not Connected**
**Error:** "SQLSTATE[HY000] [2002]"

**Fix:**
```bash
# Add database credentials
gcloud run services update gauva --region europe-west1 \
  --update-env-vars="DB_CONNECTION=mysql,DB_HOST=YOUR_HOST,DB_DATABASE=YOUR_DB,DB_USERNAME=YOUR_USER,DB_PASSWORD=YOUR_PASS"

# Or use Cloud SQL
gcloud run services update gauva --region europe-west1 \
  --add-cloudsql-instances=PROJECT_ID:REGION:INSTANCE_NAME
```

---

### **3. Missing Storage Permissions**
**Error:** "Permission denied" or "failed to open stream"

**Fix:** Already handled in Dockerfile, but verify:
```bash
# The container should have created these with proper permissions
# Check logs for specific permission errors
```

---

### **4. Cache Issues**
**Error:** Various cached config errors

**Fix:**
```bash
# Add cache clear to env vars
gcloud run services update gauva --region europe-west1 \
  --update-env-vars="CACHE_DRIVER=array,SESSION_DRIVER=cookie"
```

---

### **5. Missing Dependencies**
**Error:** "Class 'X' not found"

**Fix:** Rebuild with `--no-cache`:
```bash
# Edit cloudbuild.yaml, add --no-cache to docker build
# Then redeploy
gcloud builds submit --config cloudbuild.yaml
```

---

## ✅ **Quick Fix - All Common Issues**

Run this in **Cloud Shell** to fix most 500 errors:

```bash
# Generate APP_KEY
APP_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")

# Update service with all fixes
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars="APP_KEY=$APP_KEY,APP_ENV=production,APP_DEBUG=false,LOG_CHANNEL= stderr,CACHE_DRIVER=array,SESSION_DRIVER=cookie,QUEUE_CONNECTION=sync"

# Wait for deployment
echo "Waiting for new revision to deploy..."
sleep 30

# Test
echo "Testing application..."
curl -I https://backend-798219755346.europe-west1.run.app

echo ""
echo "If you still see errors, check logs at:"
echo "https://console.cloud.google.com/run/detail/europe-west1/gauva/logs"
```

---

## 📊 **After Fixing**

1. **Refresh** your browser (Ctrl+F5)
2. **Clear** browser cache if needed
3. **Check** the admin panel: https://backend-798219755346.europe-west1.run.app/admin

---

## 🆘 **Still Not Working?**

### **Share These With Me:**

1. **Cloud Run Logs:**
   - Go to: https://console.cloud.google.com/run/detail/europe-west1/gauva/logs
   - Take screenshot of the error logs
   
2. **Environment Variables:**
   - Go to: https://console.cloud.google.com/run/detail/europe-west1/gauva
   - Click "EDIT & DEPLOY NEW REVISION"
   - Click "VARIABLES & SECRETS"
   - Take screenshot (hide sensitive values)

3. **Service Details:**
   - In Cloud Shell, run:
   ```bash
   gcloud run services describe gauva --region europe-west1
   ```

---

## 🎯 **Most Likely Fix:**

**90% of HTTP 500 errors on fresh Cloud Run Laravel deployments are due to missing APP_KEY.**

**Run this in Cloud Shell now:**

```bash
gcloud run services update gauva --region europe-west1 \
  --update-env-vars="APP_KEY=base64:$(openssl rand -base64 32)"
```

Then refresh your browser in 30 seconds! 🚀

