<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_daily_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('users')->onDelete('cascade');
            $table->date('activity_date');
            $table->string('vehicle_type', 20); // bike, auto, car
            
            // Trip counting
            $table->integer('total_accepted_trips')->default(0);
            $table->integer('completed_trips')->default(0);
            $table->integer('customer_cancelled_after_start')->default(0);
            $table->integer('driver_cancelled')->default(0);
            $table->integer('counted_trips')->default(0); // completed + customer_cancelled_after_start
            
            // Target and fees
            $table->integer('target_trips'); // 9 for bike/auto, 10 for car
            $table->decimal('daily_fee', 10, 2)->default(0);
            $table->decimal('per_trip_fee', 10, 2)->default(0);
            
            // Status
            $table->boolean('free_access_achieved')->default(false);
            $table->boolean('fee_deducted')->default(false);
            $table->decimal('fee_amount_deducted', 10, 2)->nullable();
            $table->timestamp('fee_deducted_at')->nullable();
            
            // Welcome period tracking
            $table->integer('days_since_joining')->default(0);
            $table->boolean('is_welcome_period')->default(false);
            
            // Additional info
            $table->decimal('wallet_balance_before', 10, 2)->nullable();
            $table->decimal('wallet_balance_after', 10, 2)->nullable();
            $table->text('deduction_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->unique(['driver_id', 'activity_date'], 'driver_date_unique');
            $table->index('activity_date');
            $table->index('vehicle_type');
            $table->index('free_access_achieved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_daily_activities');
    }
};

