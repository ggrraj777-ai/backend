<?php

namespace Modules\TripManagement\Lib;

use Illuminate\Support\Facades\DB;
use Modules\FareManagement\Service\PlatformFareService;
use Modules\TripManagement\Entities\TripRequest;

/**
 * Platform Fee Integration Trait
 * Integrates GAUVA platform charges (insurance, cashback, bonuses, etc.) into trip fare calculation
 */
trait PlatformFeeIntegrationTrait
{
    /**
     * Calculate final fare with platform charges
     * 
     * @param TripRequest $trip
     * @param float $baseFare
     * @return array Complete fare breakdown
     */
    public function calculateFareWithPlatformCharges(TripRequest $trip, float $baseFare): array
    {
        $vehicleType = $this->getVehicleTypeFromCategory($trip->vehicleCategory->type);
        $platformService = new PlatformFareService();

        // Check if driver has day pass (for car only)
        $hasDayPass = false;
        if ($vehicleType === 'car') {
            $hasDayPass = $platformService->hasActiveDayPass($trip->driver_id, 'car');
        }

        // Calculate complete fare breakdown
        $fareBreakdown = $platformService->calculateTripFare(
            vehicleType: $vehicleType,
            baseFare: $baseFare,
            driverId: $trip->driver_id,
            customerId: $trip->customer_id,
            hasDayPass: $hasDayPass
        );

        // Apply platform charges to trip
        $this->applyPlatformCharges($trip, $fareBreakdown, $platformService, $vehicleType);

        return $fareBreakdown;
    }

    /**
     * Apply platform charges and update wallets
     */
    protected function applyPlatformCharges(TripRequest $trip, array $fareBreakdown, PlatformFareService $platformService, string $vehicleType): void
    {
        DB::beginTransaction();
        try {
            // 1. Deduct platform fees from driver wallet
            $totalDriverDeduction = $fareBreakdown['platform_fee'] + 
                                   $fareBreakdown['daily_fee'] + 
                                   $fareBreakdown['driver_insurance'];

            if ($totalDriverDeduction > 0) {
                DB::table('user_accounts')
                    ->where('user_id', $trip->driver_id)
                    ->decrement('wallet_balance', $totalDriverDeduction);
            }

            // 2. Credit cashback to customer (bike only)
            if ($fareBreakdown['cashback_amount'] > 0 && $vehicleType === 'bike') {
                $platformService->creditCashback(
                    customerId: $trip->customer_id,
                    tripId: $trip->id,
                    cashbackAmount: $fareBreakdown['cashback_amount'],
                    vehicleType: $vehicleType
                );
            }

            // 3. Check and credit driver bonus (auto only - 20 trips = ₹50)
            if ($vehicleType === 'auto') {
                $bonusResult = $platformService->checkAndCreditDriverBonus(
                    driverId: $trip->driver_id,
                    vehicleType: 'auto'
                );

                // Store bonus info for notification
                $fareBreakdown['bonus_credited'] = $bonusResult['eligible'] ?? false;
                $fareBreakdown['bonus_amount'] = $bonusResult['bonus_amount'] ?? 0;
            }

            // 4. Update trip fee record with platform charges
            DB::table('trip_request_fees')
                ->where('trip_request_id', $trip->id)
                ->update([
                    'platform_fee' => $fareBreakdown['platform_fee'],
                    'daily_fee' => $fareBreakdown['daily_fee'],
                    'customer_insurance' => $fareBreakdown['customer_insurance'],
                    'driver_insurance' => $fareBreakdown['driver_insurance'],
                    'total_insurance' => $fareBreakdown['total_insurance'],
                    'cashback_amount' => $fareBreakdown['cashback_amount'],
                    'wallet_deduction' => $fareBreakdown['wallet_deduction'],
                    'updated_at' => now(),
                ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Platform charges application failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get vehicle type from category
     */
    protected function getVehicleTypeFromCategory(string $categoryType): string
    {
        return match($categoryType) {
            'motor_bike', 'bike' => 'bike',
            'auto', 'auto_rickshaw' => 'auto',
            'car', 'sedan', 'suv' => 'car',
            default => 'bike',
        };
    }

    /**
     * Get fare breakdown for display
     */
    public function getFareBreakdownForDisplay(TripRequest $trip): array
    {
        $fee = $trip->fee;
        
        return [
            'base_fare' => $trip->actual_fare ?? $trip->estimated_fare,
            'platform_fee' => $fee->platform_fee ?? 0,
            'daily_fee' => $fee->daily_fee ?? 0,
            'customer_insurance' => $fee->customer_insurance ?? 0,
            'driver_insurance' => $fee->driver_insurance ?? 0,
            'total_insurance' => $fee->total_insurance ?? 0,
            'vat_tax' => $fee->vat_tax ?? 0,
            'waiting_fee' => $fee->waiting_fee ?? 0,
            'cancellation_fee' => $fee->cancellation_fee ?? 0,
            'discount_amount' => $trip->discount_amount ?? 0,
            'coupon_amount' => $trip->coupon_amount ?? 0,
            'cashback_earned' => $fee->cashback_amount ?? 0,
            'wallet_used' => $fee->wallet_deduction ?? 0,
            'tips' => $trip->tips ?? 0,
            'final_amount' => $trip->paid_fare ?? 0,
        ];
    }
}

