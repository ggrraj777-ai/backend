<?php

namespace App\Services;

use App\Models\User;
use App\Models\DriverDailyActivity;
use App\Models\DriverFeeConfiguration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DailyFeeDeductionService
{
    /**
     * Process daily fee deductions for all drivers
     */
    public function processAllDrivers(?Carbon $date = null): array
    {
        $date = $date ?? today();
        $results = [
            'total_drivers' => 0,
            'fees_deducted' => 0,
            'free_access' => 0,
            'welcome_period' => 0,
            'no_activity' => 0,
            'insufficient_balance' => 0,
            'total_amount_deducted' => 0,
            'errors' => [],
        ];

        // Get all activities for the date that need processing
        $activities = DriverDailyActivity::where('activity_date', $date)
            ->where('fee_deducted', false)
            ->with('driver')
            ->get();

        $results['total_drivers'] = $activities->count();

        foreach ($activities as $activity) {
            try {
                $result = $this->processDriverActivity($activity);
                
                if ($result['action'] == 'fee_deducted') {
                    $results['fees_deducted']++;
                    $results['total_amount_deducted'] += $result['amount'];
                } elseif ($result['action'] == 'free_access') {
                    $results['free_access']++;
                } elseif ($result['action'] == 'welcome_period') {
                    $results['welcome_period']++;
                } elseif ($result['action'] == 'no_activity') {
                    $results['no_activity']++;
                } elseif ($result['action'] == 'insufficient_balance') {
                    $results['insufficient_balance']++;
                }
                
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'driver_id' => $activity->driver_id,
                    'error' => $e->getMessage(),
                ];
                Log::error("Daily fee deduction error for driver {$activity->driver_id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Process single driver activity
     */
    public function processDriverActivity(DriverDailyActivity $activity): array
    {
        // Welcome period - no deduction
        if ($activity->is_welcome_period) {
            $activity->deduction_notes = "Welcome period - Day {$activity->days_since_joining} of {$activity->days_since_joining} free days";
            $activity->save();
            
            return [
                'action' => 'welcome_period',
                'message' => 'Welcome period - no deduction',
                'amount' => 0,
            ];
        }

        // No activity - no deduction
        if ($activity->counted_trips == 0) {
            $activity->deduction_notes = "No activity today - no deduction";
            $activity->save();
            
            return [
                'action' => 'no_activity',
                'message' => 'No trips - no deduction',
                'amount' => 0,
            ];
        }

        // Free access achieved - no deduction
        if ($activity->free_access_achieved) {
            $activity->deduction_notes = "Free access achieved with {$activity->counted_trips} trips";
            $activity->save();
            
            return [
                'action' => 'free_access',
                'message' => 'Free access achieved',
                'amount' => 0,
            ];
        }

        // Partial trips - deduct fee
        return $this->deductFee($activity);
    }

    /**
     * Deduct fee from driver wallet
     */
    private function deductFee(DriverDailyActivity $activity): array
    {
        $driver = $activity->driver;
        $feeAmount = $activity->daily_fee;

        DB::beginTransaction();
        try {
            // Check wallet balance
            $currentBalance = $driver->wallet_balance ?? 0;
            $activity->wallet_balance_before = $currentBalance;

            if ($currentBalance < $feeAmount) {
                DB::rollBack();
                
                $activity->deduction_notes = "Insufficient wallet balance. Required: ₹{$feeAmount}, Available: ₹{$currentBalance}";
                $activity->save();
                
                // Block trip acceptance
                $driver->can_accept_trips = false;
                $driver->save();
                
                return [
                    'action' => 'insufficient_balance',
                    'message' => 'Insufficient wallet balance',
                    'amount' => 0,
                    'required' => $feeAmount,
                    'available' => $currentBalance,
                ];
            }

            // Deduct fee from wallet
            $driver->wallet_balance = $currentBalance - $feeAmount;
            $driver->save();

            // Update activity
            $activity->fee_deducted = true;
            $activity->fee_amount_deducted = $feeAmount;
            $activity->fee_deducted_at = now();
            $activity->wallet_balance_after = $driver->wallet_balance;
            $activity->deduction_notes = "Daily fee deducted: ₹{$feeAmount} for {$activity->counted_trips}/{$activity->target_trips} trips";
            $activity->save();

            // Create transaction record
            $this->createFeeTransaction($driver, $activity, $feeAmount);

            DB::commit();

            return [
                'action' => 'fee_deducted',
                'message' => 'Daily fee deducted successfully',
                'amount' => $feeAmount,
                'balance_before' => $currentBalance,
                'balance_after' => $driver->wallet_balance,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create transaction record for fee deduction
     */
    private function createFeeTransaction(User $driver, DriverDailyActivity $activity, float $amount): void
    {
        // Create wallet transaction record
        DB::table('transactions')->insert([
            'id' => Str::uuid(),
            'user_id' => $driver->id,
            'attribute' => 'driver_daily_fee',
            'attribute_id' => $activity->id,
            'account' => 'wallet_balance',
            'debit' => $amount,
            'credit' => 0,
            'balance' => $driver->wallet_balance,
            'transaction_type' => 'driver_daily_fee',
            'trx_ref_id' => $activity->id,
            'reference' => "Daily access fee for {$activity->activity_date->format('Y-m-d')} - {$activity->counted_trips}/{$activity->target_trips} trips",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get pending deductions summary
     */
    public function getPendingDeductions(?Carbon $date = null): array
    {
        $date = $date ?? today();
        
        $activities = DriverDailyActivity::where('activity_date', $date)
            ->pendingDeduction()
            ->with('driver')
            ->get();

        $totalAmount = $activities->sum('daily_fee');
        $driverCount = $activities->count();

        return [
            'date' => $date->format('Y-m-d'),
            'drivers_count' => $driverCount,
            'total_amount' => $totalAmount,
            'activities' => $activities->map(function ($activity) {
                return [
                    'driver_id' => $activity->driver_id,
                    'driver_name' => $activity->driver->first_name . ' ' . $activity->driver->last_name,
                    'vehicle_type' => $activity->vehicle_type,
                    'trips' => $activity->counted_trips . '/' . $activity->target_trips,
                    'fee' => $activity->daily_fee,
                    'wallet_balance' => $activity->driver->wallet_balance ?? 0,
                ];
            }),
        ];
    }

    /**
     * Manual fee deduction for specific driver
     */
    public function manualDeduction(string $driverId, Carbon $date): array
    {
        $activity = DriverDailyActivity::forDriver($driverId)
            ->where('activity_date', $date)
            ->first();

        if (!$activity) {
            throw new \Exception("No activity found for driver on {$date->format('Y-m-d')}");
        }

        if ($activity->fee_deducted) {
            throw new \Exception("Fee already deducted for this date");
        }

        return $this->processDriverActivity($activity);
    }
}

