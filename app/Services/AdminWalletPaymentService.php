<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
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

        // Create admin wallet action log
        DB::table('admin_wallet_actions')->insert([
            'id' => Str::uuid(),
            'admin_id' => $adminId,
            'user_id' => $userId,
            'action_type' => 'add',
            'amount' => $amount,
            'payment_method' => 'razorpay',
            'transaction_ref' => $orderId,
            'notes' => "Wallet credited via Razorpay payment",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Handle payment failure
     */
    public function handlePaymentFailure($razorpayOrderId, $reason)
    {
        DB::table('admin_wallet_payment_orders')
            ->where('razorpay_order_id', $razorpayOrderId)
            ->update([
                'status' => 'failed',
                'failure_reason' => $reason,
                'updated_at' => now(),
            ]);

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
}

