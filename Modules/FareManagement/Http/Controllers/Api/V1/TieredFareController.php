<?php

namespace Modules\FareManagement\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FareManagement\Service\TieredFareCalculator;
use Illuminate\Support\Facades\DB;

class TieredFareController extends Controller
{
    protected $tieredFareCalculator;

    public function __construct(TieredFareCalculator $tieredFareCalculator)
    {
        $this->tieredFareCalculator = $tieredFareCalculator;
    }

    /**
     * Get tiered fare configuration for all vehicle types
     * 
     * @OA\Get(
     *   path="/api/v1/fare/tiered/config",
     *   tags={"Fare"},
     *   summary="Get tiered fare configuration",
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function getConfig(): JsonResponse
    {
        $configs = DB::table('tiered_fare_config')
            ->where('is_active', true)
            ->whereNull('zone_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Get tiered fare for specific vehicle type
     */
    public function getVehicleConfig(string $vehicleType): JsonResponse
    {
        $config = DB::table('tiered_fare_config')
            ->where('vehicle_type', $vehicleType)
            ->where('is_active', true)
            ->whereNull('zone_id')
            ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration not found for vehicle type: ' . $vehicleType
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Calculate estimated fare
     * 
     * @OA\Post(
     *   path="/api/v1/fare/calculate/tiered",
     *   tags={"Fare"},
     *   summary="Calculate tiered fare for a trip",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"vehicle_type","distance"},
     *       @OA\Property(property="vehicle_type", type="string", enum={"bike","auto","car"}),
     *       @OA\Property(property="distance", type="number", example=5.5),
     *       @OA\Property(property="zone_id", type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function calculateFare(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_type' => 'required|in:bike,auto,car',
            'distance' => 'required|numeric|min:0',
            'zone_id' => 'nullable|string',
        ]);

        try {
            $breakdown = $this->tieredFareCalculator->calculateTieredFare(
                $request->vehicle_type,
                $request->distance,
                $request->zone_id
            );

            return response()->json([
                'success' => true,
                'breakdown' => $breakdown,
                'formatted_for_display' => $this->formatForDisplay($breakdown),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate complete fare with all charges
     * 
     * @OA\Post(
     *   path="/api/v1/fare/calculate/complete",
     *   tags={"Fare"},
     *   summary="Calculate complete fare with platform charges and GST",
     *   security={{"sanctum":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"vehicle_type","distance"},
     *       @OA\Property(property="vehicle_type", type="string", enum={"bike","auto","car"}),
     *       @OA\Property(property="distance", type="number", example=5.5),
     *       @OA\Property(property="zone_id", type="string", nullable=true),
     *       @OA\Property(property="has_day_pass", type="boolean", example=false)
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function calculateCompleteFare(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_type' => 'required|in:bike,auto,car',
            'distance' => 'required|numeric|min:0',
            'zone_id' => 'nullable|string',
            'has_day_pass' => 'nullable|boolean',
        ]);

        try {
            $userId = auth()->user()->id;
            $hasDayPass = $request->has_day_pass ?? false;

            $breakdown = $this->tieredFareCalculator->calculateCompleteFare(
                vehicleType: $request->vehicle_type,
                distanceKm: $request->distance,
                driverId: $userId,
                customerId: $userId,
                zoneId: $request->zone_id,
                hasDayPass: $hasDayPass
            );

            // Format for customer and driver
            $customerDisplay = $this->tieredFareCalculator->formatForCustomerDisplay($breakdown);
            $driverDisplay = $this->tieredFareCalculator->formatForDriverDisplay($breakdown);

            return response()->json([
                'success' => true,
                'complete_breakdown' => $breakdown,
                'customer_view' => $customerDisplay,
                'driver_view' => $driverDisplay,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get fare breakdown for a specific trip
     */
    public function getTripFareBreakdown(string $tripId): JsonResponse
    {
        $trip = DB::table('trip_requests')
            ->where('id', $tripId)
            ->first();

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found'
            ], 404);
        }

        $fee = DB::table('trip_request_fees')
            ->where('trip_request_id', $tripId)
            ->first();

        if (!$fee) {
            return response()->json([
                'success' => false,
                'message' => 'Fee details not found'
            ], 404);
        }

        $breakdown = [
            'trip_id' => $tripId,
            'vehicle_type' => $trip->vehicle_type ?? 'unknown',
            'distance_km' => $trip->actual_distance ?? $trip->estimated_distance,
            
            // Fare components
            'base_fare' => $trip->actual_fare ?? $trip->estimated_fare,
            'platform_fee' => $fee->platform_fee ?? 0,
            'daily_fee' => $fee->daily_fee ?? 0,
            'customer_insurance' => $fee->customer_insurance ?? 0,
            'driver_insurance' => $fee->driver_insurance ?? 0,
            'total_insurance' => $fee->total_insurance ?? 0,
            'eco_gst' => $fee->vat_tax ?? 0, // ECO-GST stored in vat_tax
            'cashback_amount' => $fee->cashback_amount ?? 0,
            'wallet_deduction' => $fee->wallet_deduction ?? 0,
            
            // Totals
            'customer_paid' => $trip->paid_fare ?? 0,
            'driver_earning' => ($trip->actual_fare ?? 0) - ($fee->platform_fee ?? 0) - ($fee->daily_fee ?? 0) - ($fee->driver_insurance ?? 0),
        ];

        return response()->json([
            'success' => true,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Format breakdown for mobile display
     */
    private function formatForDisplay(array $breakdown): array
    {
        return [
            'total_distance' => $breakdown['total_distance_km'] . ' km',
            'base_fare' => '₹' . number_format($breakdown['base_fare'], 2),
            'distance_fare' => '₹' . number_format($breakdown['distance_fare'], 2),
            'subtotal' => '₹' . number_format($breakdown['subtotal_before_charges'], 2),
            'tier_breakdown' => array_map(function($tier) {
                return [
                    'label' => $tier['range'],
                    'rate' => $tier['rate'],
                    'distance' => $tier['distance'] . ' km',
                    'amount' => '₹' . number_format($tier['amount'], 2),
                ];
            }, $breakdown['tier_breakdown']),
        ];
    }
}

