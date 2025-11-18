# Fix: "Service not available in this area"

## 🔴 Problem

User app shows: **"Service not available in this area"**

**Why:** Your current GPS location is outside the configured service zones.

---

## 📍 **Configured Zones:**

Currently, the system has **3 active zones**:

1. **Amalapuram** (Zone ID: 1)
2. **rajamundry** (Zone ID: 2)
3. **kakinada** (Zone ID: 3)

The app checks if your location falls within any zone's polygon boundaries. If not, it shows "Service not available".

---

## ✅ **Solution: Add a Test Zone for Your Location**

### Quick Fix: Create a Global Test Zone

This will make the entire area serviceable for testing:

```sql
-- Create a large test zone covering your current area
INSERT INTO zones (
    id,
    name,
    readable_id,
    coordinates,
    is_active,
    extra_fare_status,
    extra_fare_fee,
    created_at,
    updated_at
) VALUES (
    UUID(),
    'Test Zone - Development',
    4,
    ST_GeomFromText('POLYGON((
        77.0 15.0,  
        84.0 15.0,  
        84.0 20.0,  
        77.0 20.0,  
        77.0 15.0
    ))'),  -- Large area covering Andhra Pradesh
    1,  -- is_active
    0,  -- no extra fare
    0,
    NOW(),
    NOW()
);
```

This creates a HUGE test zone that covers a large area!

---

## 📋 **Better Solution: Create Accurate Zone**

### Step 1: Get Your Current Location

Open the user app and note the coordinates when it shows the error.

Or check from the backend logs - it will show the lat/lng that was checked.

### Step 2: Add Zone via Admin Panel

1. Go to: http://127.0.0.1:8000/admin/zone
2. Click "Add New Zone"
3. Draw a polygon around your test area on the map
4. Name it: "Test Zone"
5. Save

---

## 🎯 **Easiest Solution: Run This Script**

Let me create a script that adds a global test zone:

```bash
cd D:\Gauva-UpdateCode\backend-main
php artisan tinker --execute="
  DB::table('zones')->insert([
    'id' => \Illuminate\Support\Str::uuid()->toString(),
    'name' => 'Global Test Zone',
    'readable_id' => 4,
    'coordinates' => DB::raw(\"ST_GeomFromText('POLYGON((76.5 14.5, 85.0 14.5, 85.0 20.5, 76.5 20.5, 76.5 14.5))')\"),
    'is_active' => 1,
    'extra_fare_status' => 0,
    'extra_fare_fee' => 0,
    'created_at' => now(),
    'updated_at' => now(),
  ]);
  echo 'Global Test Zone created successfully!';
"
```

---

## 📱 **How Zone Checking Works**

### User App Flow:
```
1. User clicks "Use current location"
   ↓
2. App gets GPS coordinates (lat, lng)
   ↓
3. Calls API: /api/customer/config/get-zone-id?lat=XX&lng=YY
   ↓
4. Backend checks if point is within ANY zone polygon
   ↓
5. If found: Returns zone_id ✅
   If not found: Returns 403 ❌
   ↓
6. App shows "Service not available" if no zone
```

### Backend Query:
```php
$point = new Point($lat, $lng);
$zone = Zone::whereContains('coordinates', $point)
    ->where('is_active', 1)
    ->first();
```

Uses **spatial geometry** to check if point is inside polygon!

---

## 🧪 **Test Commands**

### Check if a location is in any zone:
```bash
php artisan tinker --execute="
  \$point = new \MatanYadaev\EloquentSpatial\Objects\Point(16.9891, 82.2475);  // Kakinada
  \$zone = \Modules\ZoneManagement\Entities\Zone::whereContains('coordinates', \$point)->where('is_active', 1)->first();
  echo \$zone ? 'Zone found: ' . \$zone->name : 'No zone found';
"
```

### List all zone coordinates:
```bash
php artisan tinker --execute="
  \Modules\ZoneManagement\Entities\Zone::all()->each(function(\$z) {
    echo \$z->name . ': ' . \$z->coordinates . PHP_EOL;
  });
"
```

---

## 🎨 **Admin Panel - Zone Management**

### View Zones:
- URL: http://127.0.0.1:8000/admin/zone
- See all configured zones
- See zone boundaries on map
- Edit zone polygons

### Add New Zone:
1. Click "Add Zone"
2. Draw polygon on map (click to add points)
3. Name the zone
4. Configure fare settings
5. Save

### Edit Existing Zone:
1. Click edit icon on any zone
2. Modify polygon boundaries
3. Can expand to include more areas
4. Save changes

---

## ⚡ **Quick Test Zone Script**

Run this to create a test zone immediately:

```bash
cd D:\Gauva-UpdateCode\backend-main

php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

DB::unprepared(\"
  INSERT INTO zones (id, name, readable_id, coordinates, is_active, extra_fare_status, extra_fare_fee, created_at, updated_at)
  VALUES (
    UUID(),
    'Test Zone - Wide Coverage',
    4,
    ST_GeomFromText('POLYGON((76.5 14.5, 85.0 14.5, 85.0 20.5, 76.5 20.5, 76.5 14.5))'),
    1, 0, 0,
    NOW(), NOW()
  )
\");

echo 'Test zone created!';
"
```

---

## 📍 **Zone Coordinates Reference**

Your existing zones cover:
- **Amalapuram**: Small area around Amalapuram city
- **Rajamundry**: Small area around Rajamundry city  
- **Kakinada**: Small area around Kakinada city

If you're testing from **outside** these cities, you need to add a zone!

---

## ✅ **After Adding Zone:**

1. **Restart User App** (or just retry location)
2. Click "Use current location"
3. App will call getZone API
4. Backend will find the new test zone
5. Should show **zone selector** instead of error! ✅

---

## 🎯 **Summary**

| Issue | Cause | Solution |
|-------|-------|----------|
| "Service not available" | Location outside zones | Add test zone |
| Only 3 cities covered | Limited zone setup | Create global zone |
| Testing blocked | No zone for your location | Run script above |

**Run the test zone script and your location will be covered!** 🚀

