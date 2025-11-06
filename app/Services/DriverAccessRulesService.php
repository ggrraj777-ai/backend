<?php

namespace App\Services;

use App\Models\User;
use App\Models\DriverDailyActivity;
use App\Models\DriverFeeConfiguration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriverAccessRulesService
{
    /**
     * Initialize or update daily activity for a driver
     */
    public function initializeDailyActivity(string $driverId, string $vehicleType, ?Carbon $date = null): DriverDailyActivity
    {
        $date = $date ?? today();
        $driver = User::find($driverId);
        
        if (!$driver) {
            throw new \Exception("Driver not found");
        }

        // Get fee configuration for vehicle type
        $config = DriverFeeConfiguration::getForVehicle($vehicleType);
        
        if (!$config) {
            throw new \Exception("Fee configuration not found for vehicle type: {$vehicleType}");
        }

        // Calculate days since joining
        $joinedDate = $driver->driver_joined_date ? Carbon::parse($driver->driver_joined_date) : $driver->created_at;
        $daysSinceJoining = $joinedDate->diffInDays($date) + 1;
        
        // Check if in welcome period
        $isWelcomePeriod = $daysSinceJoining <= $config->welcome_period_days;

        // Create or update daily activity
        $activity = DriverDailyActivity::updateOrCreate(
            [
                'driver_id' => $driverId,
                'activity_date' => $date,
            ],
            [
                'vehicle_type' => $vehicleType,
                'target_trips' => $config->daily_target_trips,
                'daily_fee' => $config->daily_fee,
                'per_trip_fee' => $config->per_trip_fee,
                'days_since_joining' => $daysSinceJoining,
                'is_welcome_period' => $isWelcomePeriod,
            ]
        );

        return $activity;
    }

    /**
     * Record trip completion
     */
    public function recordTripCompleted(string $driverId, string $tripId, string $vehicleType): void
    {
        DB::transaction(function () use ($driverId, $vehicleType) {
            $activity = $this->initializeDailyActivity($driverId, $vehicleType);
            
            $activity->increment('total_accepted_trips');
            $activity->increment('completed_trips');
            $activity->counted_trips = $activity->calculateCountedTrips();
            $activity->free_access_achieved = $activity->checkFreeAccess();
            $activity->save();
        });
    }

    /**
     * Record customer cancellation after driver started
     */
    public function recordCustomerCancelledAfterStart(string $driverId, string $tripId, string $vehicleType): void
    {
        DB::transaction(function () use ($driverId, $vehicleType) {
            $activity = $this->initializeDailyActivity($driverId, $vehicleType);
            
            $activity->increment('total_accepted_trips');
            $activity->increment('customer_cancelled_after_start');
            $activity->counted_trips = $activity->calculateCountedTrips();
            $activity->free_access_achieved = $activity->checkFreeAccess();
            $activity->save();
        });
    }

    /**
     * Record driver cancellation
     */
    public function recordDriverCancellation(string $driverId, string $tripId, string $vehicleType): void
    {
        DB::transaction(function () use ($driverId, $vehicleType) {
            $activity = $this->initializeDailyActivity($driverId, $vehicleType);
            
            $activity->increment('total_accepted_trips');
            $activity->increment('driver_cancelled');
            
            // Check if exceeded max cancellations
            $config = DriverFeeConfiguration::getForVehicle($vehicleType);
            if ($activity->driver_cancelled > $config->max_allowed_cancellations) {
                $activity->free_access_achieved = false;
                $activity->deduction_notes = "Exceeded maximum allowed cancellations ({$config->max_allowed_cancellations})";
            }
            
            $activity->save();
        });
    }

    /**
     * Check if driver can accept trips
     */
    public function canAcceptTrips(string $driverId): array
    {
        $driver = User::find($driverId);
        
        if (!$driver) {
            return [
                'can_accept' => false,
                'reason' => 'Driver not found',
                'reason_te' => 'డ్రైవర్ దొరకలేదు',
            ];
        }

        // Check wallet balance
        $requiredBalance = $driver->minimum_required_balance ?? 50.00;
        $currentBalance = $driver->wallet_balance ?? 0;

        if ($currentBalance < $requiredBalance) {
            return [
                'can_accept' => false,
                'reason' => "Insufficient wallet balance. Minimum required: ₹{$requiredBalance}",
                'reason_te' => "వాలెట్ బ్యాలెన్స్ తక్కువగా ఉంది. కనీసం ₹{$requiredBalance} అవసరం",
                'current_balance' => $currentBalance,
                'required_balance' => $requiredBalance,
                'top_up_needed' => $requiredBalance - $currentBalance,
            ];
        }

        return [
            'can_accept' => true,
            'reason' => 'Driver can accept trips',
            'reason_te' => 'డ్రైవర్ ట్రిప్స్ అంగీకరించవచ్చు',
            'current_balance' => $currentBalance,
        ];
    }

    /**
     * Get driver's today activity status
     */
    public function getTodayStatus(string $driverId, string $vehicleType): array
    {
        $activity = DriverDailyActivity::forDriver($driverId)
            ->today()
            ->first();

        if (!$activity) {
            $activity = $this->initializeDailyActivity($driverId, $vehicleType);
        }

        $config = DriverFeeConfiguration::getForVehicle($vehicleType);
        $tripsRemaining = max(0, $activity->target_trips - $activity->counted_trips);

        return [
            'date' => $activity->activity_date->format('Y-m-d'),
            'vehicle_type' => $activity->vehicle_type,
            'days_since_joining' => $activity->days_since_joining,
            'is_welcome_period' => $activity->is_welcome_period,
            'total_accepted_trips' => $activity->total_accepted_trips,
            'completed_trips' => $activity->completed_trips,
            'counted_trips' => $activity->counted_trips,
            'target_trips' => $activity->target_trips,
            'trips_remaining' => $tripsRemaining,
            'free_access_achieved' => $activity->free_access_achieved,
            'daily_fee' => $activity->daily_fee,
            'per_trip_fee' => $activity->per_trip_fee,
            'fee_deducted' => $activity->fee_deducted,
            'status' => $activity->status,
            'message_en' => $this->getStatusMessage($activity, 'en'),
            'message_te' => $this->getStatusMessage($activity, 'te'),
        ];
    }

    /**
     * Get status message in specific language
     */
    private function getStatusMessage(DriverDailyActivity $activity, string $lang = 'en'): string
    {
        if ($activity->is_welcome_period) {
            return $lang === 'te' 
                ? "స్వాగత కాలం - పూర్తిగా ఉచితం (రోజు {$activity->days_since_joining}/3)"
                : "Welcome Period - Fully FREE (Day {$activity->days_since_joining}/3)";
        }

        if ($activity->free_access_achieved) {
            return $lang === 'te'
                ? "అభినందనలు! ఈ రోజు ఫ్రీ యాక్సెస్ పొందారు"
                : "Congratulations! Free Access Achieved Today";
        }

        if ($activity->counted_trips == 0) {
            return $lang === 'te'
                ? "ఈ రోజు ట్రిప్స్ లేవు - డెడక్షన్ లేదు"
                : "No trips today - No deduction";
        }

        $remaining = $activity->target_trips - $activity->counted_trips;
        if ($lang === 'te') {
            return "ఫ్రీ యాక్సెస్ కోసం మరో {$remaining} ట్రిప్స్ అవసరం";
        }
        
        return "{$remaining} more trips needed for free access";
    }

    /**
     * Get driver statistics for a date range
     */
    public function getDriverStatistics(string $driverId, Carbon $startDate, Carbon $endDate): array
    {
        $activities = DriverDailyActivity::forDriver($driverId)
            ->whereBetween('activity_date', [$startDate, $endDate])
            ->orderBy('activity_date', 'desc')
            ->get();

        $totalTrips = $activities->sum('counted_trips');
        $freeDays = $activities->where('free_access_achieved', true)->count();
        $paidDays = $activities->where('fee_deducted', true)->count();
        $totalFeesDeducted = $activities->sum('fee_amount_deducted');

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $activities->count(),
            ],
            'trips' => [
                'total_completed' => $totalTrips,
                'average_per_day' => $activities->count() > 0 ? round($totalTrips / $activities->count(), 2) : 0,
            ],
            'access' => [
                'free_days' => $freeDays,
                'paid_days' => $paidDays,
                'welcome_days' => $activities->where('is_welcome_period', true)->count(),
            ],
            'fees' => [
                'total_deducted' => $totalFeesDeducted,
                'average_per_day' => $paidDays > 0 ? round($totalFeesDeducted / $paidDays, 2) : 0,
            ],
            'activities' => $activities->map(function ($activity) {
                return [
                    'date' => $activity->activity_date->format('Y-m-d'),
                    'trips' => $activity->counted_trips,
                    'target' => $activity->target_trips,
                    'free_access' => $activity->free_access_achieved,
                    'fee' => $activity->fee_amount_deducted ?? 0,
                    'status' => $activity->status,
                ];
            }),
        ];
    }
}

