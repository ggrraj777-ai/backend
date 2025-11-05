<?php

namespace Modules\FareManagement\Http\Controllers\Web\New\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlatformChargeController extends BaseController
{
    public function __construct()
    {
        // No base service needed for this controller
    }

    /**
     * Display platform charges configuration
     */
    public function index(?Request $request = null, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $platformCharges = DB::table('platform_charges')
            ->where('is_active', true)
            ->get();

        return view('faremanagement::admin.platform.index', compact('platformCharges'));
    }

    /**
     * Update platform charges
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'vehicle_type' => 'required|in:bike,auto,car',
            'per_trip_fee' => 'required|numeric|min:0',
            'daily_fee' => 'required|numeric|min:0',
            'customer_insurance' => 'required|numeric|min:0',
            'driver_insurance' => 'required|numeric|min:0',
            'cashback_percent' => 'nullable|numeric|min:0|max:100',
            'cashback_max_amount' => 'nullable|numeric|min:0',
            'wallet_use_limit' => 'nullable|numeric|min:0',
            'day_pass_fee' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $charge = DB::table('platform_charges')
                ->where('vehicle_type', $request->vehicle_type)
                ->first();

            if ($charge) {
                DB::table('platform_charges')
                    ->where('id', $charge->id)
                    ->update([
                        'per_trip_fee' => $request->per_trip_fee,
                        'daily_fee' => $request->daily_fee,
                        'customer_insurance' => $request->customer_insurance,
                        'driver_insurance' => $request->driver_insurance,
                        'cashback_percent' => $request->cashback_percent ?? 0,
                        'cashback_max_amount' => $request->cashback_max_amount ?? 0,
                        'wallet_use_limit' => $request->wallet_use_limit ?? 0,
                        'day_pass_fee' => $request->day_pass_fee ?? 0,
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();
            Toastr::success('Platform charges updated successfully');
            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error('Failed to update platform charges: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * View driver statistics for bonuses and day passes
     */
    public function statistics(): View
    {
        $today = now()->toDateString();

        // Get driver bonus statistics
        $bonusStats = DB::table('driver_trip_bonuses')
            ->select('driver_id', 'vehicle_type', 'trip_count', 'is_credited')
            ->where('bonus_date', $today)
            ->get();

        // Get day pass statistics
        $dayPassStats = DB::table('driver_day_passes')
            ->select('driver_id', 'vehicle_type', 'pass_amount', 'purchased_at')
            ->where('pass_date', $today)
            ->get();

        // Get cashback statistics
        $cashbackStats = DB::table('customer_cashbacks')
            ->select(DB::raw('SUM(cashback_amount) as total_cashback'), DB::raw('COUNT(*) as total_trips'))
            ->where('is_credited', true)
            ->whereDate('created_at', $today)
            ->first();

        return view('faremanagement::admin.platform.statistics', compact('bonusStats', 'dayPassStats', 'cashbackStats'));
    }
}

