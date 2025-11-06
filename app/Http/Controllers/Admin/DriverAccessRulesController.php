<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverDailyActivity;
use App\Models\DriverFeeConfiguration;
use App\Services\DailyFeeDeductionService;
use App\Services\DriverAccessRulesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverAccessRulesController extends Controller
{
    private DailyFeeDeductionService $feeService;
    private DriverAccessRulesService $accessService;

    public function __construct(
        DailyFeeDeductionService $feeService,
        DriverAccessRulesService $accessService
    ) {
        $this->feeService = $feeService;
        $this->accessService = $accessService;
    }

    /**
     * Show dashboard
     */
    public function dashboard(): View
    {
        $today = today();
        
        // Get today's summary
        $todayActivities = DriverDailyActivity::where('activity_date', $today)
            ->with('driver')
            ->get();

        $stats = [
            'total_drivers' => $todayActivities->count(),
            'free_access' => $todayActivities->where('free_access_achieved', true)->count(),
            'welcome_period' => $todayActivities->where('is_welcome_period', true)->count(),
            'fees_pending' => $todayActivities->where('fee_deducted', false)
                ->where('is_welcome_period', false)
                ->where('free_access_achieved', false)
                ->where('counted_trips', '>', 0)
                ->count(),
            'total_trips' => $todayActivities->sum('counted_trips'),
        ];

        // Get pending deductions
        $pending = $this->feeService->getPendingDeductions($today);

        // Get this month statistics
        $monthStart = $today->copy()->startOfMonth();
        $monthActivities = DriverDailyActivity::whereBetween('activity_date', [$monthStart, $today])->get();
        
        $monthStats = [
            'total_fees' => $monthActivities->sum('fee_amount_deducted'),
            'free_days' => $monthActivities->where('free_access_achieved', true)->count(),
            'paid_days' => $monthActivities->where('fee_deducted', true)->count(),
        ];

        return view('admin.driver-access.dashboard', compact('stats', 'pending', 'monthStats', 'todayActivities'));
    }

    /**
     * Fee configurations management
     */
    public function feeConfigurations(): View
    {
        $configurations = DriverFeeConfiguration::all();
        return view('admin.driver-access.fee-configurations', compact('configurations'));
    }

    /**
     * Update fee configuration
     */
    public function updateConfiguration(Request $request, $id)
    {
        $request->validate([
            'daily_target_trips' => 'required|integer|min:1',
            'daily_fee' => 'required|numeric|min:0',
            'per_trip_fee' => 'required|numeric|min:0',
            'minimum_wallet_balance' => 'required|numeric|min:0',
            'welcome_period_days' => 'required|integer|min:0',
        ]);

        $config = DriverFeeConfiguration::findOrFail($id);
        $config->update($request->only([
            'daily_target_trips',
            'daily_fee',
            'per_trip_fee',
            'minimum_wallet_balance',
            'welcome_period_days',
            'max_allowed_cancellations',
        ]));

        return redirect()->back()->with('success', 'Configuration updated successfully');
    }

    /**
     * Daily activities list
     */
    public function dailyActivities(Request $request): View
    {
        $date = $request->date ? Carbon::parse($request->date) : today();
        
        $activities = DriverDailyActivity::where('activity_date', $date)
            ->with('driver')
            ->orderBy('counted_trips', 'desc')
            ->paginate(50);

        return view('admin.driver-access.daily-activities', compact('activities', 'date'));
    }

    /**
     * Process fees manually
     */
    public function processFeesManually(Request $request)
    {
        $date = $request->date ? Carbon::parse($request->date) : today();
        
        $results = $this->feeService->processAllDrivers($date);

        return redirect()->back()->with('success', 
            "Processed {$results['fees_deducted']} fee deductions. Total: ₹{$results['total_amount_deducted']}"
        );
    }

    /**
     * Driver statistics
     */
    public function driverStatistics(Request $request, $driverId): View
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : today()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : today();

        $stats = $this->accessService->getDriverStatistics($driverId, $startDate, $endDate);
        $driver = \App\Models\User::find($driverId);

        return view('admin.driver-access.driver-statistics', compact('stats', 'driver', 'startDate', 'endDate'));
    }

    /**
     * Export daily activities
     */
    public function exportActivities(Request $request)
    {
        $date = $request->date ? Carbon::parse($request->date) : today();
        
        $activities = DriverDailyActivity::where('activity_date', $date)
            ->with('driver')
            ->get();

        $filename = "driver_activities_{$date->format('Y-m-d')}.csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($activities) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, [
                'Driver ID', 'Driver Name', 'Vehicle Type', 'Trips Completed',
                'Target', 'Free Access', 'Fee Deducted', 'Fee Amount', 'Welcome Period'
            ]);

            // Data rows
            foreach ($activities as $activity) {
                fputcsv($file, [
                    $activity->driver_id,
                    $activity->driver->first_name . ' ' . $activity->driver->last_name,
                    $activity->vehicle_type,
                    $activity->counted_trips,
                    $activity->target_trips,
                    $activity->free_access_achieved ? 'Yes' : 'No',
                    $activity->fee_deducted ? 'Yes' : 'No',
                    $activity->fee_amount_deducted ?? 0,
                    $activity->is_welcome_period ? 'Yes' : 'No',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

