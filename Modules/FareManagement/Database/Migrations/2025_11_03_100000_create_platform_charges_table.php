<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlatformChargesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('platform_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vehicle_type'); // bike, auto, car
            $table->decimal('per_trip_fee', 10, 2)->default(0);
            $table->decimal('daily_fee', 10, 2)->default(0);
            $table->decimal('customer_insurance', 10, 2)->default(0);
            $table->decimal('driver_insurance', 10, 2)->default(0);
            $table->decimal('cashback_percent', 5, 2)->default(0); // For bike only
            $table->decimal('cashback_max_amount', 10, 2)->default(0); // For bike only
            $table->decimal('wallet_use_limit', 10, 2)->default(0); // For bike only
            $table->integer('bonus_trip_threshold')->default(0); // For auto: 20 trips
            $table->decimal('bonus_amount', 10, 2)->default(0); // For auto: ₹50
            $table->decimal('day_pass_fee', 10, 2)->default(0); // For car: ₹55
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('platform_charges');
    }
}
