<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_fee_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vehicle_type', 20)->unique(); // bike, auto, car
            $table->integer('daily_target_trips');
            $table->decimal('daily_fee', 10, 2);
            $table->decimal('per_trip_fee', 10, 2);
            $table->decimal('minimum_wallet_balance', 10, 2);
            $table->integer('welcome_period_days')->default(3);
            $table->integer('max_allowed_cancellations')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('description_en')->nullable();
            $table->text('description_te')->nullable();
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
                'description_en' => 'Complete 9 trips daily for free access',
                'description_te' => 'రోజుకు 9 ట్రిప్స్ పూర్తి చేస్తే ఫ్రీ యాక్సెస్',
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
                'description_en' => 'Complete 9 trips daily for free access',
                'description_te' => 'రోజుకు 9 ట్రిప్స్ పూర్తి చేస్తే ఫ్రీ యాక్సెస్',
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
                'description_en' => 'Complete 10 trips daily for free access',
                'description_te' => 'రోజుకు 10 ట్రిప్స్ పూర్తి చేస్తే ఫ్రీ యాక్సెస్',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_fee_configurations');
    }
};

