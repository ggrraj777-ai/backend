<?php

namespace Modules\FareManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformChargesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $platformCharges = [
            [
                'id' => Str::uuid(),
                'vehicle_type' => 'bike',
                'per_trip_fee' => 5.00,
                'daily_fee' => 7.00,
                'customer_insurance' => 1.00,
                'driver_insurance' => 1.00,
                'cashback_percent' => 10.00,
                'cashback_max_amount' => 5.00,
                'wallet_use_limit' => 5.00,
                'bonus_trip_threshold' => 0,
                'bonus_amount' => 0.00,
                'day_pass_fee' => 0.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'vehicle_type' => 'auto',
                'per_trip_fee' => 3.00,
                'daily_fee' => 11.00,
                'customer_insurance' => 1.00,
                'driver_insurance' => 1.00,
                'cashback_percent' => 0.00,
                'cashback_max_amount' => 0.00,
                'wallet_use_limit' => 0.00,
                'bonus_trip_threshold' => 20,
                'bonus_amount' => 50.00,
                'day_pass_fee' => 0.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'vehicle_type' => 'car',
                'per_trip_fee' => 11.00,
                'daily_fee' => 0.00,
                'customer_insurance' => 2.00,
                'driver_insurance' => 2.00,
                'cashback_percent' => 0.00,
                'cashback_max_amount' => 0.00,
                'wallet_use_limit' => 0.00,
                'bonus_trip_threshold' => 0,
                'bonus_amount' => 0.00,
                'day_pass_fee' => 55.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('platform_charges')->insert($platformCharges);
    }
}
