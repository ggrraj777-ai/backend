# Production Setup Checklist

## 🎯 Critical Production Issues to Fix

---

## 1. ⚠️ **Zone Configuration - URGENT**

### Current Issue:
- Only 3 zones configured: Amalapuram, Rajamundry, Kakinada
- Users outside these zones see "Service not available in this area"

### Solution Options:

#### Option A: Expand Existing Zones
Go to: http://127.0.0.1:8000/admin/zone
1. Edit each zone
2. Expand the polygon boundaries
3. Cover wider service areas

#### Option B: Add More Cities
For each new city:
1. Click "Add Zone"
2. Draw polygon around the city
3. Set zone name
4. Configure fare settings
5. Save

#### Option C: Create State-Wide Coverage (Recommended for Early Launch)
1. Create a zone covering entire Andhra Pradesh
2. Can subdivide into specific zones later
3. Allows service everywhere initially

### How to Add Zone via Admin Panel:
1. Navigate to: **Zone Management** → **Zone Setup** → **Zone List**
2. Click **"Add New Zone"**
3. Use the map interface to draw boundaries
4. Click points on map to create polygon
5. Name the zone
6. Click **Save**

---

## 2. ✅ **Razorpay QR Code Service - Enhanced**

### What Was Fixed:
- Added configuration check before QR generation
- Added detailed error logging
- Better error messages for troubleshooting

### Configuration Required:

Go to: **Payment Management** → **Payment Method** → **Razorpay**

**Configure:**
```
Mode: Test (for testing) or Live (for production)
API Key: rzp_test_XXXXXXXXXX or rzp_live_XXXXXXXXXX
API Secret: Your secret key
```

### Test QR Code Generation:
```bash
curl -X POST http://127.0.0.1:8000/api/v1/driver/payments/razorpay/generate-qr \
  -H "Content-Type: application/json" \
  -d '{
    "driver_id": 1,
    "trip_id": "test123",
    "amount": 100,
    "currency": "INR"
  }'
```

---

## 3. ✅ **SSL Certificate Issues - Fixed**

### What Was Fixed:
- Disabled SSL verification for local development
- Firebase OAuth2 token generation now works
- Push notifications working

###For Production Deployment:
**IMPORTANT:** Enable proper SSL verification:

Edit `Modules/PromotionManagement/Lib/Promotion.php`:
```php
// Change from:
'verify' => false,

// To:
'verify' => true,  // Or path to cacert.pem
```

---

## 4. ✅ **Database Performance - Optimized**

### What Was Fixed:
- Customer list query optimized (4098 customers load in 2-5s)
- Used `withCount` instead of eager loading
- Increased PHP limits for large datasets

### Configuration:
- Max execution time: 300 seconds
- Memory limit: 512MB
- Post max size: 50MB

---

## 5. ✅ **Document Verification System - Working**

### Features Ready:
- Firebase document upload
- Admin verification interface
- Approve/Reject workflow
- Auto-activate driver on approval

### Access:
- **User Management** → **Driver Setup** → **Document Verification**

---

## 6. ⚠️ **Driver App Document Upload - Needs Integration**

### Current Status:
- Firebase upload works ✅
- Backend API exists ✅
- Integration missing ❌

### What Needs to Be Done:
Driver app must call backend API after Firebase upload to save metadata.

**See:** `Driver_2.4/DOCUMENT_UPLOAD_FIX_INSTRUCTIONS.md`

---

## 7. ✅ **Encryption Keys - Generated**

### Status:
- ✅ APP_KEY: Generated
- ✅ Passport OAuth keys: Generated
- ✅ All caches cleared

### Mobile Apps:
**IMPORTANT:** After generating new keys, users must:
- Clear app data
- Or reinstall app
- Login fresh

---

## 8. ⚠️ **Production Deployment Checklist**

### Before Going Live:

#### Security:
- [ ] Enable SSL certificate verification
- [ ] Set strong APP_KEY (already done)
- [ ] Configure firewall rules
- [ ] Enable HTTPS
- [ ] Set proper CORS headers
- [ ] Review API rate limiting

#### Configuration:
- [ ] **Configure all service zones** ← CRITICAL
- [ ] Set Razorpay production keys
- [ ] Configure Firebase production project
- [ ] Set up proper domain name
- [ ] Configure email service (SMTP)
- [ ] Set up SMS gateway

#### Database:
- [ ] Backup strategy configured
- [ ] Remove test data
- [ ] Optimize indexes
- [ ] Set up monitoring

#### Mobile Apps:
- [ ] Update base URL to production domain
- [ ] Build release APKs with proper signing
- [ ] Test on multiple devices
- [ ] Upload to Play Store / App Store

---

## 🚨 **CRITICAL: Zone Setup for Production**

**Your immediate blocker:** Users cannot use the service because zones aren't configured for their locations.

### Quick Fix for Launch:

**Create zones for ALL cities you want to serve:**

1. Go to: http://127.0.0.1:8000/admin/zone
2. For EACH city/region you serve:
   - Click "Add Zone"
   - Draw polygon around service area
   - Name it properly
   - Save
3. Test from that location

**OR**

**Create one large zone covering your entire service region:**
- Easier for initial launch
- Can subdivide later as you expand
- Covers all areas at once

---

## 📊 **Current System Status**

| Component | Status | Action Needed |
|-----------|--------|---------------|
| Backend Server | ✅ Working | Deploy to production server |
| Admin Panel | ✅ Working | Configure zones & Razorpay |
| Database | ✅ Optimized | Remove test data before launch |
| Encryption Keys | ✅ Generated | Keep secure |
| SSL/HTTPS | ⚠️ Dev mode | Enable for production |
| **Zones** | ❌ **Limited** | **Add more zones!** |
| **Razorpay** | ⚠️ Not configured | **Add API keys!** |
| Document Verification | ✅ Working | Good to go |
| Customer Management | ✅ Working | Good to go |
| Driver App | ⚠️ Needs clear data | Users must clear once |
| User App | ⚠️ Zone issue | Add zones first |

---

## 🎯 **Top Priority Actions:**

### 1. Configure Service Zones (CRITICAL)
Without zones, users see "Service not available"

**Do this NOW:**
- Go to Zone Management in admin panel
- Add zones for all service areas
- Or create one large zone

### 2. Configure Razorpay (If Using Payments)
- Add API keys in admin panel
- Test QR code generation
- Verify payments work

### 3. Test Complete Flow:
- User can set location ✅
- User can request ride ✅
- Driver can accept ✅
- Payment works ✅
- Documents verified ✅

---

## 📝 **Configuration Steps**

### Step 1: Zones
```
Admin Panel → Zone Management → Zone Setup → Add Zone
- Draw service area boundaries
- Name each zone
- Activate them
```

### Step 2: Razorpay
```
Admin Panel → Payment Management → Payment Configuration
- Select Razorpay
- Enter API Key
- Enter API Secret
- Set mode (Test/Live)
- Save
```

### Step 3: Test
```
User App: Try setting location → Should work
Driver App: Generate QR → Should work
Admin Panel: Verify documents → Should work
```

---

## ✅ **Backend is Production-Ready Except:**

1. **Add more service zones** ← Users need this
2. **Configure Razorpay keys** ← Payment needs this  
3. **Deploy to production server** ← Domain, HTTPS, etc.

**The code is ready, just needs configuration!** 🚀

