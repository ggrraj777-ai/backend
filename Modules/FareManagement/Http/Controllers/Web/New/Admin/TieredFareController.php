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
use Modules\FareManagement\Service\TieredFareCalculator;

class TieredFareController extends BaseController
{
    protected $tieredFareCalculator;

    public function __construct(TieredFareCalculator $tieredFareCalculator)
    {
        $this->tieredFareCalculator = $tieredFareCalculator;
    }

    /**
     * Display tiered fare configuration
     */
    public function index(?Request $request = null, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $tieredFares = DB::table('tiered_fare_config')
            ->where('is_active', true)
            ->whereNull('zone_id') // Global configs
            ->get()
            ->keyBy('vehicle_type');

        // Get example calculations
        $examples = TieredFareCalculator::getExamples();

        return view('faremanagement::admin.tiered.index', compact('tieredFares', 'examples'));
    }

    /**
     * Update tiered fare configuration
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'vehicle_type' => 'required|in:bike,auto,car',
            'base_fare' => 'required|numeric|min:0',
            'tier1_per_km' => 'required|numeric|min:0',
            'tier2_per_km' => 'required|numeric|min:0',
            'tier3_per_km' => 'required|numeric|min:0',
            'eco_gst_percent' => 'nullable|numeric|min:0|max:100',
            'platform_gst_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            $config = DB::table('tiered_fare_config')
                ->where('vehicle_type', $request->vehicle_type)
                ->whereNull('zone_id')
                ->first();

            $data = [
                'base_fare' => $request->base_fare,
                'tier1_per_km' => $request->tier1_per_km,
                'tier2_per_km' => $request->tier2_per_km,
                'tier3_per_km' => $request->tier3_per_km,
                'eco_gst_percent' => $request->eco_gst_percent ?? 5.00,
                'platform_gst_percent' => $request->platform_gst_percent ?? 18.00,
                'updated_at' => now(),
            ];

            if ($config) {
                DB::table('tiered_fare_config')
                    ->where('id', $config->id)
                    ->update($data);
            }

            DB::commit();
            Toastr::success(ucfirst($request->vehicle_type) . ' tiered fare updated successfully');
            return redirect()->back();
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error('Failed to update: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Calculate fare preview
     */
    public function preview(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'vehicle_type' => 'required|in:bike,auto,car',
            'distance' => 'required|numeric|min:0',
        ]);

        try {
            $breakdown = $this->tieredFareCalculator->calculateTieredFare(
                $request->vehicle_type,
                $request->distance
            );

            return response()->json([
                'success' => true,
                'breakdown' => $breakdown,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}

