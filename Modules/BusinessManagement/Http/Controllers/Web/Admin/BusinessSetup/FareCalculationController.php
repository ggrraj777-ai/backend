<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Entities\BusinessSetting;

class FareCalculationController extends Controller
{
    public function index()
    {
        $defaults = [
            'gst_on_ride_percent' => 5,
            'platform_fee_type' => 'flat', // flat|percent
            'platform_fee_value' => 5.76,
            'gst_on_fee_percent' => 18,
            'cashback_percent' => 20,
        ];

        $config = BusinessSetting::query()
            ->where('settings_type', 'fare_calculation')
            ->get()
            ->keyBy('key_name');

        $values = [
            'gst_on_ride_percent' => $config->get('gst_on_ride_percent')->value ?? $defaults['gst_on_ride_percent'],
            'platform_fee_type' => $config->get('platform_fee_type')->value ?? $defaults['platform_fee_type'],
            'platform_fee_value' => $config->get('platform_fee_value')->value ?? $defaults['platform_fee_value'],
            'gst_on_fee_percent' => $config->get('gst_on_fee_percent')->value ?? $defaults['gst_on_fee_percent'],
            'cashback_percent' => $config->get('cashback_percent')->value ?? $defaults['cashback_percent'],
        ];

        return view('businessmanagement::admin.business-setup.fare-calculation', compact('values'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gst_on_ride_percent' => 'required|numeric|min:0',
            'platform_fee_type' => 'required|in:flat,percent',
            'platform_fee_value' => 'required|numeric|min:0',
            'gst_on_fee_percent' => 'required|numeric|min:0',
            'cashback_percent' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                BusinessSetting::updateOrCreate(
                    ['key_name' => $key, 'settings_type' => 'fare_calculation'],
                    ['value' => $value]
                );
            }
        });

        return back()->with('success', 'Fare calculation settings updated successfully.');
    }
}
