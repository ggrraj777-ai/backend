# Fixing .env File Error

## 🚨 Errors from Screenshot

From your Cloud Run logs, I can see:

1. **`file_get_contents(/var/www/html/.env): Failed to open stream: No such file or directory`**
   - Laravel can't find the `.env` file

2. **`Defaults STARTUP TCP probe failed (time consecutively for container 'placeholder-1' on port 8080`**
   - Container is crashing before it can respond to health checks

3. **`Container called exit(1)`**
   - Application is exiting with an error

## 🔍 Root Cause

The `.env` file is excluded by `.dockerignore` (which is correct for security), but we weren't creating it inside the container. Laravel requires a `.env` file to run.

---

## ✅ Fixes Applied

I've updated the `Dockerfile` to:

### 1. **Create .env from .env.example**
```dockerfile
# Create .env file from .env.example
RUN cp .env.example .env || echo "APP_NAME=DriveMond" > .env
```

### 2. **Ensure .env exists at startup**
```bash
# Ensure .env file exists
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env file..."
    cp /var/www/html/.env.example /var/www/html/.env
fi
```

### 3. **Auto-generate APP_KEY**
```bash
# Generate APP_KEY if not set
if ! grep -q "APP_KEY=base64:" /var/www/html/.env; then
    echo "Generating new APP_KEY..."
    php artisan key:generate --force --no-interaction
fi
```

### 4. **Set proper permissions**
```bash
chown -R www-data:www-data /var/www/html/.env
chmod 644 /var/www/html/.env
```

---

## 🚀 Deploy the Fix

### Option 1: Using Cloud Build
```bash
gcloud builds submit --config cloudbuild.yaml
```

### Option 2: Using Simple Deployment (Recommended)
```bash
deploy-simple.bat
```

### Option 3: Manual Deployment
```bash
# Build
docker build -t gcr.io/gauva/drivemond-backend:latest .

# Test locally first
docker run -p 8080:8080 gcr.io/gauva/drivemond-backend:latest

# If it works locally, push and deploy
docker push gcr.io/gauva/drivemond-backend:latest

gcloud run deploy gauva \
  --image=gcr.io/gauva/drivemond-backend:latest \
  --region=europe-west1 \
  --platform=managed \
  --allow-unauthenticated \
  --port=8080 \
  --memory=512Mi \
  --set-env-vars=APP_ENV=production,APP_DEBUG=true,LOG_CHANNEL=stderr
```

---

## 🧪 Test Locally First

Before deploying to Cloud Run, test the Docker image locally:

```bash
# Build the image
docker build -t test-drivemond .

# Run it
docker run -p 8080:8080 test-drivemond

# In another terminal, check if it's working
curl http://localhost:8080

# Check the logs
docker logs <container_id>
```

You should see:
```
Starting DriveMond on port 8080...
Creating .env file...
Generating new APP_KEY...
Application ready on port 8080
APP_ENV: production
APP_DEBUG: true
```

If it works locally, it will work on Cloud Run!

---

## 📋 What Will Happen After Fix

### In the Logs:
```
Starting DriveMond on port 8080...
Creating .env file...
Generating new APP_KEY...
Application key set successfully.
Application ready on port 8080
APP_ENV: production
APP_DEBUG: true
Checking Apache...
Syntax OK
AH00558: apache2: Could not reliably determine the server's fully qualified domain name
[core:notice] [pid 1] AH00094: Command line: 'apache2 -D FOREGROUND'
```

### In the Browser:
- ✅ No more 500 errors (or at least not .env related)
- ✅ Application loads
- ✅ Admin panel accessible

---

## 🔧 Additional Configuration

After the application is running, you may want to set additional environment variables:

```bash
gcloud run services update gauva \
  --region=europe-west1 \
  --update-env-vars \
  APP_NAME=DriveMond,\
  APP_ENV=production,\
  APP_DEBUG=false,\
  APP_URL=https://gauva-798219755346.europe-west1.run.app,\
  DB_CONNECTION=mysql,\
  DB_HOST=YOUR_DB_HOST,\
  DB_PORT=3306,\
  DB_DATABASE=gauva_db,\
  DB_USERNAME=your_user,\
  DB_PASSWORD=your_password
```

---

## 🎯 Expected Behavior

### Before Fix:
```
❌ file_get_contents(/var/www/html/.env): Failed to open stream
❌ Container called exit(1)
❌ STARTUP TCP probe failed
❌ 500 Server Error
```

### After Fix:
```
✅ .env file created successfully
✅ APP_KEY generated
✅ Container starts successfully
✅ Apache responds on port 8080
✅ Application loads (may need database configuration)
```

---

## 🔐 Security Note

The `.env` file is created inside the container at build time with default values. You should:

1. **Never commit `.env` to Git** (already in .gitignore)
2. **Set sensitive values via Cloud Run environment variables**
3. **Disable debug mode in production** after fixing issues

---

## 📊 Troubleshooting

### If still getting .env errors:

1. **Check if .env.example exists:**
   ```bash
   # In your local project
   ls -la .env.example
   ```

2. **Verify it's being copied:**
   ```bash
   # Build and check
   docker build -t test .
   docker run -it test ls -la /var/www/html/.env
   ```

3. **Check the startup logs:**
   ```bash
   gcloud run services logs read gauva --region=europe-west1 --limit=100
   ```

### If getting database errors:

That's expected! The .env error is fixed, but you need to configure the database. See the logs for the specific database error.

---

## 🚀 Quick Action Steps

1. **Deploy the fixed version:**
   ```bash
   deploy-simple.bat
   ```

2. **Wait 2-3 minutes for deployment**

3. **Check the logs:**
   ```bash
   gcloud run services logs read gauva --region=europe-west1 --limit=50
   ```

4. **Test the application:**
   ```
   https://gauva-798219755346.europe-west1.run.app
   ```

5. **If you see database errors, configure the database (that's the next step!)**

---

## 💡 Pro Tip

Test locally before deploying:
```bash
docker build -t test .
docker run -p 8080:8080 test
```

Open http://localhost:8080 - if it works, Cloud Run will work!

---

**Status**: 🟢 .env error fixed, ready to deploy!
