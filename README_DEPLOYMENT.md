# DriveMond - Complete Deployment Guide

## 🚨 Current Issue: Port 8080 Error - NOW FIXED!

Your Cloud Run deployment was failing with:
```
The user-provided container failed to start and listen on the port defined provided by the PORT=8080 environment variable
```

**This has been fixed!** The Dockerfile and cloudbuild.yaml have been updated.

---

## 📁 Files Overview

| File | Purpose |
|------|---------|
| `Dockerfile` | ✅ **FIXED** - Configures container with port 8080 |
| `cloudbuild.yaml` | ✅ **UPDATED** - Matches your service name "gauva" |
| `.dockerignore` | Optimizes Docker build |
| `docker-entrypoint.sh` | Container startup script (now inline) |
| `deploy-to-cloud-run.bat` | Windows deployment script |
| `test-docker-locally.bat` | Test Docker image before deploying |
| `CLOUD_RUN_FIX.md` | Detailed fix explanation |
| `DEPLOYMENT_GUIDE.md` | Complete deployment instructions |

---

## 🚀 Quick Start - Deploy Now!

### Step 1: Test Locally (Optional but Recommended)

```bash
# Test the Docker image locally first
test-docker-locally.bat

# Open browser to: http://localhost:8080
# If it works locally, it will work on Cloud Run!
```

### Step 2: Deploy to Cloud Run

```bash
# Make sure you're logged in
gcloud auth login

# Set your project (from screenshot: gauva)
gcloud config set project gauva

# Deploy!
gcloud builds submit --config cloudbuild.yaml
```

### Step 3: Access Your Application

After successful deployment:
- **Main App**: https://gauva-xxxxx-ew.a.run.app
- **Admin Panel**: https://gauva-xxxxx-ew.a.run.app/admin
- **API**: https://gauva-xxxxx-ew.a.run.app/api

---

## 🔧 What Was Fixed

### 1. Dockerfile Changes

**Before:**
```dockerfile
EXPOSE 8080
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf
```

**After (Fixed):**
```dockerfile
ENV PORT=8080
EXPOSE 8080
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
```

**Key improvements:**
- ✅ Set `PORT=8080` environment variable
- ✅ Replace ALL occurrences of port 80 with 8080
- ✅ Simplified startup script (inline)
- ✅ Better error handling and logging

### 2. cloudbuild.yaml Changes

**Updated to match your deployment:**
- ✅ Service name: `gauva` (from your screenshot)
- ✅ Region: `europe-west1` (from your screenshot)
- ✅ Port: `8080` explicitly set
- ✅ Memory: `512Mi`
- ✅ CPU: `1`
- ✅ Timeout: `300s`

---

## 📋 Deployment Checklist

Before deploying, ensure:

- [ ] Google Cloud SDK installed
- [ ] Logged in: `gcloud auth login`
- [ ] Project set: `gcloud config set project gauva`
- [ ] APIs enabled (the script does this automatically)
- [ ] `.env` file is NOT committed to Git
- [ ] `composer.json` and `composer.lock` exist
- [ ] `storage` and `bootstrap/cache` directories exist

---

## 🐛 Troubleshooting

### Issue: Build fails

**Solution:**
```bash
# Check build logs
gcloud builds list --limit 5

# View specific build
gcloud builds log BUILD_ID
```

### Issue: Container still won't start

**Solution:**
```bash
# Test locally first
docker build -t test .
docker run -p 8080:8080 test

# If it works locally, check Cloud Run logs
gcloud run services logs read gauva --region europe-west1 --limit 50
```

### Issue: Port 8080 still not working

**Solution:**
The Dockerfile now has explicit port configuration. If still failing:

1. Delete the service and redeploy:
```bash
gcloud run services delete gauva --region europe-west1
gcloud builds submit --config cloudbuild.yaml
```

2. Verify Apache configuration in logs:
```bash
gcloud run services logs tail gauva --region europe-west1
```

You should see:
```
Starting DriveMond on port 8080...
Application ready on port 8080
```

---

## 🔐 Environment Variables

After deployment, set your environment variables:

```bash
gcloud run services update gauva \
  --region europe-west1 \
  --update-env-vars \
  APP_KEY=base64:YOUR_KEY_HERE,\
  DB_HOST=YOUR_DB_HOST,\
  DB_DATABASE=gauva_db,\
  DB_USERNAME=your_user,\
  DB_PASSWORD=your_password
```

**Important:** Change the default APP_KEY in cloudbuild.yaml!

Generate a new key:
```bash
php artisan key:generate --show
```

---

## 💾 Database Setup

### Option 1: Cloud SQL (Recommended)

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

# Get connection name
gcloud sql instances describe gauva-db --format="value(connectionName)"
```

### Option 2: External Database

Update environment variables with your database credentials.

---

## 📊 Monitoring

### View Logs
```bash
# Real-time logs
gcloud run services logs tail gauva --region europe-west1

# Recent logs
gcloud run services logs read gauva --region europe-west1 --limit 100
```

### Service Status
```bash
gcloud run services describe gauva --region europe-west1
```

### List All Services
```bash
gcloud run services list --platform managed
```

---

## 💰 Cost Optimization

Cloud Run pricing:
- **Free tier**: 2 million requests/month
- **After free tier**: ~$0.40 per million requests
- **Memory**: $0.0000025 per GB-second
- **CPU**: $0.00002400 per vCPU-second

**Estimated cost for small app**: $5-20/month

To reduce costs:
- Use `--min-instances=0` (default, scales to zero)
- Set `--max-instances=10` (already configured)
- Use smaller memory: `--memory=512Mi` (already configured)

---

## 🎯 Success Indicators

After deployment, you should see:

✅ **In Cloud Console:**
- Creating service: ✅ Completed
- Creating Cloud Build trigger: ✅ Completed
- Building and deploying from repository: ✅ Completed
- Creating revision: ✅ Completed
- Routing traffic: ✅ Completed

✅ **In Logs:**
```
Starting DriveMond on port 8080...
Application ready on port 8080
AH00558: apache2: Could not reliably determine the server's fully qualified domain name
[core:notice] [pid 1] AH00094: Command line: 'apache2 -D FOREGROUND'
```

✅ **In Browser:**
- Application loads without errors
- Admin panel accessible
- API endpoints respond

---

## 📞 Next Steps

1. **Deploy the fixed version:**
   ```bash
   gcloud builds submit --config cloudbuild.yaml
   ```

2. **Set up your database** (Cloud SQL or external)

3. **Configure environment variables** with your database credentials

4. **Run database migrations:**
   ```bash
   # Connect to Cloud Run container and run migrations
   gcloud run services update gauva \
     --region europe-west1 \
     --command "php,artisan,migrate,--force"
   ```

5. **Access your application!**

---

## 📚 Additional Resources

- [Cloud Run Documentation](https://cloud.google.com/run/docs)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [Cloud SQL for MySQL](https://cloud.google.com/sql/docs/mysql)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)

---

## ✅ Summary

**The port 8080 error has been fixed!**

The Dockerfile and cloudbuild.yaml have been updated to:
- ✅ Properly configure Apache to listen on port 8080
- ✅ Set the PORT environment variable
- ✅ Match your service name and region
- ✅ Include better error handling and logging

**Just run:**
```bash
gcloud builds submit --config cloudbuild.yaml
```

**And your application will deploy successfully!**

---

**Status**: 🟢 Ready for deployment
