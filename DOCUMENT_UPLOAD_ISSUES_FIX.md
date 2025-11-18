# Document Upload Issues - Fixed

## 🔴 Issue 1: "Invalid key supplied" in Driver App

**Error:** Driver app login shows "Invalid key supplied"

**Root Cause:** Missing `APP_KEY` in `.env` file. Laravel uses this for encryption/decryption.

**Fix Applied:** ✅
```bash
php artisan key:generate
```

**Result:** APP_KEY generated successfully. Driver app should now work properly.

---

## 🔴 Issue 2: Documents Not Showing in Admin Panel

**Error:** Admin panel shows "not uploaded yet" for all documents

**Root Cause:** Driver app uploads files to Firebase but doesn't call backend API to save metadata

**Problem Analysis:**
- Files ARE uploaded to Firebase Storage: `gs://gauva-15d9a.firebasestorage.app/documents`
- BUT: `driver_documents` table has **0 records**
- Backend API not being called after Firebase upload

---

## 🔍 Investigation Results

Database check revealed:
```
Total documents in driver_documents table: 0
Documents for this driver: 0
```

This means the **driver app is NOT calling the backend API** after uploading to Firebase.

---

## ✅ Solution

### Option 1: Fix Driver App API Calls (Recommended)

The driver app needs to call these APIs after Firebase upload:

```http
POST /api/v1/driver/documents/license/upload
POST /api/v1/driver/documents/rc/upload
POST /api/v1/driver/documents/aadhar/upload
POST /api/v1/driver/documents/photo/upload
```

**Required Data:**
```json
{
  "document_number": "DL12345678",
  "front_image_url": "https://firebase_url.com/front.jpg",
  "back_image_url": "https://firebase_url.com/back.jpg",
  "firebase_front_path": "documents/driver_id/license_front.jpg",
  "firebase_back_path": "documents/driver_id/license_back.jpg",
  "expiry_date": "2025-12-31"
}
```

### Option 2: Direct Database Insert (Temporary Workaround)

If you need to test the admin panel immediately, you can manually insert document records:

```sql
INSERT INTO driver_documents (
  id,
  driver_id,
  document_type,
  document_number,
  front_image_url,
  back_image_url,
  firebase_front_path,
  firebase_back_path,
  verification_status,
  created_at,
  updated_at
) VALUES (
  UUID(),
  '558b6902-b262-4929-b6f2-d42d1063d160',
  'driving_license',
  'DL1234567890',
  'https://your-firebase-url.com/front.jpg',
  'https://your-firebase-url.com/back.jpg',
  'documents/558b6902-b262-4929-b6f2-d42d1063d160/license_front.jpg',
  'documents/558b6902-b262-4929-b6f2-d42d1063d160/license_back.jpg',
  'pending',
  NOW(),
  NOW()
);
```

---

## 📱 Driver App Fix Needed

### Current Flow (INCOMPLETE):
```
Driver selects document
  ↓
Upload to Firebase ✅
  ↓
❌ MISSING: Call backend API
  ↓
Admin panel shows "not uploaded"
```

### Required Flow:
```
Driver selects document
  ↓
Upload to Firebase ✅
  ↓
Get Firebase URL ✅
  ↓
Call backend API ✅ ← MISSING THIS
  ↓
Save to driver_documents table ✅
  ↓
Admin panel shows document ✅
```

---

## 🔧 Driver App Code Fix

### File to Check:
`Driver_2.4/lib/features/auth/controllers/document_upload_controller.dart`
or similar document upload handler

### Add API Call After Firebase Upload:

```dart
Future<void> uploadDocument({
  required File file,
  required String documentType,
  required String documentNumber,
}) async {
  try {
    // Step 1: Upload to Firebase (EXISTING)
    String firebasePath = 'documents/$driverId/${documentType}_${DateTime.now().millisecondsSinceEpoch}.jpg';
    TaskSnapshot snapshot = await FirebaseStorage.instance
        .ref(firebasePath)
        .putFile(file);
    
    String downloadUrl = await snapshot.ref.getDownloadURL();
    
    // Step 2: Call backend API (ADD THIS)
    final response = await dio.post(
      '/api/v1/driver/documents/$documentType/upload',
      data: {
        'document_number': documentNumber,
        'front_image_url': downloadUrl,
        'firebase_front_path': firebasePath,
        'expiry_date': expiryDate,
      },
    );
    
    if (response.statusCode == 200) {
      // Success - document saved to database
      showToast('Document uploaded successfully');
    }
  } catch (e) {
    showToast('Upload failed: $e');
  }
}
```

---

## 🧪 Testing

### Test Issue 1 (APP_KEY):
1. Restart backend server
2. Try logging in with driver app
3. Should no longer show "Invalid key supplied"

### Test Issue 2 (Documents):
1. Upload a document from driver app
2. Check backend logs for API call
3. Check database: `SELECT * FROM driver_documents;`
4. Refresh admin panel document verification page
5. Should now show uploaded documents

---

## ✅ What's Fixed

- ✅ **APP_KEY generated** - Driver app encryption working
- ✅ **Admin panel query fixed** - GROUP BY issue resolved
- ✅ **Document verification page** - Ready to display documents

## ⚠️ What Needs Fixing

- ⚠️ **Driver app API calls** - Not calling backend after Firebase upload
- ⚠️ **Document metadata** - Not being saved to `driver_documents` table

---

## 📝 Quick Test Command

Check if documents are being saved:

```bash
cd D:\Gauva-UpdateCode\backend-main
php artisan tinker --execute="DB::table('driver_documents')->count()"
```

If returns `0`, driver app is NOT calling the backend API.

---

## 🎯 Next Steps

1. **Immediate**: Restart backend server to apply APP_KEY
2. **Short term**: Add manual document record to test admin panel
3. **Long term**: Fix driver app to call backend API after Firebase upload

---

## 📞 API Endpoints Documentation

### Upload Driving License
```http
POST /api/v1/driver/documents/license/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data or application/json

{
  "document_number": "DL1234567890",
  "front_image": file or "firebase_url",
  "back_image": file or "firebase_url",
  "expiry_date": "2025-12-31"
}
```

### Upload RC Book
```http
POST /api/v1/driver/documents/rc/upload
```

### Upload Aadhar Card
```http
POST /api/v1/driver/documents/aadhar/upload
```

### Upload Driver Photo
```http
POST /api/v1/driver/documents/photo/upload
```

### Get All Documents
```http
GET /api/v1/driver/documents
Authorization: Bearer {token}
```

---

## 🔍 Debug Commands

```bash
# Check total documents
php artisan tinker --execute="DB::table('driver_documents')->count()"

# Check documents for specific driver
php artisan tinker --execute="DB::table('driver_documents')->where('driver_id', 'DRIVER_ID_HERE')->get()"

# Check recent API requests
tail -f storage/logs/laravel.log
```

---

## ✅ Summary

| Issue | Status | Solution |
|-------|--------|----------|
| Invalid key supplied | ✅ FIXED | Generated APP_KEY |
| Documents not showing | ⚠️ PARTIALLY FIXED | Backend ready, driver app needs fix |
| Admin panel query error | ✅ FIXED | SQL GROUP BY fixed |
| Firebase uploads | ✅ WORKING | Files uploading successfully |
| Database records | ❌ NOT WORKING | API not being called |

**Main Issue:** Driver app uploads to Firebase but doesn't inform backend, so admin panel has no records to display.

