# DriveMond - Google Cloud Deployment Guide

## Important: Why Firebase Hosting Won't Work

Firebase Hosting only serves **static files** (HTML, CSS, JavaScript). Your Laravel application is a **PHP backend** that requires a server to execute PHP code. 

**You need to use Google Cloud Run or Google App Engine instead.**

## Option 1: Deploy to Google Cloud Run (Recommended)

### Prerequisites
1. Google Cloud account
2. Google Cloud SDK installed
3. Docker installed (optional, Cloud Build will handle it)

### Step 1: Install Google Cloud SDK

Download and install from: https://cloud.google.com/sdk/docs/install

### Step 2: Initialize and Login

```bash
# Login to Google Cloud
gcloud auth login

# Set your project ID
gcloud config set project YOUR_PROJECT_ID

# Enable required APIs
gcloud services enable cloudbuild.googleapis.com
gcloud services enable run.googleapis.com
gcloud services enable containerregistry.googleapis.com
```

### Step 3: Update Environment Variables

Create a `.env.production` file with your production settings:

```env
APP_NAME=DriveMond
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app-url.run.app

DB_CONNECTION=mysql
DB_HOST=YOUR_CLOUD_SQL_IP
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Add other production settings
```

### Step 4: Deploy Using Cloud Build

```bash
# Navigate to your project directory
cd d:/Gauva-main/Gauva-main/Gauva-main

# Submit build to Cloud Build
gcloud builds submit --config cloudbuild.yaml
```

### Step 5: Configure Environment Variables in Cloud Run

After deployment, set environment variables:

```bash
gcloud run services update drivemond-backend \
  --region us-central1 \
  --update-env-vars APP_KEY=your_app_key_here,DB_HOST=your_db_host,DB_DATABASE=your_db_name,DB_USERNAME=your_db_user,DB_PASSWORD=your_db_pass
```

### Step 6: Run Database Migrations

```bash
# Connect to Cloud Run and run migrations
gcloud run services update drivemond-backend \
  --region us-central1 \
  --command "php,artisan,migrate,--force"
```

## Option 2: Manual Docker Deployment

### Step 1: Build Docker Image Locally

```bash
# Build the image
docker build -t drivemond-backend .

# Test locally
docker run -p 8080:8080 drivemond-backend
```

### Step 2: Push to Google Container Registry

```bash
# Tag the image
docker tag drivemond-backend gcr.io/YOUR_PROJECT_ID/drivemond-backend

# Push to GCR
docker push gcr.io/YOUR_PROJECT_ID/drivemond-backend
```

### Step 3: Deploy to Cloud Run

```bash
gcloud run deploy drivemond-backend \
  --image gcr.io/YOUR_PROJECT_ID/drivemond-backend \
  --platform managed \
  --region us-central1 \
  --allow-unauthenticated \
  --port 8080
```

## Option 3: Deploy to Google App Engine

### Step 1: Create `app.yaml`

```yaml
runtime: php82
env: standard

handlers:
  - url: /.*
    secure: always
    redirect_http_response_code: 301
    script: auto

env_variables:
  APP_ENV: production
  APP_DEBUG: false
  APP_KEY: your_app_key_here
```

### Step 2: Deploy

```bash
gcloud app deploy
```

## Database Setup (Cloud SQL)

### Create Cloud SQL Instance

```bash
gcloud sql instances create drivemond-db \
  --database-version=MYSQL_8_0 \
  --tier=db-f1-micro \
  --region=us-central1
```

### Create Database

```bash
gcloud sql databases create gauva_db --instance=drivemond-db
```

### Create User

```bash
gcloud sql users create dbuser \
  --instance=drivemond-db \
  --password=your_secure_password
```

## Access Points After Deployment

Once deployed, your application will be available at:
- **Main Application**: https://drivemond-backend-xxxxx-uc.a.run.app
- **Admin Panel**: https://drivemond-backend-xxxxx-uc.a.run.app/admin
- **API**: https://drivemond-backend-xxxxx-uc.a.run.app/api

## Troubleshooting

### Port 8080 Error
- Cloud Run requires your app to listen on port 8080
- The Dockerfile is configured to use PORT=8080
- Make sure your app respects the PORT environment variable

### Database Connection
- Use Cloud SQL Proxy for secure connections
- Or use Cloud SQL's public IP with SSL

### Storage Issues
- Use Google Cloud Storage for file uploads
- Configure Laravel filesystem to use GCS

## Cost Optimization

- Cloud Run charges only when your app is running
- Use Cloud SQL's smallest instance for development
- Enable autoscaling in Cloud Run

## Files Created

1. `Dockerfile` - Container configuration for Cloud Run
2. `.dockerignore` - Files to exclude from Docker build
3. `cloudbuild.yaml` - Automated build and deployment config
4. This deployment guide

## Next Steps

1. Choose your deployment method (Cloud Run recommended)
2. Set up Cloud SQL database
3. Configure environment variables
4. Deploy using the commands above
5. Run database migrations
6. Access your application!

## Important Notes

- **Never commit `.env` files to Git**
- Use Cloud Secret Manager for sensitive data
- Enable Cloud Logging for debugging
- Set up Cloud Monitoring for alerts
- Configure custom domain if needed
