<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Razorpay\Api\Api;

/**
 * Razorpay Auto-Split Service
 * Implements Razorpay Route/Transfer API for automatic payment splitting
 * between platform and driver
 */
class RazorpayAutoSplitService
{
    protected $api;

    public function __construct()
    {
        $this->api = new Api(
            config('razor_config.api_key'),
            config('razor_config.api_secret')
        );
    }

    /**
     * Create Razorpay linked account for driver
     * Required for receiving auto-split payments
     * 
     * @param string $driverId
     * @param array $bankDetails
     * @return array
     */
    public function createLinkedAccount(string $driverId, array $bankDetails): array
    {
        try {
            $driver = DB::table('users')->where('id', $driverId)->first();
            
            if (!$driver) {
                return ['success' => false, 'message' => 'Driver not found'];
            }

            // Create contact in Razorpay
            $contact = $this->api->contact->create([
                'name' => $driver->first_name . ' ' . $driver->last_name,
                'email' => $driver->email,
                'contact' => $driver->phone,
                'type' => 'vendor',
                'reference_id' => $driverId,
                'notes' => [
                    'driver_id' => $driverId,
                    'user_type' => 'driver',
                ]
            ]);

            // Create fund account (bank account)
            $fundAccount = $this->api->fundAccount->create([
                'contact_id' => $contact->id,
                'account_type' => 'bank_account',
                'bank_account' => [
                    'name' => $bankDetails['account_holder_name'],
                    'ifsc' => $bankDetails['ifsc_code'],
                    'account_number' => $bankDetails['account_number'],
                ]
            ]);

            // Store in database
            DB::table('driver_razorpay_accounts')->updateOrInsert(
                ['driver_id' => $driverId],
                [
                    'id' => $driver->razorpay_account_id ?? Str::uuid(),
                    'driver_id' => $driverId,
                    'razorpay_account_id' => $fundAccount->id,
                    'razorpay_contact_id' => $contact->id,
                    'account_holder_name' => $bankDetails['account_holder_name'],
                    'account_number' => $bankDetails['account_number'],
                    'ifsc_code' => $bankDetails['ifsc_code'],
                    'bank_name' => $bankDetails['bank_name'] ?? null,
                    'account_type' => $bankDetails['account_type'] ?? 'savings',
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            return [
                'success' => true,
                'account_id' => $fundAccount->id,
                'contact_id' => $contact->id,
            ];
        } catch (\Exception $e) {
            \Log::error('Razorpay linked account creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create payment order with auto-split to driver
     * 
     * @param string $tripId
     * @param float $totalAmount Total amount customer pays
     * @param float $driverShare Amount to transfer to driver
     * @param float $platformShare Amount platform keeps
     * @param string $driverId
     * @return array
     */
    public function createOrderWithAutoSplit(
        string $tripId,
        float $totalAmount,
        float $driverShare,
        float $platformShare,
        string $driverId
    ): array {
        try {
            // Get driver's Razorpay account
            $driverAccount = DB::table('driver_razorpay_accounts')
                ->where('driver_id', $driverId)
                ->where('verification_status', 'verified')
                ->first();

            if (!$driverAccount) {
                return [
                    'success' => false,
                    'message' => 'Driver Razorpay account not linked',
                ];
            }

            // Create order with transfers (auto-split)
            $order = $this->api->order->create([
                'amount' => (int)round($totalAmount * 100), // Amount in paise
                'currency' => 'INR',
                'receipt' => "TRIP-{$tripId}",
                'transfers' => [
                    [
                        'account' => $driverAccount->razorpay_account_id,
                        'amount' => (int)round($driverShare * 100),
                        'currency' => 'INR',
                        'notes' => [
                            'trip_id' => $tripId,
                            'driver_id' => $driverId,
                            'type' => 'driver_share',
                        ],
                        'linked_account_notes' => [
                            'trip_id' => $tripId,
                        ],
                        'on_hold' => false, // Instant transfer
                        'on_hold_until' => null,
                    ]
                ],
                'notes' => [
                    'trip_id' => $tripId,
                    'driver_id' => $driverId,
                    'total_amount' => $totalAmount,
                    'driver_share' => $driverShare,
                    'platform_share' => $platformShare,
                ]
            ]);

            return [
                'success' => true,
                'order_id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'key_id' => config('razor_config.api_key'),
                'driver_share' => $driverShare,
                'platform_share' => $platformShare,
            ];
        } catch (\Exception $e) {
            \Log::error('Auto-split order creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Manual transfer to driver (for cash/wallet payments)
     * 
     * @param string $driverId
     * @param float $amount
     * @param string $tripId
     * @return array
     */
    public function transferToDriver(string $driverId, float $amount, string $tripId): array
    {
        try {
            $driverAccount = DB::table('driver_razorpay_accounts')
                ->where('driver_id', $driverId)
                ->where('verification_status', 'verified')
                ->first();

            if (!$driverAccount) {
                return ['success' => false, 'message' => 'Driver account not found'];
            }

            // Create transfer
            $transfer = $this->api->transfer->create([
                'account' => $driverAccount->razorpay_account_id,
                'amount' => (int)round($amount * 100),
                'currency' => 'INR',
                'notes' => [
                    'trip_id' => $tripId,
                    'driver_id' => $driverId,
                    'type' => 'manual_transfer',
                ]
            ]);

            // Record settlement
            $this->recordSettlement(
                driverId: $driverId,
                tripId: $tripId,
                transferId: $transfer->id,
                driverShare: $amount,
                status: 'settled'
            );

            return [
                'success' => true,
                'transfer_id' => $transfer->id,
                'amount' => $amount,
            ];
        } catch (\Exception $e) {
            \Log::error('Manual transfer failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Record settlement in database
     */
    public function recordSettlement(
        string $driverId,
        string $tripId,
        string $transferId,
        float $driverShare,
        float $platformShare = 0,
        float $tripFare = 0,
        string $paymentId = null,
        string $orderId = null,
        string $status = 'settled'
    ): void {
        DB::table('razorpay_settlements')->insert([
            'id' => Str::uuid(),
            'driver_id' => $driverId,
            'trip_id' => $tripId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_transfer_id' => $transferId,
            'razorpay_order_id' => $orderId,
            'trip_fare' => $tripFare,
            'platform_share' => $platformShare,
            'driver_share' => $driverShare,
            'status' => $status,
            'settled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update driver account total
        DB::table('driver_razorpay_accounts')
            ->where('driver_id', $driverId)
            ->increment('total_settled_amount', $driverShare);
        
        DB::table('driver_razorpay_accounts')
            ->where('driver_id', $driverId)
            ->increment('total_settlements');
        
        DB::table('driver_razorpay_accounts')
            ->where('driver_id', $driverId)
            ->update(['last_settlement_at' => now()]);
    }

    /**
     * Get driver's settlement history
     */
    public function getDriverSettlements(string $driverId, int $limit = 10, int $offset = 1): array
    {
        $settlements = DB::table('razorpay_settlements')
            ->where('driver_id', $driverId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $offset);

        $account = DB::table('driver_razorpay_accounts')
            ->where('driver_id', $driverId)
            ->first();

        return [
            'settlements' => $settlements->items(),
            'total' => $settlements->total(),
            'account_summary' => [
                'total_settled' => $account->total_settled_amount ?? 0,
                'total_settlements' => $account->total_settlements ?? 0,
                'last_settlement' => $account->last_settlement_at ?? null,
            ]
        ];
    }

    /**
     * Calculate split amounts based on trip fare
     * 
     * @param float $tripFare Base trip fare
     * @param array $fareBreakdown Complete fare breakdown from TieredFareCalculator
     * @return array
     */
    public function calculateSplitAmounts(float $tripFare, array $fareBreakdown): array
    {
        // Driver share = Base fare - Platform fees - Insurance
        $driverShare = $fareBreakdown['driver_earning'] ?? (
            $tripFare - 
            ($fareBreakdown['platform_fee'] ?? 0) - 
            ($fareBreakdown['daily_fee'] ?? 0) - 
            ($fareBreakdown['driver_insurance'] ?? 0)
        );

        // Platform share = Total customer payment - Driver share
        $platformShare = ($fareBreakdown['final_customer_payment'] ?? $tripFare) - $driverShare;

        return [
            'total_amount' => $fareBreakdown['final_customer_payment'] ?? $tripFare,
            'driver_share' => round($driverShare, 2),
            'platform_share' => round($platformShare, 2),
            'split_percentage' => [
                'driver' => round(($driverShare / $tripFare) * 100, 2),
                'platform' => round(($platformShare / $tripFare) * 100, 2),
            ]
        ];
    }

    /**
     * Process auto-split after payment completion
     * Called when customer makes payment via Razorpay
     */
    public function processAutoSplit(
        string $tripId,
        string $paymentId,
        string $orderId,
        array $fareBreakdown
    ): array {
        try {
            $trip = DB::table('trip_requests')->where('id', $tripId)->first();
            
            if (!$trip) {
                return ['success' => false, 'message' => 'Trip not found'];
            }

            // Calculate split amounts
            $splitAmounts = $this->calculateSplitAmounts($trip->paid_fare ?? $trip->estimated_fare, $fareBreakdown);

            // Get payment details from Razorpay
            $payment = $this->api->payment->fetch($paymentId);

            // Check if payment has transfers (auto-split was configured)
            if (!empty($payment->transfers)) {
                $transfer = $payment->transfers->first();
                
                // Record the settlement
                $this->recordSettlement(
                    driverId: $trip->driver_id,
                    tripId: $tripId,
                    transferId: $transfer->id,
                    driverShare: $splitAmounts['driver_share'],
                    platformShare: $splitAmounts['platform_share'],
                    tripFare: $splitAmounts['total_amount'],
                    paymentId: $paymentId,
                    orderId: $orderId,
                    status: 'settled'
                );

                return [
                    'success' => true,
                    'auto_split' => true,
                    'transfer_id' => $transfer->id,
                    'driver_share' => $splitAmounts['driver_share'],
                    'platform_share' => $splitAmounts['platform_share'],
                ];
            }

            return [
                'success' => false,
                'message' => 'No auto-split configured for this payment',
            ];
        } catch (\Exception $e) {
            \Log::error('Auto-split processing failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create UPI-based linked account (faster onboarding)
     */
    public function createUPILinkedAccount(string $driverId, string $upiId): array
    {
        try {
            $driver = DB::table('users')->where('id', $driverId)->first();
            
            // Create contact
            $contact = $this->api->contact->create([
                'name' => $driver->first_name . ' ' . $driver->last_name,
                'email' => $driver->email,
                'contact' => $driver->phone,
                'type' => 'vendor',
                'reference_id' => $driverId,
            ]);

            // Create UPI fund account
            $fundAccount = $this->api->fundAccount->create([
                'contact_id' => $contact->id,
                'account_type' => 'vpa',
                'vpa' => [
                    'address' => $upiId,
                ]
            ]);

            // Store in database
            DB::table('driver_razorpay_accounts')->updateOrInsert(
                ['driver_id' => $driverId],
                [
                    'id' => Str::uuid(),
                    'driver_id' => $driverId,
                    'razorpay_account_id' => $fundAccount->id,
                    'razorpay_contact_id' => $contact->id,
                    'upi_id' => $upiId,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            return [
                'success' => true,
                'account_id' => $fundAccount->id,
                'message' => 'UPI account linked successfully',
            ];
        } catch (\Exception $e) {
            \Log::error('UPI account creation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check if driver has linked Razorpay account
     */
    public function hasLinkedAccount(string $driverId): bool
    {
        return DB::table('driver_razorpay_accounts')
            ->where('driver_id', $driverId)
            ->where('verification_status', 'verified')
            ->exists();
    }

    /**
     * Get linked account details
     */
    public function getLinkedAccount(string $driverId): ?object
    {
        return DB::table('driver_razorpay_accounts')
            ->where('driver_id', $driverId)
            ->first();
    }
}

