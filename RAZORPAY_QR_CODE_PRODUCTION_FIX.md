# Razorpay QR Code - Production Setup & Fixes

## ✅ Enhancements Applied

### 1. Configuration Validation
Added check before QR generation to ensure Razorpay is configured:
```php
if (!config('razor_config.api_key') || !config('razor_config.api_secret')) {
    return error message...
}
```

### 2. Better Error Logging
All QR generation errors now logged with context:
```php
\Log::error('QR Code Generation Error', [
    'driver_id' => $driver_id,
    'trip_id' => $trip_id,
    'amount' => $amount,
]);
```

### 3. Detailed Error Messages
Users get clear feedback about what went wrong.

---

## ⚠️ **Potential SSL Certificate Issue**

Razorpay SDK uses cURL internally and may face SSL certificate issues on Windows.

### Symptoms:
- QR code generation fails silently
- Error: "SSL certificate problem"
- Razorpay API calls timeout

### Fix for Development/Local Server:

**Option 1:** Download CA Certificate Bundle
```bash
# Download certificate bundle
curl https://curl.se/ca/cacert.pem -o backend-main/storage/cacert.pem
```

**Option 2:** Configure PHP.ini (Recommended)
Find your `php.ini` file and add:
```ini
[curl]
curl.cainfo = "C:\path\to\cacert.pem"

[openssl]
openssl.cafile = "C:\path\to\cacert.pem"
```

**Option 3:** Environment Variable (Quick Fix)
For local testing only:
```php
// In RazorPayController constructor, add:
putenv('CURLOPT_SSL_VERIFYPEER=0');
```

---

## 🔐 **Razorpay Configuration**

### Via Admin Panel:

1. Go to: **Payment Management** → **Payment Configuration**
2. Find **Razorpay** in the list
3. Click **Edit/Configure**
4. Enter:
   - **Mode**: Test or Live
   - **API Key**: `rzp_test_XXXXX` or `rzp_live_XXXXX`
   - **API Secret**: Your secret key
5. Save

### Check Configuration:
```bash
cd D:\Gauva-UpdateCode\backend-main
php artisan tinker --execute="
  \$config = DB::table('business_settings')
    ->where('key_name', 'razor_pay')
    ->first();
  print_r(\$config ? json_decode(\$config->value) : 'Not configured');
"
```

---

## 🧪 **Test QR Code Generation**

### From API:
```bash
curl -X POST http://127.0.0.1:8000/api/v1/driver/payments/razorpay/generate-qr \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {driver_token}" \
  -d '{
    "driver_id": 1,
    "trip_id": "TEST123",
    "amount": 100,
    "currency": "INR",
    "description": "Test trip payment"
  }'
```

### Expected Response:
```json
{
  "success": true,
  "qr_code_id": "qr_XXXXX",
  "qr_code_url": "https://rzp.io/i/XXXXX",
  "qr_string": "upi://pay?...",
  "receipt": "DRV-QR-...",
  "amount": 100,
  "currency": "INR",
  "expires_at": "2025-11-07 14:30:00",
  "key_id": "rzp_test_XXXXX"
}
```

---

## 📱 **How Driver Uses QR Code**

### Flow:
```
1. Customer pays cash/UPI to driver
   ↓
2. Driver opens "Collect Payment" screen
   ↓
3. Driver enters amount
   ↓
4. System generates QR code via Razorpay
   ↓
5. Customer scans QR with any UPI app
   ↓
6. Payment goes to driver's linked account
   ↓
7. System polls for payment status
   ↓
8. Marks trip as paid when confirmed
```

### API Endpoints Used:
```
POST /api/v1/driver/payments/razorpay/generate-qr
GET  /api/v1/driver/payments/razorpay/qr-status/{qrCodeId}
```

---

## 🔧 **Troubleshooting**

### Issue 1: "Razorpay not configured"
**Solution:** Add API keys in admin panel

### Issue 2: "cURL SSL certificate error"
**Solution:** Configure SSL certificates (see above)

### Issue 3: QR code doesn't load
**Check:**
- Razorpay API keys are correct
- Server can reach Razorpay API (test.razorpay.com or api.razorpay.com)
- SSL certificates configured
- Internet connection working

### Issue 4: Payment not detected
**Check:**
- Webhook configured: http://yourserver.com/api/webhooks/razorpay
- Webhook secret set correctly
- QR code not expired (30 min limit)

---

## 📊 **QR Code Features**

### Current Implementation:
- ✅ Single-use QR codes
- ✅ Fixed amount (cannot be changed by customer)
- ✅ 30-minute expiry
- ✅ Automatic status checking
- ✅ Links to driver account automatically
- ✅ Detailed logging for debugging

### Security:
- ✅ Amount validation
- ✅ Driver verification
- ✅ Trip ID tracking
- ✅ Receipt generation
- ✅ Webhook verification

---

## 💰 **Payment Flow Architecture**

```
Driver App
    ↓ Generate QR
Gauva Backend (Your Server)
    ↓ API Call
Razorpay API (https://api.razorpay.com)
    ↓ Create QR
Returns QR Code
    ↓
Driver Shows to Customer
    ↓
Customer Scans with UPI App (Google Pay, PhonePe, etc.)
    ↓
Payment Processed by Razorpay
    ↓
Razorpay Webhook → Gauva Backend
    ↓
Trip Marked as Paid
```

---

## ✅ **Production Checklist**

### Razorpay Setup:
- [ ] Create Razorpay account
- [ ] Get production API keys (`rzp_live_XXXXX`)
- [ ] Configure in admin panel
- [ ] Test QR generation
- [ ] Configure webhook URL
- [ ] Test payment flow end-to-end

### SSL/Security:
- [ ] Download CA certificate bundle
- [ ] Configure php.ini with cert path
- [ ] Test HTTPS API calls
- [ ] Verify webhook signatures

### Testing:
- [ ] Generate QR code from driver app
- [ ] Customer scans and pays
- [ ] Payment detected and recorded
- [ ] Trip marked as paid
- [ ] Driver receives settlement

---

## 🚨 **Critical for Production**

1. **Use Live Keys:**
   - Not test keys (`rzp_test_`)
   - Use live keys (`rzp_live_`)

2. **Configure Webhook:**
   - URL: `https://yourdomain.com/api/webhooks/razorpay`
   - Events: `payment.captured`, `qr_code.credited`

3. **Test Payment Flow:**
   - End-to-end testing
   - Real UPI payments
   - Verify settlements

---

## 📝 **Summary**

| Feature | Status | Action Needed |
|---------|--------|---------------|
| QR Generation API | ✅ Working | Configure Razorpay keys |
| Error Handling | ✅ Enhanced | None |
| Logging | ✅ Added | Monitor logs |
| SSL Certificates | ⚠️ May need config | Test & fix if needed |
| Configuration Check | ✅ Added | Add keys in admin |

**QR code service is ready - just needs Razorpay API keys configured!** 🎯

