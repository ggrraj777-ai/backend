<?php

namespace Modules\Gateways\Http\Controllers\Api\V1;

use App\Services\RazorpayAutoSplitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class RazorpayAccountController extends Controller
{
    protected $autoSplitService;

    public function __construct(RazorpayAutoSplitService $autoSplitService)
    {
        $this->autoSplitService = $autoSplitService;
    }

    /**
     * Link driver's bank account to Razorpay for auto-split
     * 
     * @OA\Post(
     *   path="/api/v1/driver/razorpay/link-account",
     *   tags={"Driver","Razorpay"},
     *   summary="Link bank account for auto-split settlements",
     *   security={{"sanctum":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"account_holder_name","account_number","ifsc_code"},
     *       @OA\Property(property="account_holder_name", type="string"),
     *       @OA\Property(property="account_number", type="string"),
     *       @OA\Property(property="ifsc_code", type="string"),
     *       @OA\Property(property="bank_name", type="string"),
     *       @OA\Property(property="account_type", type="string", enum={"savings","current"})
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function linkBankAccount(Request $request): JsonResponse
    {
        $request->validate([
            'account_holder_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:20',
            'ifsc_code' => 'required|string|size:11',
            'bank_name' => 'nullable|string|max:100',
            'account_type' => 'nullable|in:savings,current',
        ]);

        $driverId = auth()->user()->id;

        $result = $this->autoSplitService->createLinkedAccount($driverId, [
            'account_holder_name' => $request->account_holder_name,
            'account_number' => $request->account_number,
            'ifsc_code' => $request->ifsc_code,
            'bank_name' => $request->bank_name,
            'account_type' => $request->account_type ?? 'savings',
        ]);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Bank account linked successfully for auto-settlements',
                'account_id' => $result['account_id'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 400);
    }

    /**
     * Link driver's UPI for auto-split (faster alternative)
     * 
     * @OA\Post(
     *   path="/api/v1/driver/razorpay/link-upi",
     *   tags={"Driver","Razorpay"},
     *   summary="Link UPI ID for instant settlements",
     *   security={{"sanctum":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"upi_id"},
     *       @OA\Property(property="upi_id", type="string", example="driver@paytm")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function linkUPI(Request $request): JsonResponse
    {
        $request->validate([
            'upi_id' => 'required|string|regex:/^[\w\.\-]+@[\w]+$/',
        ]);

        $driverId = auth()->user()->id;

        $result = $this->autoSplitService->createUPILinkedAccount(
            $driverId,
            $request->upi_id
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'UPI linked successfully for instant settlements',
                'account_id' => $result['account_id'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 400);
    }

    /**
     * Get linked account status
     * 
     * @OA\Get(
     *   path="/api/v1/driver/razorpay/account-status",
     *   tags={"Driver","Razorpay"},
     *   summary="Check if Razorpay account is linked",
     *   security={{"sanctum":{}}},
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function getAccountStatus(): JsonResponse
    {
        $driverId = auth()->user()->id;

        $account = $this->autoSplitService->getLinkedAccount($driverId);
        $hasAccount = $this->autoSplitService->hasLinkedAccount($driverId);

        return response()->json([
            'success' => true,
            'has_linked_account' => $hasAccount,
            'account' => $account ? [
                'account_type' => $account->upi_id ? 'upi' : 'bank',
                'verification_status' => $account->verification_status,
                'auto_settlement_enabled' => $account->auto_settlement_enabled,
                'total_settled' => $account->total_settled_amount,
                'total_settlements' => $account->total_settlements,
                'last_settlement' => $account->last_settlement_at,
            ] : null,
        ]);
    }

    /**
     * Get settlement history
     * 
     * @OA\Get(
     *   path="/api/v1/driver/razorpay/settlements",
     *   tags={"Driver","Razorpay"},
     *   summary="Get driver's settlement history",
     *   security={{"sanctum":{}}},
     *   @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *   @OA\Parameter(name="offset", in="query", @OA\Schema(type="integer", default=1)),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    public function getSettlements(Request $request): JsonResponse
    {
        $driverId = auth()->user()->id;
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 1);

        $data = $this->autoSplitService->getDriverSettlements($driverId, $limit, $offset);

        return response()->json([
            'success' => true,
            'settlements' => $data['settlements'],
            'total' => $data['total'],
            'summary' => $data['account_summary'],
        ]);
    }
}

