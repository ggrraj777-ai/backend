<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DriverFeeConfiguration extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'vehicle_type',
        'daily_target_trips',
        'daily_fee',
        'per_trip_fee',
        'minimum_wallet_balance',
        'welcome_period_days',
        'max_allowed_cancellations',
        'is_active',
        'description_en',
        'description_te',
    ];

    protected $casts = [
        'daily_fee' => 'decimal:2',
        'per_trip_fee' => 'decimal:2',
        'minimum_wallet_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get configuration for specific vehicle type
     */
    public static function getForVehicle(string $vehicleType): ?self
    {
        return self::where('vehicle_type', strtolower($vehicleType))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active configurations
     */
    public static function getAllActive()
    {
        return self::where('is_active', true)->get();
    }

    /**
     * Get description in specific language
     */
    public function getDescription(string $lang = 'en'): ?string
    {
        return $lang === 'te' ? $this->description_te : $this->description_en;
    }

    /**
     * Check if trips meet target
     */
    public function isTargetMet(int $trips): bool
    {
        return $trips >= $this->daily_target_trips;
    }

    /**
     * Calculate fee for partial trips
     */
    public function calculateFee(int $trips): float
    {
        if ($trips == 0) {
            return 0;
        }
        
        if ($this->isTargetMet($trips)) {
            return 0;
        }
        
        return (float) $this->daily_fee;
    }

    /**
     * Get configuration summary
     */
    public function getSummary(): array
    {
        return [
            'vehicle_type' => $this->vehicle_type,
            'target' => $this->daily_target_trips,
            'daily_fee' => '₹' . number_format($this->daily_fee, 2),
            'per_trip_fee' => '₹' . number_format($this->per_trip_fee, 2),
            'min_balance' => '₹' . number_format($this->minimum_wallet_balance, 2),
            'welcome_days' => $this->welcome_period_days,
        ];
    }
}

