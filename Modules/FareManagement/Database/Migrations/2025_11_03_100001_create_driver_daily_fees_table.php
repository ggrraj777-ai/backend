<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDriverDailyFeesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('driver_daily_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id');
            $table->string('vehicle_type'); // bike, auto, car
            $table->date('fee_date');
            $table->decimal('daily_fee_amount', 10, 2);
            $table->boolean('is_deducted')->default(false);
            $table->timestamp('deducted_at')->nullable();
            $table->foreignUuid('first_trip_id')->nullable(); // Trip that triggered the deduction
            $table->timestamps();
            
            $table->unique(['driver_id', 'vehicle_type', 'fee_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('driver_daily_fees');
    }
}
