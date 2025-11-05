<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDriverTripBonusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('driver_trip_bonuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id');
            $table->string('vehicle_type')->default('auto'); // Only for auto
            $table->date('bonus_date');
            $table->integer('trip_count')->default(0);
            $table->integer('bonus_threshold')->default(20); // 20 trips for auto
            $table->decimal('bonus_amount', 10, 2)->default(50); // ₹50 for auto
            $table->boolean('is_credited')->default(false);
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
            
            $table->unique(['driver_id', 'bonus_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('driver_trip_bonuses');
    }
}
