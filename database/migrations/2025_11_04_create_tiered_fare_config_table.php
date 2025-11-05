<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates table for GAUVA tiered km-based fare configuration
     */
    public function up(): void
    {
        Schema::create('tiered_fare_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('vehicle_type', ['bike', 'auto', 'car'])->index();
            $table->foreignUuid('zone_id')->nullable()->index();
            
            // Base fare (covers 0-2 km)
            $table->decimal('base_fare', 16, 2)->default(0)->comment('Covers 0-2 km');
            
            // Tier 1: 2-6 km
            $table->decimal('tier1_start_km', 8, 2)->default(2.00);
            $table->decimal('tier1_end_km', 8, 2)->default(6.00);
            $table->decimal('tier1_per_km', 16, 2)->default(0);
            
            // Tier 2: 6-8 km
            $table->decimal('tier2_start_km', 8, 2)->default(6.00);
            $table->decimal('tier2_end_km', 8, 2)->default(8.00);
            $table->decimal('tier2_per_km', 16, 2)->default(0);
            
            // Tier 3: Above 8 km
            $table->decimal('tier3_start_km', 8, 2)->default(8.00);
            $table->decimal('tier3_per_km', 16, 2)->default(0);
            
            // GST Configuration
            $table->decimal('eco_gst_percent', 8, 2)->default(5.00)->comment('ECO-GST 5%');
            $table->decimal('platform_gst_percent', 8, 2)->default(18.00)->comment('Platform GST 18%');
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['vehicle_type', 'zone_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiered_fare_config');
    }
};

