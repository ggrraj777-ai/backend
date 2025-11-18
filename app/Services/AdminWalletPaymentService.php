<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Razorpay\Api\Api;

class AdminWalletPaymentService
{
    protected $razorpayApi;
    protected $keyId;
    protected $keySecret;

    public function __construct()
    {
        $this->keyId = env('RAZORPAY_KEY_ID');
        $this->keySecret = env('RAZORPAY_KEY_SECRET');
        $this->razorpayApi = new Api($this->keyId, $this->keySecret);
    }

    /**
     * Determine eligible users for bulk wallet operations
     */
    protected function buildBulkUserQuery(string $userType)
    {
        $query = DB::table('users')
            ->join('user_accounts', 'users.id', '=', 'user_accounts.user_id')
            ->select('users.id')
            ->where('users.is_active', true);

        if ($userType === 'all') {
            $query->whereIn('users.user_type', ['customer', 'driver']);
        } else {
            $query->where('users.user_type', $userType);
        }

        return $query;
    }

    /**
     * Create Razorpay order for admin wallet top-up
     */
    public function createWalletTopUpOrder(array $data)
    {
        try {
            // Create order in database first
            $orderId = 'WALLET_' . strtoupper(Str::random(12));
            
            $walletOrder = DB::table('admin_wallet_payment_orders')->insertGetId([
                'order_id' => $orderId,
                'admin_id' => $data['admin_id'],
                'user_id' => $data['user_id'],
                'user_type' => $data['user_type'],
                'amount' => $data['amount'],
                'currency' => 'INR',
                'payment_method' => $data['payment_method'], // UPI or Netbanking
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create Razorpay order
            $razorpayOrder = $this->razorpayApi->order->create([
                'receipt' => $orderId,
                'amount' => $data['amount'] * 100, // Convert to paise
                'currency' => 'INR',
                'notes' => [
                    'admin_id' => $data['admin_id'],
                    'user_id' => $data['user_id'],
                    'user_type' => $data['user_type'],
                    'purpose' => 'Admin Wallet Top-up',
                ]
            ]);

            // Update with Razorpay order ID
            DB::table('admin_wallet_payment_orders')
                ->where('id', $walletOrder)
                ->update([
                    'razorpay_order_id' => $razorpayOrder->id,
                    'updated_at' => now(),
                ]);

            return [
                'success' => true,
                'order_id' => $orderId,
                'razorpay_order_id' => $razorpayOrder->id,
                'amount' => $data['amount'],
                'key_id' => $this->keyId,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create payment order: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create Razorpay order for bulk wallet top-up
     */
    public function createBulkWalletTopUpOrder(array $data): array
    {
        try {
            $userQuery = $this->buildBulkUserQuery($data['user_type']);
            $targetUsersCount = (clone $userQuery)->count();

            if ($targetUsersCount === 0) {
                return [
                    'success' => false,
                    'message' => 'No active users found for the selected criteria.',
                ];
            }

            $totalAmount = round($data['per_user_amount'] * $targetUsersCount, 2);
            $orderId = 'BULK_WALLET_' . strtoupper(Str::random(12));

            DB::beginTransaction();

            $bulkOrderId = DB::table('admin_wallet_bulk_payment_orders')->insertGetId([
                'order_id' => $orderId,
                'admin_id' => $data['admin_id'],
                'user_type' => $data['user_type'],
                'per_user_amount' => $data['per_user_amount'],
                'target_users_count' => $targetUsersCount,
                'total_amount' => $totalAmount,
                'currency' => 'INR',
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $timestamp = now();

            (clone $userQuery)
                ->orderBy('users.id')
                ->chunk(500, function ($users) use ($bulkOrderId, $timestamp) {
                    $rows = [];

                    foreach ($users as $user) {
                        $rows[] = [
                            'bulk_order_id' => $bulkOrderId,
                            'user_id' => $user->id,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }

                    if (!empty($rows)) {
                        DB::table('admin_wallet_bulk_payment_order_users')->insert($rows);
                    }
                });

            // Create Razorpay order for total amount
            $razorpayOrder = $this->razorpayApi->order->create([
                'receipt' => $orderId,
                'amount' => (int) round($totalAmount * 100),
                'currency' => 'INR',
                'notes' => [
                    'admin_id' => $data['admin_id'],
                    'user_type' => $data['user_type'],
                    'per_user_amount' => $data['per_user_amount'],
                    'purpose' => 'Admin Bulk Wallet Top-up',
                ],
            ]);

            DB::table('admin_wallet_bulk_payment_orders')
                ->where('id', $bulkOrderId)
                ->update([
                    'razorpay_order_id' => $razorpayOrder->id,
                    'updated_at' => now(),
                ]);

            DB::commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'razorpay_order_id' => $razorpayOrder->id,
                'total_amount' => $totalAmount,
                'per_user_amount' => $data['per_user_amount'],
                'target_users_count' => $targetUsersCount,
                'reference' => $data['reference'] ?? null,
                'key_id' => $this->keyId,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create bulk wallet payment order: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to create bulk payment order: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify and process payment
     */
    public function verifyAndProcessPayment(array $data)
    {
        try {
            // Verify signature
            $attributes = [
                'razorpay_order_id' => $data['razorpay_order_id'],
                'razorpay_payment_id' => $data['razorpay_payment_id'],
                'razorpay_signature' => $data['razorpay_signature'],
            ];

            $this->razorpayApi->utility->verifyPaymentSignature($attributes);

            // Get payment order
            $order = DB::table('admin_wallet_payment_orders')
                ->where('razorpay_order_id', $data['razorpay_order_id'])
                ->first();

            if (!$order) {
                return ['success' => false, 'message' => 'Order not found'];
            }

            // Fetch payment details from Razorpay
            $payment = $this->razorpayApi->payment->fetch($data['razorpay_payment_id']);

            DB::beginTransaction();
            try {
                // Update payment order
                DB::table('admin_wallet_payment_orders')
                    ->where('id', $order->id)
                    ->update([
                        'razorpay_payment_id' => $data['razorpay_payment_id'],
                        'razorpay_signature' => $data['razorpay_signature'],
                        'payment_method_used' => $payment->method ?? null,
                        'status' => 'completed',
                        'paid_at' => now(),
                        'updated_at' => now(),
                    ]);

                // Credit wallet
                $this->creditWallet($order->user_id, $order->amount, $order->user_type, $order->admin_id, $order->order_id);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Payment successful and wallet credited',
                    'amount' => $order->amount,
                    'user_id' => $order->user_id,
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Credit wallet after successful payment
     */
    protected function creditWallet($userId, $amount, $userType, $adminId, $orderId)
    {
        // Update user account balance
        DB::table('user_accounts')
            ->where('user_id', $userId)
            ->increment('wallet_balance', $amount);

        $updatedBalance = DB::table('user_accounts')
            ->where('user_id', $userId)
            ->value('wallet_balance');

        // Create transaction record aligned with current schema
        DB::table('transactions')->insert([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'attribute' => 'admin_wallet_topup',
            'account' => 'wallet_balance',
            'credit' => $amount,
            'debit' => 0,
            'balance' => $updatedBalance,
            'transaction_type' => 'admin_wallet_topup',
            'trx_ref_id' => $orderId,
            'reference' => $orderId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $balanceBefore = $updatedBalance - $amount;

        // Create admin wallet action log aligned with audit schema
        DB::table('admin_wallet_actions')->insert([
            'id' => Str::uuid(),
            'admin_id' => $adminId,
            'user_id' => $userId,
            'user_type' => $userType,
            'transaction_type' => 'credit',
            'amount' => $amount,
            'affected_users_count' => 1,
            'note' => 'Wallet credited via Razorpay payment',
            'reference' => $orderId,
            'balance_before' => $balanceBefore,
            'balance_after' => $updatedBalance,
            'created_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Process Razorpay verification and credit for bulk orders
     */
    public function verifyAndProcessBulkPayment(array $data): array
    {
        try {
            $attributes = [
                'razorpay_order_id' => $data['razorpay_order_id'],
                'razorpay_payment_id' => $data['razorpay_payment_id'],
                'razorpay_signature' => $data['razorpay_signature'],
            ];

            $this->razorpayApi->utility->verifyPaymentSignature($attributes);

            $order = DB::table('admin_wallet_bulk_payment_orders')
                ->where('razorpay_order_id', $data['razorpay_order_id'])
                ->first();

            if (!$order) {
                return ['success' => false, 'message' => 'Bulk order not found'];
            }

            if ($order->status === 'completed') {
                return [
                    'success' => true,
                    'message' => 'Payment already processed',
                    'total_amount' => $order->total_amount,
                    'per_user_amount' => $order->per_user_amount,
                    'target_users_count' => $order->target_users_count,
                    'reference' => $order->reference ?? $order->order_id,
                ];
            }

            $payment = $this->razorpayApi->payment->fetch($data['razorpay_payment_id']);

            DB::beginTransaction();

            DB::table('admin_wallet_bulk_payment_orders')
                ->where('id', $order->id)
                ->update([
                    'razorpay_payment_id' => $data['razorpay_payment_id'],
                    'razorpay_signature' => $data['razorpay_signature'],
                    'payment_method_used' => $payment->method ?? null,
                    'status' => 'processing',
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->creditBulkWalletUsers($order);

            DB::table('admin_wallet_bulk_payment_orders')
                ->where('id', $order->id)
                ->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('admin_wallet_actions')->insert([
                'id' => Str::uuid(),
                'admin_id' => $order->admin_id,
                'user_id' => null,
                'user_type' => $order->user_type,
                'transaction_type' => 'bulk_credit',
                'amount' => $order->per_user_amount,
                'affected_users_count' => $order->target_users_count,
                'note' => $order->notes,
                'reference' => $order->reference ?? $order->order_id,
                'balance_before' => null,
                'balance_after' => null,
                'created_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Bulk payment successful and wallets credited',
                'total_amount' => $order->total_amount,
                'per_user_amount' => $order->per_user_amount,
                'target_users_count' => $order->target_users_count,
                'reference' => $order->reference ?? $order->order_id,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk payment verification failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Bulk payment verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Credit wallets for a bulk order
     */
    protected function creditBulkWalletUsers(object $order): void
    {
        $now = now();

        DB::table('admin_wallet_bulk_payment_order_users')
            ->where('bulk_order_id', $order->id)
            ->orderBy('id')
            ->chunk(200, function ($chunk) use ($order, $now) {
                if ($chunk->isEmpty()) {
                    return;
                }

                $userIds = $chunk->pluck('user_id')->all();

                $accounts = DB::table('user_accounts')
                    ->join('users', 'users.id', '=', 'user_accounts.user_id')
                    ->select('user_accounts.user_id', 'user_accounts.wallet_balance', 'users.user_type')
                    ->whereIn('user_accounts.user_id', $userIds)
                    ->lockForUpdate()
                    ->get();

                $transactions = [];

                foreach ($accounts as $account) {
                    $newBalance = $account->wallet_balance + $order->per_user_amount;

                    DB::table('user_accounts')
                        ->where('user_id', $account->user_id)
                        ->update([
                            'wallet_balance' => $newBalance,
                            'updated_at' => $now,
                        ]);

                    $transactions[] = [
                        'id' => Str::uuid(),
                        'user_id' => $account->user_id,
                        'attribute' => 'admin_wallet_bulk_topup',
                        'account' => 'wallet_balance',
                        'credit' => $order->per_user_amount,
                        'debit' => 0,
                        'balance' => $newBalance,
                        'transaction_type' => 'admin_wallet_topup',
                        'trx_ref_id' => $order->order_id,
                        'reference' => $order->reference ?? $order->order_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($transactions)) {
                    DB::table('transactions')->insert($transactions);
                }
            });
    }

    /**
     * Handle payment failure
     */
    public function handlePaymentFailure($razorpayOrderId, $reason)
    {
        $updated = DB::table('admin_wallet_payment_orders')
            ->where('razorpay_order_id', $razorpayOrderId)
            ->update([
                'status' => 'failed',
                'failure_reason' => $reason,
                'updated_at' => now(),
            ]);

        if (!$updated) {
            DB::table('admin_wallet_bulk_payment_orders')
                ->where('razorpay_order_id', $razorpayOrderId)
                ->update([
                    'status' => 'failed',
                    'failure_reason' => $reason,
                    'updated_at' => now(),
                ]);
        }

        return ['success' => true, 'message' => 'Payment failure recorded'];
    }

    /**
     * Get payment order details
     */
    public function getOrderDetails($orderId)
    {
        return DB::table('admin_wallet_payment_orders')
            ->where('order_id', $orderId)
            ->orWhere('razorpay_order_id', $orderId)
            ->first();
    }

    /**
     * Refund wallet top-up amount back to Razorpay
     */
    public function refundWalletTopUp(array $data): array
    {
        if (empty($this->keyId) || empty($this->keySecret)) {
            return [
                'success' => false,
                'message' => 'Razorpay credentials are not configured.',
            ];
        }

        $amountToRefund = round($data['amount'], 2);

        if ($amountToRefund <= 0) {
            return [
                'success' => true,
                'refunded_amount' => 0,
                'refunds' => [],
            ];
        }

        $orders = DB::table('admin_wallet_payment_orders')
            ->where('user_id', $data['user_id'])
            ->where('status', 'completed')
            ->whereNotNull('razorpay_payment_id')
            ->whereColumn('refunded_amount', '<', 'amount')
            ->orderBy('paid_at')
            ->lockForUpdate()
            ->get();

        if ($orders->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No refundable Razorpay payments found for this user.',
            ];
        }

        $refunds = [];
        $remaining = $amountToRefund;

        try {
            foreach ($orders as $order) {
                if ($remaining <= 0) {
                    break;
                }

                $available = (float) $order->amount - (float) $order->refunded_amount;

                if ($available <= 0) {
                    continue;
                }

                $refundAmount = min($available, $remaining);
                $refundPaise = (int) round($refundAmount * 100);

                $payment = $this->razorpayApi->payment->fetch($order->razorpay_payment_id);
                $refundEntity = $payment->refund([
                    'amount' => $refundPaise,
                    'notes' => [
                        'user_id' => $data['user_id'],
                        'admin_id' => $data['admin_id'],
                        'reason' => $data['reason'] ?? 'Admin wallet deduction',
                        'reference' => $data['reference'] ?? null,
                    ],
                ]);

                $razorpayRefundId = $refundEntity['id'] ?? null;
                $refundStatus = $refundEntity['status'] ?? 'pending';

                DB::table('admin_wallet_payment_orders')
                    ->where('id', $order->id)
                    ->update([
                        'refunded_amount' => DB::raw('refunded_amount + ' . number_format($refundAmount, 2, '.', '')),
                        'last_refunded_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('admin_wallet_payment_refunds')->insert([
                    'payment_order_id' => $order->id,
                    'admin_id' => $data['admin_id'],
                    'user_id' => $data['user_id'],
                    'amount' => $refundAmount,
                    'currency' => $order->currency ?? 'INR',
                    'razorpay_refund_id' => $razorpayRefundId,
                    'razorpay_payment_id' => $order->razorpay_payment_id,
                    'razorpay_order_id' => $order->razorpay_order_id,
                    'status' => $refundStatus === 'processed' ? 'succeeded' : $refundStatus,
                    'reference' => $data['reference'] ?? $razorpayRefundId,
                    'notes' => $data['reason'] ?? null,
                    'failure_reason' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $refunds[] = [
                    'payment_order_id' => $order->id,
                    'razorpay_payment_id' => $order->razorpay_payment_id,
                    'razorpay_refund_id' => $razorpayRefundId,
                    'amount' => $refundAmount,
                    'status' => $refundStatus,
                ];

                $remaining -= $refundAmount;
            }
        } catch (\Exception $exception) {
            Log::error('Razorpay refund failed', [
                'error' => $exception->getMessage(),
                'user_id' => $data['user_id'],
                'admin_id' => $data['admin_id'],
            ]);

            return [
                'success' => false,
                'message' => 'Razorpay refund failed: ' . $exception->getMessage(),
            ];
        }

        if ($remaining > 0 && $amountToRefund > 0) {
            return [
                'success' => false,
                'message' => 'Unable to allocate enough Razorpay payments to refund the requested amount.',
            ];
        }

        return [
            'success' => true,
            'refunded_amount' => $amountToRefund - max($remaining, 0),
            'refunds' => $refunds,
        ];
    }
}

