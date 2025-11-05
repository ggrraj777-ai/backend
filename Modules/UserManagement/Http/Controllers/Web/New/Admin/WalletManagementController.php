<?php

namespace Modules\UserManagement\Http\Controllers\Web\New\Admin;

use App\Http\Controllers\BaseController;
use App\Services\AdminWalletPaymentService;
use App\Services\PushNotificationService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin Wallet Management Controller
 * Allows admin to add/deduct money from both customer and driver wallets
 */
class WalletManagementController extends BaseController
{
    /**
     * Display unified wallet management page
     */
    public function index(?Request $request = null, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        // Ensure request is not null (create new instance if needed)
        $request = $request ?? request();
        
        $userType = $request->get('user_type', 'customer');
        $search = $request->get('search');

        $users = DB::table('users')
            ->join('user_accounts', 'users.id', '=', 'user_accounts.user_id')
            ->select('users.*', 'user_accounts.wallet_balance', 'user_accounts.payable_balance', 'user_accounts.receivable_balance')
            ->where('users.user_type', $userType)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.first_name', 'like', "%{$search}%")
                      ->orWhere('users.last_name', 'like', "%{$search}%")
                      ->orWhere('users.phone', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.created_at', 'desc')
            ->paginate(20);

        return view('usermanagement::admin.wallet.index', compact('users', 'userType', 'search'));
    }

    /**
     * Add money to user/driver wallet
     */
    public function addMoney(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'user_id' => 'required|uuid',
            'amount' => 'required|numeric|min:1',
            'transaction_type' => 'required|in:credit,debit',
            'note' => 'nullable|string|max:500',
            'reference' => 'nullable|string|max:100',
        ]);

        $user = DB::table('users')->where('id', $request->user_id)->first();
        
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }
            Toastr::error('User not found');
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            $amount = abs($request->amount);
            
            // Update wallet balance
            if ($request->transaction_type === 'credit') {
                DB::table('user_accounts')
                    ->where('user_id', $request->user_id)
                    ->increment('wallet_balance', $amount);
                $transactionType = 'credit';
                $message = 'Money added successfully';
            } else {
                // Check sufficient balance for debit
                $account = DB::table('user_accounts')->where('user_id', $request->user_id)->first();
                if ($account->wallet_balance < $amount) {
                    DB::rollBack();
                    if ($request->expectsJson()) {
                        return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 400);
                    }
                    Toastr::error('Insufficient wallet balance');
                    return redirect()->back();
                }

                DB::table('user_accounts')
                    ->where('user_id', $request->user_id)
                    ->decrement('wallet_balance', $amount);
                $transactionType = 'debit';
                $message = 'Money deducted successfully';
            }

            // Get updated balance
            $updatedAccount = DB::table('user_accounts')->where('user_id', $request->user_id)->first();

            // Record transaction
            DB::table('transactions')->insert([
                'id' => Str::uuid(),
                'user_id' => $request->user_id,
                'attribute' => $transactionType === 'credit' ? 'wallet_fund_by_admin' : 'wallet_deduct_by_admin',
                'account' => 'wallet_balance',
                $transactionType => $amount,
                'balance' => $updatedAccount->wallet_balance,
                'trx_ref_id' => $request->reference ?? 'ADMIN-' . Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Record admin wallet action for audit
            DB::table('admin_wallet_actions')->insert([
                'id' => Str::uuid(),
                'admin_id' => auth()->user()->id,
                'user_id' => $request->user_id,
                'user_type' => $user->user_type,
                'transaction_type' => $transactionType,
                'amount' => $amount,
                'note' => $request->note,
                'reference' => $request->reference,
                'balance_before' => $transactionType === 'credit' 
                    ? $updatedAccount->wallet_balance - $amount 
                    : $updatedAccount->wallet_balance + $amount,
                'balance_after' => $updatedAccount->wallet_balance,
                'created_at' => now(),
            ]);

            DB::commit();

            // Send notification to user
            $this->sendWalletNotification($user, $transactionType, $amount);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'new_balance' => $updatedAccount->wallet_balance,
                ]);
            }

            Toastr::success($message);
            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Wallet operation failed: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            Toastr::error('Operation failed: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Bulk wallet operation (add money to multiple users)
     */
    public function bulkAddMoney(Request $request): RedirectResponse
    {
        $request->validate([
            'user_type' => 'required|in:customer,driver,all',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string|max:500',
            'reference' => 'required|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $amount = abs($request->amount);

            // Get target users
            $query = DB::table('users')
                ->join('user_accounts', 'users.id', '=', 'user_accounts.user_id')
                ->where('users.is_active', true);

            if ($request->user_type !== 'all') {
                $query->where('users.user_type', $request->user_type);
            } else {
                $query->whereIn('users.user_type', ['customer', 'driver']);
            }

            $users = $query->get();
            $transactionsData = [];

            foreach ($users as $user) {
                // Update wallet
                DB::table('user_accounts')
                    ->where('user_id', $user->id)
                    ->increment('wallet_balance', $amount);

                // Prepare transaction record
                $transactionsData[] = [
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'attribute' => 'bulk_wallet_fund_by_admin',
                    'account' => 'wallet_balance',
                    'credit' => $amount,
                    'balance' => $user->wallet_balance + $amount,
                    'trx_ref_id' => $request->reference,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert transactions
            DB::table('transactions')->insert($transactionsData);

            // Record bulk action
            DB::table('admin_wallet_actions')->insert([
                'id' => Str::uuid(),
                'admin_id' => auth()->user()->id,
                'user_id' => null,
                'user_type' => $request->user_type,
                'transaction_type' => 'bulk_credit',
                'amount' => $amount,
                'affected_users_count' => count($users),
                'note' => $request->note,
                'reference' => $request->reference,
                'created_at' => now(),
            ]);

            DB::commit();

            Toastr::success("₹{$amount} added to {$users->count()} {$request->user_type} wallets successfully!");
            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Bulk wallet operation failed: ' . $e->getMessage());
            Toastr::error('Bulk operation failed: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Get wallet transaction history for a user
     */
    public function transactionHistory(string $userId)
    {
        $user = DB::table('users')->where('id', $userId)->first();
        
        if (!$user) {
            Toastr::error('User not found');
            return redirect()->back();
        }

        $transactions = DB::table('transactions')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $account = DB::table('user_accounts')->where('user_id', $userId)->first();

        return view('usermanagement::admin.wallet.history', compact('user', 'transactions', 'account'));
    }

    /**
     * View admin wallet actions audit log
     */
    public function auditLog(?Request $request = null)
    {
        // Ensure request is not null
        $request = $request ?? request();
        
        $filter = $request->get('filter', 'all');

        $actions = DB::table('admin_wallet_actions')
            ->join('users as admin', 'admin_wallet_actions.admin_id', '=', 'admin.id')
            ->leftJoin('users as target', 'admin_wallet_actions.user_id', '=', 'target.id')
            ->select(
                'admin_wallet_actions.*',
                'admin.first_name as admin_first_name',
                'admin.last_name as admin_last_name',
                'target.first_name as target_first_name',
                'target.last_name as target_last_name',
                'target.phone as target_phone'
            )
            ->when($filter !== 'all', function ($query) use ($filter) {
                $query->where('admin_wallet_actions.transaction_type', $filter);
            })
            ->orderBy('admin_wallet_actions.created_at', 'desc')
            ->paginate(50);

        return view('usermanagement::admin.wallet.audit-log', compact('actions', 'filter'));
    }

    /**
     * Send notification when admin adds money
     */
    private function sendWalletNotification($user, string $type, float $amount): void
    {
        if (!$user->fcm_token) {
            return;
        }

        $title = $type === 'credit' ? 'Wallet Credited' : 'Wallet Debited';
        $description = $type === 'credit' 
            ? "₹{$amount} has been added to your wallet by admin"
            : "₹{$amount} has been deducted from your wallet by admin";

        sendDeviceNotification(
            fcm_token: $user->fcm_token,
            title: $title,
            description: $description,
            status: 'wallet_update',
            type: 'wallet_transaction',
            action: 'view_wallet',
            user_id: $user->id,
        );
    }

    /**
     * Show Razorpay payment form for wallet top-up
     */
    public function showPaymentForm(string $userId): View
    {
        $user = DB::table('users')->where('id', $userId)->first();
        
        if (!$user) {
            Toastr::error('User not found');
            return redirect()->back();
        }

        $account = DB::table('user_accounts')->where('user_id', $userId)->first();

        return view('usermanagement::admin.wallet.payment-form', compact('user', 'account'));
    }

    /**
     * Create Razorpay order for wallet top-up
     */
    public function createPaymentOrder(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'amount' => 'required|numeric|min:10|max:50000',
            'payment_method' => 'required|in:upi,netbanking,card,wallet',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = DB::table('users')->where('id', $request->user_id)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $paymentService = new AdminWalletPaymentService();
        
        $result = $paymentService->createWalletTopUpOrder([
            'admin_id' => auth()->user()->id,
            'user_id' => $request->user_id,
            'user_type' => $user->user_type,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'notes' => $request->notes,
        ]);

        return response()->json($result);
    }

    /**
     * Verify Razorpay payment and credit wallet
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $paymentService = new AdminWalletPaymentService();
        
        $result = $paymentService->verifyAndProcessPayment([
            'razorpay_order_id' => $request->razorpay_order_id,
            'razorpay_payment_id' => $request->razorpay_payment_id,
            'razorpay_signature' => $request->razorpay_signature,
        ]);

        if ($result['success']) {
            // Send notification to user
            $user = DB::table('users')->where('id', $result['user_id'])->first();
            $this->sendWalletNotification($user, 'credit', $result['amount']);
        }

        return response()->json($result);
    }

    /**
     * Handle payment failure
     */
    public function paymentFailed(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $paymentService = new AdminWalletPaymentService();
        
        $result = $paymentService->handlePaymentFailure(
            $request->razorpay_order_id,
            $request->reason ?? 'Payment cancelled by user'
        );

        return response()->json($result);
    }

    /**
     * View payment history
     */
    public function paymentHistory(Request $request): View
    {
        // Ensure request is not null
        $request = $request ?? request();
        
        $status = $request->get('status', 'all');
        $userId = $request->get('user_id');

        $payments = DB::table('admin_wallet_payment_orders')
            ->join('users as admin', 'admin_wallet_payment_orders.admin_id', '=', 'admin.id')
            ->join('users as target', 'admin_wallet_payment_orders.user_id', '=', 'target.id')
            ->select(
                'admin_wallet_payment_orders.*',
                DB::raw("CONCAT(admin.first_name, ' ', admin.last_name) as admin_name"),
                DB::raw("CONCAT(target.first_name, ' ', target.last_name) as target_name"),
                'target.phone as target_phone',
                'target.user_type as target_user_type'
            )
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('admin_wallet_payment_orders.status', $status);
            })
            ->when($userId, function ($query) use ($userId) {
                $query->where('admin_wallet_payment_orders.user_id', $userId);
            })
            ->orderBy('admin_wallet_payment_orders.created_at', 'desc')
            ->paginate(20);

        return view('usermanagement::admin.wallet.payment-history', compact('payments', 'status'));
    }
}

