<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DriverDailyActivity extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'driver_id',
        'activity_date',
        'vehicle_type',
        'total_accepted_trips',
        'completed_trips',
        'customer_cancelled_after_start',
        'driver_cancelled',
        'counted_trips',
        'target_trips',
        'daily_fee',
        'per_trip_fee',
        'free_access_achieved',
        'fee_deducted',
        'fee_amount_deducted',
        'fee_deducted_at',
        'days_since_joining',
        'is_welcome_period',
        'wallet_balance_before',
        'wallet_balance_after',
        'deduction_notes',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'free_access_achieved' => 'boolean',
        'fee_deducted' => 'boolean',
        'is_welcome_period' => 'boolean',
        'fee_deducted_at' => 'datetime',
        'daily_fee' => 'decimal:2',
        'per_trip_fee' => 'decimal:2',
        'fee_amount_deducted' => 'decimal:2',
        'wallet_balance_before' => 'decimal:2',
        'wallet_balance_after' => 'decimal:2',
    ];

    /**
     * Get the driver
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Calculate counted trips (completed + customer cancelled after start)
     */
    public function calculateCountedTrips(): int
    {
        return $this->completed_trips + $this->customer_cancelled_after_start;
    }

    /**
     * Check if free access is achieved
     */
    public function checkFreeAccess(): bool
    {
        return $this->counted_trips >= $this->target_trips;
    }

    /**
     * Check if fee should be deducted
     */
    public function shouldDeductFee(): bool
    {
        // Don't deduct if:
        // 1. In welcome period
        // 2. 0 trips done
        // 3. Free access achieved
        // 4. Already deducted
        
        if ($this->is_welcome_period) {
            return false;
        }
        
        if ($this->counted_trips == 0) {
            return false;
        }
        
        if ($this->free_access_achieved) {
            return false;
        }
        
        if ($this->fee_deducted) {
            return false;
        }
        
        return true;
    }

    /**
     * Get status message
     */
    public function getStatusAttribute(): string
    {
        if ($this->is_welcome_period) {
            return 'welcome_period';
        }
        
        if ($this->free_access_achieved) {
            return 'free_access';
        }
        
        if ($this->fee_deducted) {
            return 'fee_deducted';
        }
        
        if ($this->counted_trips == 0) {
            return 'no_activity';
        }
        
        return 'in_progress';
    }

    /**
     * Scope for today's activity
     */
    public function scopeToday($query)
    {
        return $query->whereDate('activity_date', today());
    }

    /**
     * Scope for specific driver
     */
    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Scope for welcome period
     */
    public function scopeInWelcomePeriod($query)
    {
        return $query->where('is_welcome_period', true);
    }

    /**
     * Scope for pending fee deduction
     */
    public function scopePendingDeduction($query)
    {
        return $query->where('fee_deducted', false)
            ->where('is_welcome_period', false)
            ->where('free_access_achieved', false)
            ->where('counted_trips', '>', 0);
    }
}

