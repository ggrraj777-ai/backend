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
        Schema::table('trip_request_fees', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_request_fees', 'platform_fee')) {
                $table->decimal('platform_fee', 16, 2)->default(0)->after('admin_commission');
            }
            if (!Schema::hasColumn('trip_request_fees', 'daily_fee')) {
                $table->decimal('daily_fee', 16, 2)->default(0)->after('platform_fee');
            }
            if (!Schema::hasColumn('trip_request_fees', 'customer_insurance')) {
                $table->decimal('customer_insurance', 16, 2)->default(0)->after('daily_fee');
            }
            if (!Schema::hasColumn('trip_request_fees', 'driver_insurance')) {
                $table->decimal('driver_insurance', 16, 2)->default(0)->after('customer_insurance');
            }
            if (!Schema::hasColumn('trip_request_fees', 'total_insurance')) {
                $table->decimal('total_insurance', 16, 2)->default(0)->after('driver_insurance');
            }
            if (!Schema::hasColumn('trip_request_fees', 'cashback_amount')) {
                $table->decimal('cashback_amount', 16, 2)->default(0)->after('total_insurance');
            }
            if (!Schema::hasColumn('trip_request_fees', 'wallet_deduction')) {
                $table->decimal('wallet_deduction', 16, 2)->default(0)->after('cashback_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_request_fees', function (Blueprint $table) {
            $table->dropColumn([
                'platform_fee',
                'daily_fee',
                'customer_insurance',
                'driver_insurance',
                'total_insurance',
                'cashback_amount',
                'wallet_deduction'
            ]);
        });
    }
};

