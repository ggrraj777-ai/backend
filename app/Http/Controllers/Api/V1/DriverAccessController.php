<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DriverAccessRulesService;
use App\Services\DailyFeeDeductionService;
use App\Models\DriverFeeConfiguration;
use App\Models\DriverDailyActivity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverAccessController extends Controller
{
    private DriverAccessRulesService $accessService;
    private DailyFeeDeductionService $feeService;

    public function __construct(
        DriverAccessRulesService $accessService,
        DailyFeeDeductionService $feeService
    ) {
        $this->accessService = $accessService;
        $this->feeService = $feeService;
    }

    /**
     * @OA\Get(
     *   path="/api/v1/driver/access/status",
     *   tags={"Driver Access"},
     *   summary="Get driver's today access status",
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(
     *     name="vehicle_type",
     *     in="query",
     *     required=true,
     *     @OA\Schema(type="string", enum={"bike", "auto", "car"})
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function getTodayStatus(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_type' => 'required|in:bike,auto,car',
        ]);

        $driver = $request->user();
        $status = $this->accessService->getTodayStatus($driver->id, $request->vehicle_type);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/v1/driver/access/can-accept-trips",
     *   tags={"Driver Access"},
     *   summary="Check if driver can accept trips",
     *   security={{"sanctum":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function canAcceptTrips(Request $request): JsonResponse
    {
        $driver = $request->user();
        $result = $this->accessService->canAcceptTrips($driver->id);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/v1/driver/access/statistics",
     *   tags={"Driver Access"},
     *   summary="Get driver statistics for date range",
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(
     *     name="start_date",
     *     in="query",
     *     required=false,
     *     @OA\Schema(type="string", format="date")
     *   ),
     *   @OA\Parameter(
     *     name="end_date",
     *     in="query",
     *     required=false,
     *     @OA\Schema(type="string", format="date")
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $driver = $request->user();
        
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : today()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : today();

        $stats = $this->accessService->getDriverStatistics($driver->id, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/v1/driver/access/fee-configurations",
     *   tags={"Driver Access"},
     *   summary="Get fee configurations for all vehicle types",
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function getFeeConfigurations(): JsonResponse
    {
        $configurations = DriverFeeConfiguration::getAllActive();

        $data = $configurations->map(function ($config) {
            return [
                'vehicle_type' => $config->vehicle_type,
                'vehicle_name_en' => ucfirst($config->vehicle_type),
                'vehicle_name_te' => $this->getVehicleNameTe($config->vehicle_type),
                'daily_target' => $config->daily_target_trips,
                'daily_fee' => $config->daily_fee,
                'per_trip_fee' => $config->per_trip_fee,
                'min_wallet_balance' => $config->minimum_wallet_balance,
                'welcome_days' => $config->welcome_period_days,
                'description_en' => $config->description_en,
                'description_te' => $config->description_te,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'message_en' => 'Every Day is Free Access — If You Earn More!',
            'message_te' => 'ప్రతి రోజు ఫ్రీ యాక్సెస్ – మీరు సంపాదిస్తే!',
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/v1/driver/access/record-trip-complete",
     *   tags={"Driver Access"},
     *   summary="Record trip completion (Internal use)",
     *   security={{"sanctum":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"trip_id","vehicle_type"},
     *       @OA\Property(property="trip_id", type="string"),
     *       @OA\Property(property="vehicle_type", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function recordTripComplete(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => 'required|string',
            'vehicle_type' => 'required|in:bike,auto,car',
        ]);

        $driver = $request->user();
        
        $this->accessService->recordTripCompleted(
            $driver->id,
            $request->trip_id,
            $request->vehicle_type
        );

        $status = $this->accessService->getTodayStatus($driver->id, $request->vehicle_type);

        return response()->json([
            'success' => true,
            'message' => 'Trip recorded successfully',
            'data' => $status,
        ]);
    }

    /**
     * Helper: Get vehicle name in Telugu
     */
    private function getVehicleNameTe(string $vehicleType): string
    {
        return match($vehicleType) {
            'bike' => 'బైక్',
            'auto' => 'ఆటో',
            'car' => 'కార్',
            default => $vehicleType,
        };
    }
}

