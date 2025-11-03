<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomerCashbacksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_cashbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id');
            $table->foreignUuid('trip_id');
            $table->string('vehicle_type')->default('bike'); // Only for bike
            $table->decimal('trip_fare', 10, 2);
            $table->decimal('cashback_percent', 5, 2)->default(10); // 10%
            $table->decimal('cashback_amount', 10, 2);
            $table->decimal('max_cashback', 10, 2)->default(5); // Max ₹5
            $table->boolean('is_credited')->default(false);
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_cashbacks');
    }
}
