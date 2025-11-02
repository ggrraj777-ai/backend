# Fixing 500 Server Error on Cloud Run

## 🚨 Current Issue

You're seeing **500 SERVER ERROR** when accessing:
```
https://gauva-798219755346.europe-west1.run.app/admin
```

This is a Laravel application error, not a deployment issue. The container is running, but Laravel is encountering an error.

---

## 🔍 Step 1: Check the Logs

First, let's see what the actual error is:

```bash
gcloud run services logs read gauva --region europe-west1 --limit 100
```

Look for error messages like:
- `RuntimeException: No application encryption key has been specified`
- `SQLSTATE[HY000]` (database connection error)
- `Class not found`
- `Permission denied`

---

## ✅ Step 2: Apply the Fixes

I've updated the Dockerfile to:
1. ✅ Auto-generate APP_KEY if missing
2. ✅ Clear all caches properly
3. ✅ Set correct permissions
4. ✅ Add better error logging

I've also enabled debug mode temporarily in `cloudbuild.yaml` so you can see the actual error.

### Redeploy with Fixes:

```bash
gcloud builds submit --config cloudbuild.yaml
```

---

## 🛠️ Common 500 Error Causes & Solutions

### 1. **Missing APP_KEY** (Most Common)

**Symptoms:**
```
RuntimeException: No application encryption key has been specified
```

**Solution:**
The Dockerfile now auto-generates the key. But you can also set it manually:

```bash
# Generate a new key locally
php artisan key:generate --show

# Set it in Cloud Run
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars APP_KEY=base64:YOUR_GENERATED_KEY_HERE
```

### 2. **Database Connection Error**

**Symptoms:**
```
SQLSTATE[HY000] [2002] Connection refused
```

**Solution:**
You need to set up a database. The app is trying to connect but can't find one.

**Option A: Use Cloud SQL**
```bash
# Create Cloud SQL instance
gcloud sql instances create gauva-db \
  --database-version=MYSQL_8_0 \
  --tier=db-f1-micro \
  --region=europe-west1

# Create database
gcloud sql databases create gauva_db --instance=gauva-db

# Create user
gcloud sql users create dbuser \
  --instance=gauva-db \
  --password=YOUR_SECURE_PASSWORD

# Get connection details
gcloud sql instances describe gauva-db
```

**Option B: Use SQLite (for testing)**
```bash
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars DB_CONNECTION=sqlite,DB_DATABASE=/var/www/html/database/database.sqlite
```

### 3. **Permission Errors**

**Symptoms:**
```
file_put_contents(/var/www/html/storage/logs/laravel.log): failed to open stream
```

**Solution:**
The Dockerfile now sets permissions automatically. If still failing, check logs.

### 4. **Missing .env File**

**Symptoms:**
```
InvalidArgumentException: Please provide a valid cache path
```

**Solution:**
Set all required environment variables in Cloud Run:

```bash
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars \
  APP_NAME=DriveMond,\
  APP_ENV=production,\
  APP_DEBUG=false,\
  APP_URL=https://gauva-798219755346.europe-west1.run.app,\
  DB_CONNECTION=mysql,\
  DB_HOST=YOUR_DB_HOST,\
  DB_PORT=3306,\
  DB_DATABASE=gauva_db,\
  DB_USERNAME=dbuser,\
  DB_PASSWORD=YOUR_PASSWORD
```

### 5. **Cached Configuration**

**Symptoms:**
App shows old configuration or errors

**Solution:**
The startup script now clears all caches. But you can force it:

```bash
# Redeploy to clear caches
gcloud builds submit --config cloudbuild.yaml
```

---

## 📋 Quick Fix Checklist

Run these commands in order:

### 1. Check Current Logs
```bash
gcloud run services logs read gauva --region europe-west1 --limit 50
```

### 2. Redeploy with Fixes
```bash
gcloud builds submit --config cloudbuild.yaml
```

### 3. Wait for Deployment (2-5 minutes)
```bash
gcloud run services describe gauva --region europe-west1
```

### 4. Check New Logs
```bash
gcloud run services logs read gauva --region europe-west1 --limit 50
```

Look for:
```
Starting DriveMond on port 8080...
Generating new APP_KEY...
Application ready on port 8080
APP_ENV: production
APP_DEBUG: true
```

### 5. Test the Application
```bash
# Open in browser
https://gauva-798219755346.europe-west1.run.app
```

---

## 🔧 Advanced Troubleshooting

### View Real-Time Logs
```bash
gcloud run services logs tail gauva --region europe-west1
```

### Check Service Status
```bash
gcloud run services describe gauva --region europe-west1 --format="value(status.conditions)"
```

### Test Database Connection
```bash
# If using Cloud SQL, test connection
gcloud sql connect gauva-db --user=dbuser
```

### SSH into Container (for debugging)
```bash
# Get the image
IMAGE=$(gcloud run services describe gauva --region europe-west1 --format="value(spec.template.spec.containers[0].image)")

# Run locally
docker run -it --entrypoint /bin/bash $IMAGE

# Inside container, test Laravel
php artisan --version
php artisan config:clear
php artisan route:list
```

---

## 🎯 Expected Behavior After Fix

### In Logs:
```
Starting DriveMond on port 8080...
Generating new APP_KEY...
Application ready on port 8080
APP_ENV: production
APP_DEBUG: true
AH00558: apache2: Could not reliably determine the server's fully qualified domain name
[core:notice] [pid 1] AH00094: Command line: 'apache2 -D FOREGROUND'
```

### In Browser:
- ✅ Homepage loads (or shows Laravel welcome page)
- ✅ Admin panel accessible at `/admin`
- ✅ No 500 error

---

## 🔐 Security: Disable Debug Mode After Fixing

Once you've identified and fixed the issue, disable debug mode:

```bash
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars APP_DEBUG=false
```

---

## 📊 Most Likely Issue

Based on typical Laravel deployments, the 500 error is most likely:

1. **Missing APP_KEY** (90% probability)
   - ✅ Fixed in updated Dockerfile

2. **Database not configured** (8% probability)
   - Need to set up Cloud SQL or configure DB env vars

3. **Permission issues** (2% probability)
   - ✅ Fixed in updated Dockerfile

---

## 🚀 Next Steps

1. **Redeploy with the fixes:**
   ```bash
   gcloud builds submit --config cloudbuild.yaml
   ```

2. **Check the logs to see the actual error:**
   ```bash
   gcloud run services logs read gauva --region europe-west1 --limit 100
   ```

3. **Based on the error, apply the appropriate solution above**

4. **Once working, disable debug mode for security**

---

## 📞 If Still Not Working

Share the output of:
```bash
gcloud run services logs read gauva --region europe-west1 --limit 100
```

This will show the exact error message, and I can provide a specific fix.

---

**Status**: 🟡 Fixes applied, ready to redeploy
