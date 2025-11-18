<?php

namespace Modules\FareManagement\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FareManagement\Service\PlatformFareService;
use Illuminate\Support\Facades\DB;

class PlatformChargeController extends Controller
{
    protected $platformFareService;

    public function __construct(PlatformFareService $platformFareService)
    {
        $this->platformFareService = $platformFareService;
    }

    /**
     * Get platform charges for all vehicle types
     * 
     * @OA\Get(
     *   path="/api/v1/platform/charges",
     *   tags={"Platform"},
     *   summary="Get platform charges configuration",
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function index(): JsonResponse
    {
        $charges = DB::table('platform_charges')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $charges,
        ]);
    }

    /**
     * Get platform charges for specific vehicle type
     */
    public function show(string $vehicleType): JsonResponse
    {
        $charge = DB::table('platform_charges')
            ->where('vehicle_type', $vehicleType)
            ->where('is_active', true)
            ->first();

        if (!$charge) {
            return response()->json([
                'success' => false,
                'message' => 'Platform charges not found for vehicle type: ' . $vehicleType
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $charge,
        ]);
    }

    /**
     * Purchase day pass for car drivers
     * 
     * @OA\Post(
     *   path="/api/v1/driver/purchase-day-pass",
     *   tags={"Driver","Platform"},
     *   summary="Purchase day pass for unlimited trips",
     *   security={{"sanctum":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="vehicle_type", type="string", example="car")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function purchaseDayPass(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_type' => 'required|in:car',
        ]);

        $driverId = auth()->user()->id;
        
        $result = $this->platformFareService->purchaseDayPass(
            $driverId,
            $request->vehicle_type
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Day pass purchased successfully',
                'amount_deducted' => $result['amount'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 400);
    }

    /**
     * Check if driver has active day pass
     * 
     * @OA\Get(
     *   path="/api/v1/driver/day-pass/status",
     *   tags={"Driver","Platform"},
     *   summary="Check active day pass status",
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(
     *     name="vehicle_type",
     *     in="query",
     *     required=true,
     *     @OA\Schema(type="string", enum={"car"})
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function checkDayPassStatus(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_type' => 'required|in:car',
        ]);

        $driverId = auth()->user()->id;
        $hasPass = $this->platformFareService->hasActiveDayPass(
            $driverId,
            $request->vehicle_type
        );

        return response()->json([
            'success' => true,
            'has_active_pass' => $hasPass,
            'vehicle_type' => $request->vehicle_type,
        ]);
    }

    /**
     * Get driver bonus progress (Auto drivers - 20 trips = ₹50)
     * 
     * @OA\Get(
     *   path="/api/v1/driver/bonus/progress",
     *   tags={"Driver","Platform"},
     *   summary="Get driver bonus progress for today",
     *   security={{"sanctum":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function getBonusProgress(): JsonResponse
    {
        $driverId = auth()->user()->id;
        $today = now()->toDateString();

        $bonusRecord = DB::table('driver_trip_bonuses')
            ->where('driver_id', $driverId)
            ->where('bonus_date', $today)
            ->where('vehicle_type', 'auto')
            ->first();

        if (!$bonusRecord) {
            return response()->json([
                'success' => true,
                'trip_count' => 0,
                'remaining_trips' => 20,
                'progress_percent' => 0,
                'is_eligible' => false,
                'is_credited' => false,
                'bonus_amount' => 50,
            ]);
        }

        return response()->json([
            'success' => true,
            'trip_count' => $bonusRecord->trip_count,
            'remaining_trips' => max(20 - $bonusRecord->trip_count, 0),
            'progress_percent' => min(($bonusRecord->trip_count / 20) * 100, 100),
            'is_eligible' => $bonusRecord->trip_count >= 20,
            'is_credited' => $bonusRecord->is_credited ?? false,
            'bonus_amount' => 50,
        ]);
    }

    /**
     * Get customer cashback history
     * 
     * @OA\Get(
     *   path="/api/v1/customer/cashback/history",
     *   tags={"Customer","Platform"},
     *   summary="Get customer cashback history",
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     @OA\Schema(type="integer", default=10)
     *   ),
     *   @OA\Parameter(
     *     name="offset",
     *     in="query",
     *     @OA\Schema(type="integer", default=1)
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function getCashbackHistory(Request $request): JsonResponse
    {
        $customerId = auth()->user()->id;
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 1);

        $cashbacks = DB::table('customer_cashbacks')
            ->where('customer_id', $customerId)
            ->where('is_credited', true)
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $offset);

        return response()->json([
            'success' => true,
            'data' => $cashbacks->items(),
            'total' => $cashbacks->total(),
            'total_cashback' => DB::table('customer_cashbacks')
                ->where('customer_id', $customerId)
                ->where('is_credited', true)
                ->sum('cashback_amount'),
        ]);
    }
}

