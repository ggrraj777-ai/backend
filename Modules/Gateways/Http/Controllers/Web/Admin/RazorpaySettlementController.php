<?php

namespace Modules\Gateways\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use App\Services\RazorpayAutoSplitService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RazorpaySettlementController extends BaseController
{
    protected $autoSplitService;

    public function __construct(RazorpayAutoSplitService $autoSplitService)
    {
        $this->autoSplitService = $autoSplitService;
    }

    /**
     * Display settlement overview
     */
    public function index(?Request $request = null, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        // Ensure request is not null
        $request = $request ?? request();
        
        $status = $request->get('status', 'all');
        $period = $request->get('period', 'today');

        // Get date range based on period
        $dateRange = $this->getDateRange($period);

        $settlements = DB::table('razorpay_settlements')
            ->join('users', 'razorpay_settlements.driver_id', '=', 'users.id')
            ->select('razorpay_settlements.*', 'users.first_name', 'users.last_name', 'users.phone')
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('razorpay_settlements.status', $status);
            })
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('razorpay_settlements.created_at', $dateRange);
            })
            ->orderBy('razorpay_settlements.created_at', 'desc')
            ->paginate(20);

        // Statistics
        $stats = [
            'total_settled' => DB::table('razorpay_settlements')
                ->where('status', 'settled')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
                ->sum('driver_share'),
            'total_platform' => DB::table('razorpay_settlements')
                ->where('status', 'settled')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
                ->sum('platform_share'),
            'total_trips' => DB::table('razorpay_settlements')
                ->when($dateRange, fn($q) => $q->whereBetween('created_at', $dateRange))
                ->count(),
            'pending_settlements' => DB::table('razorpay_settlements')
                ->where('status', 'pending')
                ->count(),
        ];

        return view('gateways::admin.settlements.index', compact('settlements', 'stats', 'status', 'period'));
    }

    /**
     * View driver's Razorpay account details
     */
    public function driverAccount(string $driverId): View
    {
        $driver = DB::table('users')->where('id', $driverId)->first();
        
        if (!$driver) {
            Toastr::error('Driver not found');
            return redirect()->back();
        }

        $account = DB::table('driver_razorpay_accounts')
            ->where('driver_id', $driverId)
            ->first();

        $settlements = DB::table('razorpay_settlements')
            ->where('driver_id', $driverId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('gateways::admin.settlements.driver-account', compact('driver', 'account', 'settlements'));
    }

    /**
     * List drivers with/without linked accounts
     */
    public function driverAccounts(Request $request): View
    {
        $linked = $request->get('linked', 'all');

        $drivers = DB::table('users')
            ->leftJoin('driver_razorpay_accounts', 'users.id', '=', 'driver_razorpay_accounts.driver_id')
            ->select('users.*', 'driver_razorpay_accounts.verification_status', 'driver_razorpay_accounts.total_settled_amount')
            ->where('users.user_type', 'driver')
            ->when($linked === 'yes', function ($query) {
                $query->whereNotNull('driver_razorpay_accounts.razorpay_account_id');
            })
            ->when($linked === 'no', function ($query) {
                $query->whereNull('driver_razorpay_accounts.razorpay_account_id');
            })
            ->orderBy('users.created_at', 'desc')
            ->paginate(20);

        return view('gateways::admin.settlements.driver-accounts', compact('drivers', 'linked'));
    }

    /**
     * Get date range helper
     */
    private function getDateRange(string $period): ?array
    {
        return match($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => null,
        };
    }
}

