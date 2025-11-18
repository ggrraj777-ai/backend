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
        Schema::table('users', function (Blueprint $table) {
            $table->date('driver_joined_date')->nullable()->after('user_type');
            $table->integer('total_days_active')->default(0)->after('driver_joined_date');
            $table->boolean('in_welcome_period')->default(true)->after('total_days_active');
            $table->boolean('daily_fee_eligible')->default(false)->after('in_welcome_period');
            $table->boolean('can_accept_trips')->default(true)->after('daily_fee_eligible');
            $table->decimal('minimum_required_balance', 10, 2)->default(50.00)->after('can_accept_trips');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'driver_joined_date',
                'total_days_active',
                'in_welcome_period',
                'daily_fee_eligible',
                'can_accept_trips',
                'minimum_required_balance'
            ]);
        });
    }
};

