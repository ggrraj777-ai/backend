<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDriverDayPassesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('driver_day_passes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id');
            $table->string('vehicle_type')->default('car'); // Only for car
            $table->date('pass_date');
            $table->decimal('pass_amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('purchased_at');
            $table->timestamps();
            
            $table->unique(['driver_id', 'pass_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('driver_day_passes');
    }
}
