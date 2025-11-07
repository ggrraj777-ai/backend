# Firebase Document Verification - Admin Access

## ✅ System Workflow

### 1. **Driver Uploads Documents** (Mobile App)
- Driver registers in the mobile app
- Uploads documents to **Firebase Storage**
- Storage location: `gs://gauva-15d9a.firebasestorage.app/documents/{driver_id}/`
- Documents include:
  - 📄 Driving License (front + back)
  - 📄 RC Book (front + back)  
  - 📄 Aadhar Card (front + back)
  - 📷 Driver Photo

### 2. **Documents Saved in Database**
- Table: `driver_documents`
- Fields stored:
  - `driver_id` - Links to driver
  - `document_type` - Type of document
  - `document_number` - License/Aadhar/RC number
  - `front_image_url` - Firebase URL for front image
  - `back_image_url` - Firebase URL for back image
  - `firebase_front_path` - Firebase storage path
  - `firebase_back_path` - Firebase storage path
  - `verification_status` - `pending` / `approved` / `rejected`
  - `expiry_date` - Document expiry
  - `metadata` - Additional info (JSON)

### 3. **Admin Verification** (Admin Panel)
- Admin receives notification
- Reviews documents from Firebase
- Can approve or reject each document
- Driver gets activated upon approval

---

## 🎯 How to Access (Admin Panel)

### **New Menu Added:** ✅

**Location:** Sidebar → User Management → Driver Setup → **Document Verification**

**Route:** `/admin/driver/documents`

**Features:**
- ✅ Shows badge with pending document count
- ✅ Filter by status (Pending, Partial, Approved, Rejected, All)
- ✅ View all driver information
- ✅ One-click access to verify documents

---

## 📋 Document Verification Page

### **List View** (`/admin/driver/documents`)

Shows all drivers with:
- Driver profile photo
- Name and phone number
- Document count
- Verification status badge
- "View Documents" button

**Filter Options:**
- 🟡 **Pending** - Newly uploaded, waiting for verification
- 🟠 **Partial** - Some documents approved, some pending
- 🟢 **Approved** - All documents verified
- 🔴 **Rejected** - Has rejected documents
- ⚪ **All** - Show everyone

---

### **Detail View** (`/admin/driver/documents/show/{driverId}`)

Shows each document type with:
- **Document image viewer** (front and back)
- **Document number** (license/aadhar/RC)
- **Expiry date**
- **Current status** (pending/approved/rejected)
- **Action buttons:**
  - ✅ **Approve** - Mark document as verified
  - ❌ **Reject** - Reject with reason
  - 📥 **Download** - Download from Firebase

**Batch Actions:**
- ✅ **Approve All** - Approve all documents and activate driver

---

## 🔄 Verification Workflow

```
1. Driver uploads documents via mobile app
   ↓
2. Documents saved to Firebase Storage
   ↓
3. Database record created with status: "pending"
   ↓
4. Admin sees badge notification in sidebar
   ↓
5. Admin clicks "Document Verification"
   ↓
6. Admin reviews each document
   ↓
7. Admin approves or rejects
   ↓
8. If all approved: Driver is ACTIVATED (is_active = true)
   ↓
9. Driver receives notification
   ↓
10. Driver can start accepting rides
```

---

## 📊 Driver Status After Verification

### After Approval:
```php
document_verification_status = 'approved'
is_active = true
documents_verified_at = [current timestamp]
```

### After Rejection:
```php
document_verification_status = 'rejected'
is_active = false  // Driver cannot work
rejection_reason = [admin's message]
```

---

## 🎨 UI Features

### **Badge Notification** ✨
```blade
Document Verification [5]  // Shows pending count
```
- Red badge appears when there are pending documents
- Updates in real-time
- Clicking takes you directly to pending documents

### **Status Badges**
- 🟡 **Pending** - Yellow badge
- 🟢 **Approved** - Green badge  
- 🔴 **Rejected** - Red badge
- 🟠 **Partial** - Orange badge

---

## 🔐 Security & Permissions

### Required Permission:
- `user_view` - View drivers and documents
- `user_edit` - Approve/reject documents

### Firebase Integration:
- ✅ Secure download URLs (60 seconds validity)
- ✅ Proper authentication
- ✅ Direct Firebase Storage access
- ✅ Automatic URL generation

---

## 📱 Mobile App Integration

### API Endpoints Used by Driver App:
```http
POST /api/v1/driver/documents/license/upload
POST /api/v1/driver/documents/rc/upload
POST /api/v1/driver/documents/aadhar/upload
POST /api/v1/driver/documents/photo/upload
GET  /api/v1/driver/documents
DELETE /api/v1/driver/documents/{documentId}
```

### Upload Process:
1. Driver selects document from phone
2. App uploads to Firebase
3. Gets Firebase URL and path
4. Sends URL to backend API
5. Backend stores in `driver_documents` table
6. Driver sees "Pending verification" status

---

## ✅ Testing the Feature

### As Admin:
1. Go to **User Management** → **Driver Setup** → **Document Verification**
2. Should see list of drivers
3. Click "View Documents" on any driver
4. View all uploaded documents
5. Click "Approve" or "Reject" on each document
6. Try "Approve All" to activate driver

### As Driver (Mobile App):
1. Register new driver account
2. Upload all required documents
3. Wait for admin verification
4. Check status in app
5. Once approved, can start accepting rides

---

## 🐛 Troubleshooting

### "No documents found"
- Driver hasn't uploaded documents yet
- Check `driver_documents` table
- Verify Firebase connection

### "Download failed"
- Check Firebase credentials
- Verify storage bucket access
- Check `firebase_front_path` in database

### Documents not showing
- Check `deleted_at` is NULL
- Verify driver ID matches
- Check database connection

---

## 📝 Database Schema

```sql
CREATE TABLE `driver_documents` (
  `id` char(36) PRIMARY KEY,
  `driver_id` char(36) NOT NULL,
  `document_type` enum('driving_license','rc_book','aadhar_card','photo'),
  `document_number` varchar(100),
  `front_image_url` text,
  `back_image_url` text,
  `firebase_front_path` text,
  `firebase_back_path` text,
  `expiry_date` date,
  `verification_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text,
  `verified_by` char(36),
  `verified_at` timestamp,
  `metadata` json,
  `created_at` timestamp,
  `updated_at` timestamp,
  `deleted_at` timestamp,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`)
);
```

---

## 🎉 Summary

✅ **Menu Added:** "Document Verification" under Driver Setup  
✅ **Badge Notification:** Shows pending count  
✅ **Firebase Integration:** Direct download from Firebase Storage  
✅ **Complete Workflow:** Upload → Review → Approve/Reject → Activate  
✅ **User-Friendly:** Clear UI with status badges and actions  
✅ **Secure:** Temporary download URLs, proper permissions  

**Admin can now easily verify driver documents uploaded to Firebase!**

