# Fixing CSS Not Loading on Cloud Run

## 🚨 Problem

CSS and JavaScript files are not loading on your Cloud Run deployment. The page loads but without styling.

## 🔍 Root Causes

1. **APP_URL not set correctly** - Laravel uses this for asset URLs
2. **Assets not compiled** - Need to run `npm run prod` during build
3. **Storage link missing** - Public storage files not accessible
4. **HTTPS mixed content** - HTTP assets on HTTPS site

---

## ✅ Fixes Applied

I've updated the configuration to fix all these issues:

### 1. **Updated Dockerfile**
- ✅ Sets APP_URL to your Cloud Run URL
- ✅ Compiles assets during Docker build (`npm run prod`)
- ✅ Creates storage link automatically

### 2. **Updated cloudbuild.yaml**
- ✅ Sets APP_URL environment variable
- ✅ Sets ASSET_URL environment variable

---

## 🚀 Deploy the Fix

### Option 1: Simple Deployment (Recommended)

```bash
deploy-simple.bat
```

### Option 2: Cloud Build

```bash
gcloud builds submit --config cloudbuild.yaml
```

### Option 3: Manual with Environment Variables

```bash
# Build
docker build -t gcr.io/gauva/drivemond-backend:latest .

# Push
docker push gcr.io/gauva/drivemond-backend:latest

# Deploy with correct APP_URL
gcloud run deploy gauva \
  --image=gcr.io/gauva/drivemond-backend:latest \
  --region=europe-west1 \
  --platform=managed \
  --allow-unauthenticated \
  --port=8080 \
  --memory=512Mi \
  --set-env-vars=APP_ENV=production,APP_DEBUG=true,APP_URL=https://gauva-798219755346.europe-west1.run.app,ASSET_URL=https://gauva-798219755346.europe-west1.run.app
```

---

## 🔧 Additional Fixes (If Still Not Working)

### 1. **Check Asset Paths in Blade Templates**

Make sure your templates use Laravel's asset helpers:

**❌ Wrong:**
```html
<link href="/css/app.css" rel="stylesheet">
<script src="/js/app.js"></script>
```

**✅ Correct:**
```html
<link href="{{ asset('css/app.css') }}" rel="stylesheet">
<script src="{{ asset('js/app.js') }}"></script>
```

Or use mix/vite helpers:
```html
<link href="{{ mix('css/app.css') }}" rel="stylesheet">
<script src="{{ mix('js/app.js') }}"></script>
```

### 2. **Verify Assets Exist**

Check if assets were compiled:

```bash
# Build locally and check
docker build -t test .
docker run -it test ls -la /var/www/html/public/css
docker run -it test ls -la /var/www/html/public/js
```

You should see compiled CSS and JS files.

### 3. **Check Apache Configuration**

The `.htaccess` should allow access to static files:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Allow access to static files
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

This is already configured in your `public/.htaccess`.

### 4. **Force HTTPS for Assets**

Add to your `.env` or environment variables:

```bash
gcloud run services update gauva \
  --region=europe-west1 \
  --update-env-vars=ASSET_URL=https://gauva-798219755346.europe-west1.run.app
```

### 5. **Clear Laravel Caches**

After deployment:

```bash
# The startup script already does this, but you can force it
gcloud run services update gauva \
  --region=europe-west1 \
  --command="php,artisan,config:clear"
```

---

## 🧪 Test Locally First

Before deploying, test that assets load locally:

```bash
# Build the image
docker build -t test-assets .

# Run it
docker run -p 8080:8080 test-assets

# Open http://localhost:8080
# Check browser console for asset loading errors
```

**In browser console (F12), you should see:**
- ✅ CSS files loading with 200 status
- ✅ JS files loading with 200 status
- ❌ No 404 errors for assets

---

## 📊 What Should Happen

### Before Fix:
```
❌ CSS files return 404
❌ Page loads without styling
❌ Browser console shows asset errors
```

### After Fix:
```
✅ CSS files load successfully (200 status)
✅ Page displays with proper styling
✅ No asset errors in console
```

---

## 🔍 Debugging Asset Issues

### 1. Check Asset URLs in Browser

Open browser DevTools (F12) → Network tab → Reload page

Look at the CSS/JS requests:
- **Request URL**: Should be `https://gauva-798219755346.europe-west1.run.app/css/app.css`
- **Status**: Should be `200 OK`
- **Type**: Should be `stylesheet` or `script`

### 2. Check Laravel Asset Helper Output

In your blade template, temporarily add:
```html
<!-- Debug asset URLs -->
{{ asset('css/app.css') }}
{{ mix('css/app.css') }}
```

This will show you what URLs Laravel is generating.

### 3. Check Cloud Run Logs

```bash
gcloud run services logs read gauva --region=europe-west1 --limit=100 | grep -i "css\|js\|asset"
```

Look for 404 errors on asset files.

### 4. Verify Public Directory Structure

```bash
# Check if assets exist in the container
docker run -it gcr.io/gauva/drivemond-backend:latest ls -la /var/www/html/public/css
docker run -it gcr.io/gauva/drivemond-backend:latest ls -la /var/www/html/public/js
```

---

## 🎯 Common Asset Loading Issues & Solutions

### Issue 1: Mixed Content (HTTP/HTTPS)

**Error:** "Mixed Content: The page was loaded over HTTPS, but requested an insecure resource"

**Solution:**
```bash
# Force HTTPS for all assets
gcloud run services update gauva \
  --region=europe-west1 \
  --update-env-vars=ASSET_URL=https://gauva-798219755346.europe-west1.run.app
```

### Issue 2: Assets Return 404

**Possible causes:**
- Assets not compiled during build
- Wrong asset paths in templates
- Apache not serving static files

**Solution:**
1. Ensure `npm run prod` runs during build (already added to Dockerfile)
2. Use `{{ asset() }}` or `{{ mix() }}` helpers
3. Check `.htaccess` configuration

### Issue 3: Assets Not Updated

**Problem:** Old CSS/JS still loading after changes

**Solution:**
```bash
# Clear browser cache
# Or add version to assets
{{ asset('css/app.css?v=' . time()) }}

# Or use Laravel Mix versioning
mix.js('resources/js/app.js', 'public/js')
   .postCss('resources/css/app.css', 'public/css')
   .version();
```

### Issue 4: Large Assets Timeout

**Problem:** Large CSS/JS files take too long to load

**Solution:**
```bash
# Increase timeout
gcloud run services update gauva \
  --region=europe-west1 \
  --timeout=300
```

---

## 📋 Checklist

After deploying, verify:

- [ ] Page loads with proper styling
- [ ] Browser console shows no 404 errors
- [ ] CSS files load with 200 status
- [ ] JS files load with 200 status
- [ ] Images load correctly
- [ ] Fonts load correctly (if any)
- [ ] No mixed content warnings

---

## 🚀 Quick Fix Steps

1. **Deploy with fixes:**
   ```bash
   deploy-simple.bat
   ```

2. **Wait for deployment (2-3 minutes)**

3. **Clear browser cache and reload:**
   - Chrome: Ctrl+Shift+R
   - Firefox: Ctrl+F5

4. **Check browser console (F12) for errors**

5. **If still not working, check logs:**
   ```bash
   gcloud run services logs read gauva --region=europe-west1 --limit=50
   ```

---

## 💡 Pro Tips

1. **Use CDN for assets** (optional, for better performance):
   - Upload compiled assets to Google Cloud Storage
   - Set ASSET_URL to CDN URL

2. **Enable asset caching**:
   - Already configured in `.htaccess`
   - Assets cached for 1 year

3. **Minify assets**:
   - `npm run prod` already minifies
   - Check `webpack.mix.js` for optimization settings

---

## 📞 Still Not Working?

If CSS still doesn't load after these fixes:

1. **Check the actual asset URL in browser:**
   - Right-click on page → View Page Source
   - Find the `<link>` tags
   - Copy the CSS URL and try accessing it directly

2. **Verify the file exists:**
   ```bash
   curl https://gauva-798219755346.europe-west1.run.app/css/app.css
   ```

3. **Check Apache error logs:**
   ```bash
   gcloud run services logs read gauva --region=europe-west1 --limit=100
   ```

4. **Test locally first:**
   ```bash
   docker build -t test .
   docker run -p 8080:8080 test
   # Open http://localhost:8080 and check if CSS loads
   ```

---

**Status**: 🟢 CSS loading fixes applied, ready to deploy!
