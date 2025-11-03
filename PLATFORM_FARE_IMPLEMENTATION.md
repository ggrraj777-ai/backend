# GAUVA Platform Fare & Logic Implementation

## 📋 Overview

This document explains the implementation of the GAUVA platform fare and logic system for Bike, Auto, and Car models.

---

## 🗄️ Database Structure

### Tables Created

1. **`platform_charges`** - Stores platform charges configuration for each vehicle type
2. **`driver_daily_fees`** - Tracks daily fee deductions per driver
3. **`driver_day_passes`** - Tracks day pass purchases for car drivers
4. **`driver_trip_bonuses`** - Tracks trip counts and bonuses for auto drivers
5. **`customer_cashbacks`** - Tracks cashback credits for bike customers

---

## 🚗 Vehicle Type Configurations

### 1. BIKE Model

```php
Platform Charges:
- Per Trip Fee: ₹5
- Daily Fee: ₹7 (deducted after first trip)
- Customer Insurance: ₹1
- Driver Insurance: ₹1
- Total Insurance: ₹2

Cashback:
- Percentage: 10%
- Max Amount: ₹5 per ride
- Wallet Use Limit: ₹5 per ride
```

**Implementation:**
```php
use Modules\FareManagement\Service\PlatformFareService;

$fareService = new PlatformFareService();

// Calculate fare for bike trip
$fareBreakdown = $fareService->calculateTripFare(
    vehicleType: 'bike',
    baseFare: 100,
    driverId: $driverId,
    customerId: $customerId,
    hasDayPass: false
);

// Credit cashback to customer
$fareService->creditCashback(
    customerId: $customerId,
    tripId: $tripId,
    cashbackAmount: $fareBreakdown['cashback_amount'],
    vehicleType: 'bike'
);
```

**Fare Breakdown Example:**
```
Base Fare: ₹100
Platform Fee: ₹5
Daily Fee: ₹7 (first trip only)
Customer Insurance: ₹1
Driver Insurance: ₹1
Cashback: ₹5 (10% of ₹100, max ₹5)
Wallet Deduction: ₹5 (if available)

Customer Pays: ₹100 + ₹5 + ₹2 - ₹5 = ₹102
Driver Earns: ₹100 - ₹5 - ₹7 = ₹88 (first trip) or ₹93 (subsequent trips)
Platform Earns: ₹5 + ₹7 + ₹2 = ₹14 (first trip) or ₹7 (subsequent trips)
```

---

### 2. AUTO Model

```php
Platform Charges:
- Per Trip Fee: ₹3
- Daily Fee: ₹11 (deducted after first trip)
- Customer Insurance: ₹1
- Driver Insurance: ₹1
- Total Insurance: ₹2

Driver Bonus:
- Threshold: 20 trips per day
- Bonus Amount: ₹50
- No Cashback
```

**Implementation:**
```php
// Calculate fare for auto trip
$fareBreakdown = $fareService->calculateTripFare(
    vehicleType: 'auto',
    baseFare: 150,
    driverId: $driverId,
    customerId: null, // No cashback for auto
    hasDayPass: false
);

// Check and credit bonus after each trip
$bonusResult = $fareService->checkAndCreditDriverBonus(
    driverId: $driverId,
    vehicleType: 'auto'
);

if ($bonusResult['eligible']) {
    // Driver completed 20 trips, ₹50 bonus credited
    echo "Bonus credited: ₹{$bonusResult['bonus_amount']}";
}
```

**Fare Breakdown Example:**
```
Base Fare: ₹150
Platform Fee: ₹3
Daily Fee: ₹11 (first trip only)
Customer Insurance: ₹1
Driver Insurance: ₹1

Customer Pays: ₹150 + ₹3 + ₹2 = ₹155
Driver Earns: ₹150 - ₹3 - ₹11 = ₹136 (first trip) or ₹147 (subsequent trips)
Platform Earns: ₹3 + ₹11 + ₹2 = ₹16 (first trip) or ₹5 (subsequent trips)

After 20 trips: Driver gets ₹50 bonus in wallet
```

---

### 3. CAR Model

```php
Platform Charges:
- Per Trip Fee: ₹11 (without day pass)
- Day Pass: ₹55 (unlimited trips for the day)
- Customer Insurance: ₹2
- Driver Insurance: ₹2
- Total Insurance: ₹4
- No Cashback
```

**Implementation:**
```php
// Purchase day pass (optional)
$passResult = $fareService->purchaseDayPass(
    driverId: $driverId,
    vehicleType: 'car'
);

// Check if driver has day pass
$hasDayPass = $fareService->hasActiveDayPass(
    driverId: $driverId,
    vehicleType: 'car'
);

// Calculate fare for car trip
$fareBreakdown = $fareService->calculateTripFare(
    vehicleType: 'car',
    baseFare: 200,
    driverId: $driverId,
    customerId: null,
    hasDayPass: $hasDayPass
);
```

**Fare Breakdown Example (Without Day Pass):**
```
Base Fare: ₹200
Platform Fee: ₹11
Customer Insurance: ₹2
Driver Insurance: ₹2

Customer Pays: ₹200 + ₹11 + ₹4 = ₹215
Driver Earns: ₹200 - ₹11 = ₹189
Platform Earns: ₹11 + ₹4 = ₹15
```

**Fare Breakdown Example (With Day Pass):**
```
Day Pass: ₹55 (paid once at start of day)

Per Trip:
Base Fare: ₹200
Platform Fee: ₹0 (covered by day pass)
Customer Insurance: ₹2
Driver Insurance: ₹2

Customer Pays: ₹200 + ₹4 = ₹204
Driver Earns: ₹200 (no per-trip platform fee)
Platform Earns: ₹4 (insurance only)

If driver does 10 trips: Saves ₹110 - ₹55 = ₹55
```

---

## 🔄 Integration with Existing Trip Flow

### Step 1: On Trip Completion

```php
use Modules\FareManagement\Service\PlatformFareService;

public function completeTripAndCalculateFare($tripId)
{
    $trip = Trip::find($tripId);
    $fareService = new PlatformFareService();
    
    // Check if driver has day pass (for car only)
    $hasDayPass = false;
    if ($trip->vehicle_type === 'car') {
        $hasDayPass = $fareService->hasActiveDayPass(
            $trip->driver_id,
            'car'
        );
    }
    
    // Calculate complete fare breakdown
    $fareBreakdown = $fareService->calculateTripFare(
        vehicleType: $trip->vehicle_type,
        baseFare: $trip->estimated_fare,
        driverId: $trip->driver_id,
        customerId: $trip->customer_id,
        hasDayPass: $hasDayPass
    );
    
    // Update trip with fare details
    $trip->update([
        'platform_fee' => $fareBreakdown['platform_fee'],
        'daily_fee' => $fareBreakdown['daily_fee'],
        'customer_insurance' => $fareBreakdown['customer_insurance'],
        'driver_insurance' => $fareBreakdown['driver_insurance'],
        'cashback_amount' => $fareBreakdown['cashback_amount'],
        'wallet_deduction' => $fareBreakdown['wallet_deduction'],
        'final_fare' => $fareBreakdown['final_customer_payment'],
        'driver_earnings' => $fareBreakdown['driver_earnings'],
        'platform_earnings' => $fareBreakdown['platform_earnings'],
    ]);
    
    // Credit cashback for bike trips
    if ($trip->vehicle_type === 'bike' && $fareBreakdown['cashback_amount'] > 0) {
        $fareService->creditCashback(
            $trip->customer_id,
            $trip->id,
            $fareBreakdown['cashback_amount'],
            'bike'
        );
    }
    
    // Check and credit bonus for auto drivers
    if ($trip->vehicle_type === 'auto') {
        $bonusResult = $fareService->checkAndCreditDriverBonus(
            $trip->driver_id,
            'auto'
        );
        
        if ($bonusResult['eligible']) {
            // Notify driver about bonus
            // Send notification: "Congratulations! You've completed 20 trips today. ₹50 bonus credited!"
        }
    }
    
    return $fareBreakdown;
}
```

---

## 📊 Database Migrations

### Run Migrations

```bash
# Run all new migrations
php artisan migrate

# Or run specific module migrations
php artisan module:migrate FareManagement
```

### Seed Platform Charges

```bash
php artisan db:seed --class=Modules\\FareManagement\\Database\\Seeders\\PlatformChargesSeeder
```

---

## 🧪 Testing Examples

### Test Bike Trip

```php
$fareService = new PlatformFareService();

// First trip of the day
$result = $fareService->calculateTripFare('bike', 100, 'driver-uuid', 'customer-uuid');
/*
Expected Output:
[
    'base_fare' => 100,
    'platform_fee' => 5,
    'daily_fee' => 7,  // First trip
    'total_insurance' => 2,
    'cashback_amount' => 5,  // 10% of 100, max 5
    'wallet_deduction' => 5,  // If customer has balance
    'final_customer_payment' => 102,
    'driver_earnings' => 88,
    'platform_earnings' => 14,
]
*/

// Second trip of the day
$result2 = $fareService->calculateTripFare('bike', 100, 'driver-uuid', 'customer-uuid');
/*
Expected Output:
[
    'daily_fee' => 0,  // Already deducted
    'driver_earnings' => 95,  // No daily fee deduction
]
*/
```

### Test Auto Bonus

```php
// Simulate 20 trips
for ($i = 1; $i <= 20; $i++) {
    $fareService->calculateTripFare('auto', 150, 'driver-uuid');
    $bonusResult = $fareService->checkAndCreditDriverBonus('driver-uuid', 'auto');
    
    if ($i < 20) {
        echo "Trip {$i}: Remaining " . (20 - $i) . " trips for bonus\n";
    } else {
        echo "Trip 20: ₹50 bonus credited!\n";
        // $bonusResult['eligible'] === true
        // $bonusResult['bonus_amount'] === 50
    }
}
```

### Test Car Day Pass

```php
// Purchase day pass
$passResult = $fareService->purchaseDayPass('driver-uuid', 'car');
// Deducts ₹55 from driver wallet

// First trip with day pass
$result = $fareService->calculateTripFare('car', 200, 'driver-uuid', null, true);
/*
[
    'platform_fee' => 0,  // No per-trip fee
    'has_day_pass' => true,
    'driver_earnings' => 200,  // Full base fare
]
*/

// 10 trips later, driver saved ₹110 - ₹55 = ₹55
```

---

## 📱 API Response Examples

### Trip Fare Breakdown Response

```json
{
    "trip_id": "uuid",
    "vehicle_type": "bike",
    "fare_breakdown": {
        "base_fare": 100.00,
        "platform_fee": 5.00,
        "daily_fee": 7.00,
        "customer_insurance": 1.00,
        "driver_insurance": 1.00,
        "total_insurance": 2.00,
        "cashback_amount": 5.00,
        "wallet_deduction": 5.00,
        "final_customer_payment": 102.00,
        "driver_earnings": 88.00,
        "platform_earnings": 14.00,
        "is_first_trip_today": true
    }
}
```

### Driver Bonus Notification

```json
{
    "type": "bonus_credited",
    "message": "Congratulations! You've completed 20 trips today.",
    "bonus_amount": 50.00,
    "trip_count": 20,
    "wallet_balance": 1250.00
}
```

---

## ⚙️ Configuration

All platform charges are configurable via:

1. **Database**: `platform_charges` table
2. **Config File**: `config/platform_charges.php`

To update charges:

```php
// Update via database
DB::table('platform_charges')
    ->where('vehicle_type', 'bike')
    ->update(['per_trip_fee' => 6.00]);

// Or via config (requires cache clear)
config(['platform_charges.bike.per_trip_fee' => 6]);
```

---

## 🔐 Important Business Rules

### Daily Fee Logic
- ✅ Deduct **only after first completed trip**
- ✅ Deduct **once per day**
- ✅ If driver does **0 trips** → **no daily fee**

### Cashback Logic (Bike Only)
- ✅ Calculate cashback **first**
- ✅ Add to **customer wallet**
- ✅ Next trip: allow **max ₹5 wallet usage**

### Driver Wallet Handles
- ✅ Daily fee deductions
- ✅ Per trip platform fee
- ✅ Insurance
- ✅ Driver bonus (auto)
- ✅ Day pass (car)

---

## 📞 Support & Maintenance

### Common Queries

**Q: How to change platform fees?**
```bash
# Update in database
php artisan tinker
DB::table('platform_charges')->where('vehicle_type', 'bike')->update(['per_trip_fee' => 6]);
```

**Q: How to reset daily fees for testing?**
```bash
# Clear today's daily fee records
DB::table('driver_daily_fees')->where('fee_date', today())->delete();
```

**Q: How to check driver's trip count for bonus?**
```php
$record = DB::table('driver_trip_bonuses')
    ->where('driver_id', $driverId)
    ->where('bonus_date', today())
    ->first();
echo "Trips: {$record->trip_count}/20";
```

---

## ✅ Implementation Checklist

- [x] Database migrations created
- [x] Platform charges seeder created
- [x] PlatformFareService class created
- [x] Configuration file created
- [x] Documentation completed
- [ ] Integrate with existing trip completion flow
- [ ] Add API endpoints for day pass purchase
- [ ] Add admin panel for managing platform charges
- [ ] Add driver dashboard to show daily stats
- [ ] Add customer wallet transaction history
- [ ] Add unit tests for fare calculations
- [ ] Deploy to Cloud Run

---

## 🚀 Next Steps

1. **Run Migrations:**
   ```bash
   php artisan migrate
   php artisan db:seed --class=Modules\\FareManagement\\Database\\Seeders\\PlatformChargesSeeder
   ```

2. **Integrate with Trip Controller:**
   - Update trip completion logic to use `PlatformFareService`
   - Add fare breakdown to trip response

3. **Add API Endpoints:**
   - `POST /api/driver/purchase-day-pass` (for car drivers)
   - `GET /api/driver/daily-stats` (show trips, earnings, bonus progress)
   - `GET /api/customer/wallet/cashback-history`

4. **Test Thoroughly:**
   - Test all vehicle types
   - Test daily fee deduction logic
   - Test bonus crediting
   - Test day pass functionality
   - Test cashback calculations

5. **Deploy:**
   ```bash
   deploy-simple.bat
   ```

---

**Status**: ✅ Implementation Complete - Ready for Integration & Testing
