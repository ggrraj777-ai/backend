<?php

namespace Modules\FareManagement\Service;

use Illuminate\Support\Facades\DB;

/**
 * GAUVA Tiered Fare Calculator
 * Implements distance-based tiered pricing for Bike/Auto/Car
 */
class TieredFareCalculator
{
    /**
     * Calculate fare using tiered km-based pricing
     * 
     * @param string $vehicleType (bike, auto, car)
     * @param float $distanceKm Total distance in kilometers
     * @param string|null $zoneId Optional zone for zone-specific rates
     * @return array Complete fare breakdown
     */
    public function calculateTieredFare(
        string $vehicleType,
        float $distanceKm,
        ?string $zoneId = null
    ): array {
        // Get tiered fare configuration
        $config = $this->getTieredConfig($vehicleType, $zoneId);
        
        if (!$config) {
            throw new \Exception("Tiered fare configuration not found for vehicle type: {$vehicleType}");
        }

        // Initialize breakdown
        $breakdown = [
            'vehicle_type' => $vehicleType,
            'total_distance_km' => round($distanceKm, 2),
            'base_fare' => $config->base_fare,
            'tier1_fare' => 0,
            'tier2_fare' => 0,
            'tier3_fare' => 0,
            'distance_fare' => 0,
            'subtotal_before_charges' => 0,
            'tier_breakdown' => [],
        ];

        // Calculate tiered fare
        if ($distanceKm <= 2) {
            // Within base fare coverage (0-2 km)
            $breakdown['subtotal_before_charges'] = $config->base_fare;
            $breakdown['tier_breakdown'][] = [
                'range' => '0-2 km',
                'rate' => 'Base fare',
                'distance' => round($distanceKm, 2),
                'amount' => $config->base_fare,
            ];
        } else {
            // Start with base fare
            $fareAmount = $config->base_fare;
            $remainingDistance = $distanceKm - 2;
            
            $breakdown['tier_breakdown'][] = [
                'range' => '0-2 km',
                'rate' => 'Base fare',
                'distance' => 2.00,
                'amount' => $config->base_fare,
            ];

            // Tier 1: 2-6 km
            if ($remainingDistance > 0) {
                $tier1Distance = min($remainingDistance, $config->tier1_end_km - $config->tier1_start_km);
                $tier1Fare = $tier1Distance * $config->tier1_per_km;
                $breakdown['tier1_fare'] = $tier1Fare;
                $fareAmount += $tier1Fare;
                $remainingDistance -= $tier1Distance;
                
                $breakdown['tier_breakdown'][] = [
                    'range' => "{$config->tier1_start_km}-{$config->tier1_end_km} km",
                    'rate' => "₹{$config->tier1_per_km}/km",
                    'distance' => round($tier1Distance, 2),
                    'amount' => round($tier1Fare, 2),
                ];
            }

            // Tier 2: 6-8 km
            if ($remainingDistance > 0) {
                $tier2Distance = min($remainingDistance, $config->tier2_end_km - $config->tier2_start_km);
                $tier2Fare = $tier2Distance * $config->tier2_per_km;
                $breakdown['tier2_fare'] = $tier2Fare;
                $fareAmount += $tier2Fare;
                $remainingDistance -= $tier2Distance;
                
                $breakdown['tier_breakdown'][] = [
                    'range' => "{$config->tier2_start_km}-{$config->tier2_end_km} km",
                    'rate' => "₹{$config->tier2_per_km}/km",
                    'distance' => round($tier2Distance, 2),
                    'amount' => round($tier2Fare, 2),
                ];
            }

            // Tier 3: Above 8 km
            if ($remainingDistance > 0) {
                $tier3Fare = $remainingDistance * $config->tier3_per_km;
                $breakdown['tier3_fare'] = $tier3Fare;
                $fareAmount += $tier3Fare;
                
                $breakdown['tier_breakdown'][] = [
                    'range' => "Above {$config->tier3_start_km} km",
                    'rate' => "₹{$config->tier3_per_km}/km",
                    'distance' => round($remainingDistance, 2),
                    'amount' => round($tier3Fare, 2),
                ];
            }

            $breakdown['distance_fare'] = $fareAmount - $config->base_fare;
            $breakdown['subtotal_before_charges'] = $fareAmount;
        }

        return $breakdown;
    }

    /**
     * Calculate complete fare with platform charges and GST
     * 
     * @param string $vehicleType
     * @param float $distanceKm
     * @param string $driverId
     * @param string|null $customerId
     * @param string|null $zoneId
     * @param bool $hasDayPass
     * @return array Complete breakdown
     */
    public function calculateCompleteFare(
        string $vehicleType,
        float $distanceKm,
        string $driverId,
        ?string $customerId = null,
        ?string $zoneId = null,
        bool $hasDayPass = false
    ): array {
        // Step 1: Calculate tiered fare
        $tieredBreakdown = $this->calculateTieredFare($vehicleType, $distanceKm, $zoneId);
        $subtotal = $tieredBreakdown['subtotal_before_charges'];

        // Step 2: Get platform charges
        $platformCharge = DB::table('platform_charges')
            ->where('vehicle_type', $vehicleType)
            ->where('is_active', true)
            ->first();

        if (!$platformCharge) {
            throw new \Exception("Platform charges not configured for: {$vehicleType}");
        }

        // Step 3: Calculate platform fees
        $platformFee = $this->calculatePlatformFee($vehicleType, $hasDayPass, $platformCharge);
        $dailyFee = $this->calculateDailyFee($driverId, $vehicleType, $platformCharge);
        
        // Step 4: Add insurance
        $customerInsurance = $platformCharge->customer_insurance;
        $driverInsurance = $platformCharge->driver_insurance;
        $totalInsurance = $customerInsurance + $driverInsurance;

        // Step 5: Calculate subtotal with all charges (before GST)
        $subtotalWithCharges = $subtotal + $platformFee + $totalInsurance;

        // Step 6: Calculate ECO-GST (5% - inclusive in customer display)
        $ecoGst = ($subtotalWithCharges * $platformCharge->eco_gst_percent) / 100;
        $totalWithEcoGst = $subtotalWithCharges + $ecoGst;

        // Step 7: Calculate cashback (bike only)
        $cashbackAmount = 0;
        if ($vehicleType === 'bike' && $customerId) {
            $cashbackAmount = $this->calculateCashback($subtotal, $platformCharge);
        }

        // Step 8: Calculate wallet deduction (bike only)
        $walletDeduction = 0;
        if ($vehicleType === 'bike' && $customerId) {
            $walletBalance = $this->getWalletBalance($customerId);
            $walletDeduction = min($walletBalance, $platformCharge->wallet_use_limit, $subtotal);
        }

        // Step 9: Calculate platform GST (18% on platform income)
        $platformIncome = $platformFee + $dailyFee;
        $platformGst = ($platformIncome * $platformCharge->platform_gst_percent) / 100;

        // Step 10: Final amounts
        $finalCustomerPayment = $totalWithEcoGst - $walletDeduction;
        $driverEarning = $subtotal - $platformFee - $dailyFee - $driverInsurance;
        $platformEarning = $platformFee + $dailyFee + $totalInsurance + $platformGst;

        // Complete breakdown
        return array_merge($tieredBreakdown, [
            // Platform charges
            'platform_fee' => $platformFee,
            'daily_fee' => $dailyFee,
            'customer_insurance' => $customerInsurance,
            'driver_insurance' => $driverInsurance,
            'total_insurance' => $totalInsurance,
            
            // GST
            'eco_gst' => round($ecoGst, 2),
            'eco_gst_percent' => $platformCharge->eco_gst_percent,
            'platform_gst' => round($platformGst, 2),
            'platform_gst_percent' => $platformCharge->platform_gst_percent,
            
            // Wallet & Cashback
            'cashback_amount' => round($cashbackAmount, 2),
            'wallet_deduction' => round($walletDeduction, 2),
            
            // Totals
            'subtotal_with_charges' => round($subtotalWithCharges, 2),
            'total_with_eco_gst' => round($totalWithEcoGst, 2),
            'final_customer_payment' => round($finalCustomerPayment, 2),
            'driver_earning' => round($driverEarning, 2),
            'platform_earning' => round($platformEarning, 2),
            
            // Additional info
            'has_day_pass' => $hasDayPass,
            'config' => [
                'eco_gst_percent' => $platformCharge->eco_gst_percent,
                'platform_gst_percent' => $platformCharge->platform_gst_percent,
            ],
        ]);
    }

    /**
     * Get tiered fare configuration
     */
    private function getTieredConfig(string $vehicleType, ?string $zoneId = null)
    {
        $query = DB::table('tiered_fare_config')
            ->where('vehicle_type', $vehicleType)
            ->where('is_active', true);

        // Try zone-specific first, then fall back to global
        if ($zoneId) {
            $config = $query->where('zone_id', $zoneId)->first();
            if ($config) {
                return $config;
            }
        }

        return $query->whereNull('zone_id')->first();
    }

    /**
     * Calculate platform fee based on vehicle type and day pass
     */
    private function calculatePlatformFee(string $vehicleType, bool $hasDayPass, $platformCharge): float
    {
        if ($vehicleType === 'car' && $hasDayPass) {
            return 0; // Day pass means no per-trip fee
        }

        return $platformCharge->per_trip_fee;
    }

    /**
     * Calculate daily fee (only for first trip of the day)
     */
    private function calculateDailyFee(string $driverId, string $vehicleType, $platformCharge): float
    {
        $today = now()->toDateString();
        
        $existingFee = DB::table('driver_daily_fees')
            ->where('driver_id', $driverId)
            ->where('vehicle_type', $vehicleType)
            ->where('fee_date', $today)
            ->where('is_deducted', true)
            ->exists();

        return $existingFee ? 0 : $platformCharge->daily_fee;
    }

    /**
     * Calculate cashback (bike only)
     */
    private function calculateCashback(float $baseFare, $platformCharge): float
    {
        $cashback = ($baseFare * $platformCharge->cashback_percent) / 100;
        return min($cashback, $platformCharge->cashback_max_amount);
    }

    /**
     * Get wallet balance
     */
    private function getWalletBalance(string $userId): float
    {
        $account = DB::table('user_accounts')
            ->where('user_id', $userId)
            ->first();

        return $account ? (float) $account->wallet_balance : 0;
    }

    /**
     * Format fare for customer display (inclusive of ECO-GST)
     */
    public function formatForCustomerDisplay(array $breakdown): array
    {
        return [
            'total_fare_inclusive' => $breakdown['final_customer_payment'],
            'breakdown' => [
                'base_fare' => $breakdown['base_fare'],
                'distance_charges' => $breakdown['distance_fare'],
                'platform_fee' => $breakdown['platform_fee'],
                'insurance' => $breakdown['total_insurance'],
                'gst_inclusive' => $breakdown['eco_gst'],
                'subtotal' => $breakdown['total_with_eco_gst'],
                'wallet_used' => $breakdown['wallet_deduction'],
                'cashback_earned' => $breakdown['cashback_amount'],
                'you_pay' => $breakdown['final_customer_payment'],
            ],
            'tier_details' => $breakdown['tier_breakdown'],
            'note' => 'Fare inclusive of taxes & service charges',
        ];
    }

    /**
     * Format fare for driver display
     */
    public function formatForDriverDisplay(array $breakdown): array
    {
        return [
            'customer_paid' => $breakdown['final_customer_payment'],
            'your_earning' => $breakdown['driver_earning'],
            'detailed_breakdown' => [
                'base_fare' => $breakdown['subtotal_before_charges'],
                'platform_fee' => -$breakdown['platform_fee'],
                'daily_fee' => -$breakdown['daily_fee'],
                'driver_insurance' => -$breakdown['driver_insurance'],
                'eco_gst' => $breakdown['eco_gst'],
                'platform_gst' => $breakdown['platform_gst'],
                'net_earning' => $breakdown['driver_earning'],
            ],
            'tier_details' => $breakdown['tier_breakdown'],
        ];
    }

    /**
     * Example calculations for documentation
     */
    public static function getExamples(): array
    {
        return [
            'bike_5km' => [
                'description' => 'Bike 5 km trip',
                'calculation' => [
                    'Base (0-2 km)' => '₹25',
                    'Tier 1 (2-5 km, 3km × ₹8)' => '₹24',
                    'Subtotal' => '₹49',
                    'Platform fee' => '+₹5',
                    'Daily fee' => '+₹7 (if first trip)',
                    'Insurance' => '+₹2',
                    'ECO-GST (5%)' => '+₹3.15',
                    'Total' => '₹66.15',
                    'Wallet use' => '-₹5',
                    'Customer pays' => '₹61.15',
                    'Cashback earned' => '+₹5 (to wallet)',
                ],
            ],
            'bike_10km' => [
                'description' => 'Bike 10 km trip',
                'calculation' => [
                    'Base (0-2 km)' => '₹25',
                    'Tier 1 (2-6 km, 4km × ₹8)' => '₹32',
                    'Tier 2 (6-8 km, 2km × ₹9)' => '₹18',
                    'Tier 3 (8-10 km, 2km × ₹10)' => '₹20',
                    'Subtotal' => '₹95',
                    'Platform charges' => '+₹12 (₹5+₹7)',
                    'Insurance' => '+₹2',
                    'ECO-GST (5%)' => '+₹5.45',
                    'Total' => '₹114.45',
                    'Wallet use' => '-₹5',
                    'Customer pays' => '₹109.45',
                    'Cashback earned' => '+₹5',
                ],
            ],
            'auto_5km' => [
                'description' => 'Auto 5 km trip',
                'calculation' => [
                    'Base (0-2 km)' => '₹45',
                    'Tier 1 (2-5 km, 3km × ₹15)' => '₹45',
                    'Subtotal' => '₹90',
                    'Platform fee' => '+₹3',
                    'Daily fee' => '+₹11 (if first)',
                    'Insurance' => '+₹2',
                    'ECO-GST (5%)' => '+₹5.30',
                    'Total' => '₹111.30',
                    'Customer pays' => '₹111.30',
                ],
            ],
            'auto_12km' => [
                'description' => 'Auto 12 km trip',
                'calculation' => [
                    'Base (0-2 km)' => '₹45',
                    'Tier 1 (2-6 km, 4km × ₹15)' => '₹60',
                    'Tier 2 (6-8 km, 2km × ₹16)' => '₹32',
                    'Tier 3 (8-12 km, 4km × ₹18)' => '₹72',
                    'Subtotal' => '₹209',
                    'Platform charges' => '+₹14 (₹3+₹11)',
                    'Insurance' => '+₹2',
                    'ECO-GST (5%)' => '+₹11.25',
                    'Total' => '₹236.25',
                    'Customer pays' => '₹236.25',
                    'Driver bonus (if 20th trip)' => '+₹50',
                ],
            ],
            'car_5km' => [
                'description' => 'Car 5 km trip',
                'calculation' => [
                    'Base (0-2 km)' => '₹75',
                    'Tier 1 (2-5 km, 3km × ₹18)' => '₹54',
                    'Subtotal' => '₹129',
                    'Platform fee' => '+₹11 (or ₹0 with pass)',
                    'Insurance' => '+₹4',
                    'ECO-GST (5%)' => '+₹7.20',
                    'Total' => '₹151.20',
                    'Customer pays' => '₹151.20',
                ],
            ],
            'car_20km' => [
                'description' => 'Car 20 km trip',
                'calculation' => [
                    'Base (0-2 km)' => '₹75',
                    'Tier 1 (2-6 km, 4km × ₹18)' => '₹72',
                    'Tier 2 (6-8 km, 2km × ₹20)' => '₹40',
                    'Tier 3 (8-20 km, 12km × ₹22)' => '₹264',
                    'Subtotal' => '₹451',
                    'Platform fee' => '+₹11',
                    'Insurance' => '+₹4',
                    'ECO-GST (5%)' => '+₹23.30',
                    'Total' => '₹489.30',
                    'Customer pays' => '₹489.30',
                ],
            ],
        ];
    }
}

