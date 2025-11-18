<?php

namespace Modules\FareManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TieredFareSeeder extends Seeder
{
    /**
     * Seed GAUVA tiered fare configuration
     * Based on distance-based pricing model
     */
    public function run()
    {
        $tieredFares = [
            // BIKE MODEL
            [
                'id' => Str::uuid(),
                'vehicle_type' => 'bike',
                'zone_id' => null, // Global default
                'base_fare' => 25.00, // Covers 0-2 km
                'tier1_start_km' => 2.00,
                'tier1_end_km' => 6.00,
                'tier1_per_km' => 8.00, // 2-6 km: ₹8/km
                'tier2_start_km' => 6.00,
                'tier2_end_km' => 8.00,
                'tier2_per_km' => 9.00, // 6-8 km: ₹9/km
                'tier3_start_km' => 8.00,
                'tier3_per_km' => 10.00, // Above 8 km: ₹10/km
                'eco_gst_percent' => 5.00,
                'platform_gst_percent' => 18.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // AUTO MODEL
            [
                'id' => Str::uuid(),
                'vehicle_type' => 'auto',
                'zone_id' => null, // Global default
                'base_fare' => 45.00, // Covers 0-2 km
                'tier1_start_km' => 2.00,
                'tier1_end_km' => 6.00,
                'tier1_per_km' => 15.00, // 2-6 km: ₹15/km
                'tier2_start_km' => 6.00,
                'tier2_end_km' => 8.00,
                'tier2_per_km' => 16.00, // 6-8 km: ₹16/km
                'tier3_start_km' => 8.00,
                'tier3_per_km' => 18.00, // Above 8 km: ₹18/km
                'eco_gst_percent' => 5.00,
                'platform_gst_percent' => 18.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // CAR MODEL
            [
                'id' => Str::uuid(),
                'vehicle_type' => 'car',
                'zone_id' => null, // Global default
                'base_fare' => 75.00, // Covers 0-2 km
                'tier1_start_km' => 2.00,
                'tier1_end_km' => 6.00,
                'tier1_per_km' => 18.00, // 2-6 km: ₹18/km
                'tier2_start_km' => 6.00,
                'tier2_end_km' => 8.00,
                'tier2_per_km' => 20.00, // 6-8 km: ₹20/km
                'tier3_start_km' => 8.00,
                'tier3_per_km' => 22.00, // Above 8 km: ₹22/km
                'eco_gst_percent' => 5.00,
                'platform_gst_percent' => 18.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('tiered_fare_config')->insert($tieredFares);
    }
}

