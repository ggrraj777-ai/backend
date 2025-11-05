<?php

namespace Modules\FareManagement\Service;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformFareService
{
    /**
     * Calculate complete fare breakdown for a trip
     *
     * @param string $vehicleType (bike, auto, car)
     * @param float $baseFare
     * @param string $driverId
     * @param string|null $customerId
     * @param bool $hasDayPass (for car only)
     * @return array
     */
    public function calculateTripFare(
        string $vehicleType,
        float $baseFare,
        string $driverId,
        ?string $customerId = null,
        bool $hasDayPass = false
    ): array {
        // Get platform charges for vehicle type
        $platformCharge = $this->getPlatformCharges($vehicleType);
        
        if (!$platformCharge) {
            throw new \Exception("Platform charges not configured for vehicle type: {$vehicleType}");
        }

        // Initialize fare breakdown
        $fareBreakdown = [
            'base_fare' => $baseFare,
            'platform_fee' => 0,
            'daily_fee' => 0,
            'customer_insurance' => $platformCharge->customer_insurance,
            'driver_insurance' => $platformCharge->driver_insurance,
            'total_insurance' => $platformCharge->customer_insurance + $platformCharge->driver_insurance,
            'cashback_amount' => 0,
            'wallet_deduction' => 0,
            'final_customer_payment' => 0,
            'driver_earnings' => 0,
            'platform_earnings' => 0,
        ];

        // Calculate platform fee based on vehicle type and day pass
        if ($vehicleType === 'car' && $hasDayPass) {
            $fareBreakdown['platform_fee'] = 0; // No per trip fee with day pass
            $fareBreakdown['has_day_pass'] = true;
        } else {
            $fareBreakdown['platform_fee'] = $platformCharge->per_trip_fee;
        }

        // Check and deduct daily fee (only on first trip of the day)
        $dailyFeeDeduction = $this->checkAndDeductDailyFee($driverId, $vehicleType, $platformCharge->daily_fee);
        $fareBreakdown['daily_fee'] = $dailyFeeDeduction['amount'];
        $fareBreakdown['is_first_trip_today'] = $dailyFeeDeduction['is_first_trip'];

        // Calculate cashback for bike only
        if ($vehicleType === 'bike' && $customerId) {
            $cashback = $this->calculateCashback($baseFare, $platformCharge);
            $fareBreakdown['cashback_amount'] = $cashback;
        }

        // Calculate wallet usage for bike only
        if ($vehicleType === 'bike' && $customerId) {
            $walletBalance = $this->getCustomerWalletBalance($customerId);
            $fareBreakdown['wallet_deduction'] = min($walletBalance, $platformCharge->wallet_use_limit, $baseFare);
        }

        // Calculate final amounts
        $totalCharges = $baseFare + $fareBreakdown['total_insurance'] + $fareBreakdown['platform_fee'];
        $fareBreakdown['final_customer_payment'] = $totalCharges - $fareBreakdown['wallet_deduction'];
        
        // Driver earnings = base fare - platform fee - daily fee
        $fareBreakdown['driver_earnings'] = $baseFare - $fareBreakdown['platform_fee'] - $fareBreakdown['daily_fee'];
        
        // Platform earnings = platform fee + daily fee + insurance
        $fareBreakdown['platform_earnings'] = $fareBreakdown['platform_fee'] + $fareBreakdown['daily_fee'] + $fareBreakdown['total_insurance'];

        return $fareBreakdown;
    }

    /**
     * Get platform charges for vehicle type
     */
    private function getPlatformCharges(string $vehicleType)
    {
        return DB::table('platform_charges')
            ->where('vehicle_type', $vehicleType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if daily fee needs to be deducted and deduct it
     */
    private function checkAndDeductDailyFee(string $driverId, string $vehicleType, float $dailyFee): array
    {
        $today = Carbon::today()->toDateString();
        
        // Check if daily fee already deducted today
        $existingFee = DB::table('driver_daily_fees')
            ->where('driver_id', $driverId)
            ->where('vehicle_type', $vehicleType)
            ->where('fee_date', $today)
            ->first();

        if ($existingFee && $existingFee->is_deducted) {
            return [
                'amount' => 0,
                'is_first_trip' => false,
            ];
        }

        // This is the first trip, deduct daily fee
        if (!$existingFee) {
            DB::table('driver_daily_fees')->insert([
                'id' => Str::uuid(),
                'driver_id' => $driverId,
                'vehicle_type' => $vehicleType,
                'fee_date' => $today,
                'daily_fee_amount' => $dailyFee,
                'is_deducted' => true,
                'deducted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('driver_daily_fees')
                ->where('id', $existingFee->id)
                ->update([
                    'is_deducted' => true,
                    'deducted_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return [
            'amount' => $dailyFee,
            'is_first_trip' => true,
        ];
    }

    /**
     * Calculate cashback for bike trips
     */
    private function calculateCashback(float $baseFare, $platformCharge): float
    {
        $cashback = ($baseFare * $platformCharge->cashback_percent) / 100;
        return min($cashback, $platformCharge->cashback_max_amount);
    }

    /**
     * Get customer wallet balance
     */
    private function getCustomerWalletBalance(string $customerId): float
    {
        $account = DB::table('user_accounts')
            ->where('user_id', $customerId)
            ->first();

        return $account ? (float) $account->wallet_balance : 0;
    }

    /**
     * Credit cashback to customer wallet (for bike only)
     */
    public function creditCashback(string $customerId, string $tripId, float $cashbackAmount, string $vehicleType = 'bike'): bool
    {
        if ($vehicleType !== 'bike' || $cashbackAmount <= 0) {
            return false;
        }

        DB::beginTransaction();
        try {
            // Record cashback
            DB::table('customer_cashbacks')->insert([
                'id' => Str::uuid(),
                'customer_id' => $customerId,
                'trip_id' => $tripId,
                'vehicle_type' => $vehicleType,
                'trip_fare' => 0, // Will be updated
                'cashback_amount' => $cashbackAmount,
                'is_credited' => true,
                'credited_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update wallet balance
            DB::table('user_accounts')
                ->where('user_id', $customerId)
                ->increment('wallet_balance', $cashbackAmount);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Check and credit driver bonus for auto (20 trips = ₹50)
     */
    public function checkAndCreditDriverBonus(string $driverId, string $vehicleType = 'auto'): array
    {
        if ($vehicleType !== 'auto') {
            return ['eligible' => false, 'message' => 'Bonus only for auto drivers'];
        }

        $today = Carbon::today()->toDateString();
        
        // Get or create bonus record
        $bonusRecord = DB::table('driver_trip_bonuses')
            ->where('driver_id', $driverId)
            ->where('bonus_date', $today)
            ->first();

        if (!$bonusRecord) {
            DB::table('driver_trip_bonuses')->insert([
                'id' => Str::uuid(),
                'driver_id' => $driverId,
                'vehicle_type' => $vehicleType,
                'bonus_date' => $today,
                'trip_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['eligible' => false, 'trip_count' => 1, 'remaining' => 19];
        }

        // Increment trip count
        $newTripCount = $bonusRecord->trip_count + 1;
        DB::table('driver_trip_bonuses')
            ->where('id', $bonusRecord->id)
            ->update([
                'trip_count' => $newTripCount,
                'updated_at' => now(),
            ]);

        // Check if eligible for bonus
        if ($newTripCount >= 20 && !$bonusRecord->is_credited) {
            DB::beginTransaction();
            try {
                // Credit bonus
                DB::table('user_accounts')
                    ->where('user_id', $driverId)
                    ->increment('wallet_balance', 50);

                // Mark as credited
                DB::table('driver_trip_bonuses')
                    ->where('id', $bonusRecord->id)
                    ->update([
                        'is_credited' => true,
                        'credited_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::commit();
                return ['eligible' => true, 'bonus_amount' => 50, 'trip_count' => $newTripCount];
            } catch (\Exception $e) {
                DB::rollBack();
                return ['eligible' => false, 'error' => $e->getMessage()];
            }
        }

        return ['eligible' => false, 'trip_count' => $newTripCount, 'remaining' => 20 - $newTripCount];
    }

    /**
     * Purchase day pass for car drivers
     */
    public function purchaseDayPass(string $driverId, string $vehicleType = 'car'): array
    {
        if ($vehicleType !== 'car') {
            return ['success' => false, 'message' => 'Day pass only available for car drivers'];
        }

        $today = Carbon::today()->toDateString();
        $platformCharge = $this->getPlatformCharges($vehicleType);

        // Check if already purchased
        $existingPass = DB::table('driver_day_passes')
            ->where('driver_id', $driverId)
            ->where('pass_date', $today)
            ->first();

        if ($existingPass) {
            return ['success' => false, 'message' => 'Day pass already purchased for today'];
        }

        // Check wallet balance
        $walletBalance = $this->getCustomerWalletBalance($driverId);
        if ($walletBalance < $platformCharge->day_pass_fee) {
            return ['success' => false, 'message' => 'Insufficient wallet balance'];
        }

        DB::beginTransaction();
        try {
            // Deduct from wallet
            DB::table('user_accounts')
                ->where('user_id', $driverId)
                ->decrement('wallet_balance', $platformCharge->day_pass_fee);

            // Create pass record
            DB::table('driver_day_passes')->insert([
                'id' => Str::uuid(),
                'driver_id' => $driverId,
                'vehicle_type' => $vehicleType,
                'pass_date' => $today,
                'pass_amount' => $platformCharge->day_pass_fee,
                'purchased_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return ['success' => true, 'amount' => $platformCharge->day_pass_fee];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if driver has active day pass
     */
    public function hasActiveDayPass(string $driverId, string $vehicleType = 'car'): bool
    {
        $today = Carbon::today()->toDateString();
        
        return DB::table('driver_day_passes')
            ->where('driver_id', $driverId)
            ->where('vehicle_type', $vehicleType)
            ->where('pass_date', $today)
            ->where('is_active', true)
            ->exists();
    }
}
