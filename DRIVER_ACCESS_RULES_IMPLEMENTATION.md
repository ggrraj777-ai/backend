# 🚀 GAUVA Driver Access Rules - Implementation Guide

## 📋 **System Overview**

A comprehensive daily fee and trip target system for GAUVA drivers with:
- Welcome period (first 3 days free)
- Vehicle-specific trip targets
- Automatic daily fee deduction
- Bilingual support (English + Telugu)

---

## 🎯 **Business Rules**

### **1. Wallet Top-Up Requirement**
- **Bike/Auto:** Minimum ₹50
- **Car:** Minimum ₹100

### **2. Welcome Period**
- **First 3 days:** Completely FREE
- **No deductions** even if 0 trips

### **3. Daily Trip Targets (From Day 4)**

| Vehicle | Daily Target | Daily Fee (if not achieved) | Per Trip Fee | Free Access |
|---------|--------------|----------------------------|--------------|-------------|
| Bike    | 9 trips      | ₹7                         | ₹5/trip      | 9 trips = Free |
| Auto    | 9 trips      | ₹11                        | ₹3/trip      | 9 trips = Free |
| Car     | 10 trips     | ₹55                        | ₹11/trip     | 10 trips = Free |

### **4. Deduction Logic**
- **0 trips** → No deduction (no activity = no charge)
- **1-8 trips** (Bike/Auto) or **1-9 trips** (Car) → Daily fee deducted
- **9 trips** (Bike/Auto) or **10 trips** (Car) → FREE ACCESS

### **5. Cancellation Rules**
- **Customer cancels after driver started** → Counts toward target
- **Customer cancels before driver starts** → Does NOT count
- **Driver cancels once** → Okay, not counted
- **Driver cancels 2+ times** → Free access cancelled, fee deducted

---

## 🗄️ **DATABASE SCHEMA**

### **Migration 1: Driver Daily Activity Table**

```php
// database/migrations/xxxx_create_driver_daily_activities_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('driver_daily_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->date('activity_date');
            $table->string('vehicle_type'); // bike, auto, car
            $table->integer('total_trips')->default(0);
            $table->integer('completed_trips')->default(0);
            $table->integer('customer_cancelled_after_start')->default(0);
            $table->integer('driver_cancelled')->default(0);
            $table->integer('target_trips'); // 9 for bike/auto, 10 for car
            $table->decimal('daily_fee', 10, 2)->default(0);
            $table->boolean('fee_deducted')->default(false);
            $table->boolean('free_access_achieved')->default(false);
            $table->decimal('fee_amount_deducted', 10, 2)->nullable();
            $table->timestamp('fee_deducted_at')->nullable();
            $table->integer('days_since_joining')->default(0);
            $table->boolean('is_welcome_period')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['driver_id', 'activity_date']);
            $table->index('activity_date');
            $table->index(['driver_id', 'activity_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_daily_activities');
    }
};
```

### **Migration 2: Driver Fee Configuration Table**

```php
// database/migrations/xxxx_create_driver_fee_configurations_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('driver_fee_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vehicle_type')->unique(); // bike, auto, car
            $table->integer('daily_target_trips');
            $table->decimal('daily_fee', 10, 2);
            $table->decimal('per_trip_fee', 10, 2);
            $table->decimal('minimum_wallet_balance', 10, 2);
            $table->integer('welcome_period_days')->default(3);
            $table->integer('max_allowed_cancellations')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // Insert default configurations
        DB::table('driver_fee_configurations')->insert([
            [
                'id' => Str::uuid(),
                'vehicle_type' => 'bike',
                'daily_target_trips' => 9,
                'daily_fee' => 7.00,
                'per_trip_fee' => 5.00,
                'minimum_wallet_balance' => 50.00,
                'welcome_period_days' => 3,
                'max_allowed_cancellations' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'vehicle_type' => 'auto',
                'daily_target_trips' => 9,
                'daily_fee' => 11.00,
                'per_trip_fee' => 3.00,
                'minimum_wallet_balance' => 50.00,
                'welcome_period_days' => 3,
                'max_allowed_cancellations' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'vehicle_type' => 'car',
                'daily_target_trips' => 10,
                'daily_fee' => 55.00,
                'per_trip_fee' => 11.00,
                'minimum_wallet_balance' => 100.00,
                'welcome_period_days' => 3,
                'max_allowed_cancellations' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('driver_fee_configurations');
    }
};
```

### **Migration 3: Add columns to users table**

```php
// database/migrations/xxxx_add_driver_access_fields_to_users_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('driver_joined_date')->nullable()->after('user_type');
            $table->integer('total_days_active')->default(0)->after('driver_joined_date');
            $table->boolean('in_welcome_period')->default(true)->after('total_days_active');
            $table->boolean('daily_fee_eligible')->default(false)->after('in_welcome_period');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'driver_joined_date',
                'total_days_active',
                'in_welcome_period',
                'daily_fee_eligible'
            ]);
        });
    }
};
```

---

## 📁 **File Structure**

```
backend-main/
├── app/
│   ├── Models/
│   │   ├── DriverDailyActivity.php          [NEW]
│   │   └── DriverFeeConfiguration.php       [NEW]
│   ├── Services/
│   │   ├── DriverAccessRulesService.php     [NEW]
│   │   └── DailyFeeDeductionService.php     [NEW]
│   └── Console/
│       └── Commands/
│           └── ProcessDailyFeeDeduction.php [NEW]
├── Modules/
│   └── DriverManagement/
│       ├── Http/Controllers/
│       │   └── Api/V1/
│       │       └── DriverAccessController.php [NEW]
│       └── Resources/
│           └── lang/
│               ├── en/
│               │   └── driver_access.php     [NEW]
│               └── te/
│                   └── driver_access.php     [NEW]
└── database/
    └── migrations/
        ├── xxxx_create_driver_daily_activities_table.php      [NEW]
        ├── xxxx_create_driver_fee_configurations_table.php    [NEW]
        └── xxxx_add_driver_access_fields_to_users_table.php   [NEW]
```

---

## 📝 **Implementation Steps**

I'll create all necessary files for this system. This includes:

1. ✅ Database migrations (3 files)
2. ✅ Models (2 files)
3. ✅ Service classes (2 files)
4. ✅ API controllers (1 file)
5. ✅ Scheduled command for daily processing
6. ✅ Translation files (English + Telugu)
7. ✅ API routes
8. ✅ Admin panel UI
9. ✅ Driver app integration points

**Total: ~15 new files**

---

## 🎯 **Should I Proceed?**

This will create a complete Driver Access Rules system with:
- Automatic daily fee deduction
- Trip tracking per vehicle type
- Welcome period management
- Cancellation handling
- Wallet balance checking
- Bilingual support

**Estimated time:** ~30-45 minutes to create all files

**Do you want me to implement this complete system now?**

Reply with "yes" and I'll create all the files! 🚀

