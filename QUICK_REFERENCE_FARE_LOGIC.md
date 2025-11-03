# GAUVA Fare Logic - Quick Reference

## 🚴 BIKE

```
Per Trip Fee: ₹5
Daily Fee: ₹7 (first trip only)
Insurance: ₹1 + ₹1 = ₹2
Cashback: 10% (max ₹5)
Wallet Limit: ₹5 per ride
```

**Example Trip (₹100 base fare):**
```
Customer Pays: ₹100 + ₹5 + ₹2 - ₹5 (wallet) = ₹102
Driver Earns: ₹100 - ₹5 - ₹7 (first trip) = ₹88
Cashback: ₹5 → Customer Wallet
```

---

## 🛺 AUTO

```
Per Trip Fee: ₹3
Daily Fee: ₹11 (first trip only)
Insurance: ₹1 + ₹1 = ₹2
Bonus: ₹50 after 20 trips/day
No Cashback
```

**Example Trip (₹150 base fare):**
```
Customer Pays: ₹150 + ₹3 + ₹2 = ₹155
Driver Earns: ₹150 - ₹3 - ₹11 (first trip) = ₹136
After 20 trips: +₹50 bonus → Driver Wallet
```

---

## 🚗 CAR

```
Per Trip Fee: ₹11 OR
Day Pass: ₹55 (unlimited trips)
Insurance: ₹2 + ₹2 = ₹4
No Cashback
```

**Example Trip WITHOUT Day Pass (₹200 base fare):**
```
Customer Pays: ₹200 + ₹11 + ₹4 = ₹215
Driver Earns: ₹200 - ₹11 = ₹189
```

**Example Trip WITH Day Pass (₹200 base fare):**
```
Customer Pays: ₹200 + ₹4 = ₹204
Driver Earns: ₹200 (no per-trip fee)
Day Pass Cost: ₹55 (paid once)
Break-even: 5 trips
```

---

## 💻 Code Usage

### Calculate Fare
```php
use Modules\FareManagement\Service\PlatformFareService;

$fareService = new PlatformFareService();

$fareBreakdown = $fareService->calculateTripFare(
    vehicleType: 'bike',  // or 'auto', 'car'
    baseFare: 100,
    driverId: $driverId,
    customerId: $customerId,
    hasDayPass: false
);
```

### Credit Cashback (Bike Only)
```php
$fareService->creditCashback(
    customerId: $customerId,
    tripId: $tripId,
    cashbackAmount: $fareBreakdown['cashback_amount'],
    vehicleType: 'bike'
);
```

### Check Auto Bonus
```php
$bonusResult = $fareService->checkAndCreditDriverBonus(
    driverId: $driverId,
    vehicleType: 'auto'
);

if ($bonusResult['eligible']) {
    // ₹50 bonus credited!
}
```

### Purchase Car Day Pass
```php
$passResult = $fareService->purchaseDayPass(
    driverId: $driverId,
    vehicleType: 'car'
);
```

---

## 📊 Database Tables

1. **platform_charges** - Configuration for each vehicle type
2. **driver_daily_fees** - Daily fee tracking
3. **driver_day_passes** - Car day pass records
4. **driver_trip_bonuses** - Auto bonus tracking
5. **customer_cashbacks** - Bike cashback records

---

## 🚀 Setup Commands

```bash
# Run migrations
php artisan migrate

# Seed platform charges
php artisan db:seed --class=Modules\\FareManagement\\Database\\Seeders\\PlatformChargesSeeder

# Test in tinker
php artisan tinker
$service = new \Modules\FareManagement\Service\PlatformFareService();
$result = $service->calculateTripFare('bike', 100, 'driver-id', 'customer-id');
print_r($result);
```

---

## ✅ Key Rules

1. **Daily Fee**: Deduct only after first completed trip
2. **Cashback**: Calculate first, then add to wallet
3. **Wallet Usage**: Max ₹5 per ride (bike only)
4. **Auto Bonus**: Credit after 20th trip of the day
5. **Car Day Pass**: Valid for current day only

---

**For detailed documentation, see:** `PLATFORM_FARE_IMPLEMENTATION.md`
