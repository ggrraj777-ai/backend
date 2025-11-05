@extends('adminmodule::layouts.master')

@section('title', translate('Fare Calculation'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">{{ translate('Fare Calculation Settings') }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.business.setup.fare-calculation.store') }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">{{ translate('GST on Ride (%)') }}</label>
                                <input type="number" step="0.01" name="gst_on_ride_percent" class="form-control" value="{{ $values['gst_on_ride_percent'] }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ translate('Platform Fee Type') }}</label>
                                <select name="platform_fee_type" class="form-select">
                                    <option value="flat" {{ $values['platform_fee_type']=='flat'?'selected':'' }}>{{ translate('Flat') }}</option>
                                    <option value="percent" {{ $values['platform_fee_type']=='percent'?'selected':'' }}>{{ translate('Percent') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ translate('Platform Fee Value') }}</label>
                                <input type="number" step="0.01" name="platform_fee_value" class="form-control" value="{{ $values['platform_fee_value'] }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ translate('GST on Platform Fee (%)') }}</label>
                                <input type="number" step="0.01" name="gst_on_fee_percent" class="form-control" value="{{ $values['gst_on_fee_percent'] }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ translate('Cashback (%)') }}</label>
                                <input type="number" step="0.01" name="cashback_percent" class="form-control" value="{{ $values['cashback_percent'] }}">
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ translate('Save Settings') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ translate('Preview') }}</h5>
                    <div class="d-flex align-items-center gap-2">
                        <input type="number" step="0.01" id="preview_fare" class="form-control" style="max-width: 180px;" placeholder="{{ translate('Fare amount') }}" value="100">
                        <button class="btn btn-outline-primary" id="btn_preview">{{ translate('Recalculate') }}</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                            <tr><th>{{ translate('Fare value') }}</th><td id="p_fare">0</td></tr>
                            <tr><th>{{ translate('GST (Ride)') }}</th><td id="p_gst_ride">0</td></tr>
                            <tr><th>{{ translate('Platform Fee') }}</th><td id="p_platform_fee">0</td></tr>
                            <tr><th>{{ translate('GST on Platform Fee') }}</th><td id="p_platform_fee_gst">0</td></tr>
                            <tr><th>{{ translate('Cashback') }}</th><td id="p_cashback">0</td></tr>
                            <tr><th>{{ translate('Total paid by customer') }}</th><td id="p_customer_pays">0</td></tr>
                            <tr><th>{{ translate('Driver receives') }}</th><td id="p_driver_receives">0</td></tr>
                            <tr><th>{{ translate('Gauva final balance') }}</th><td id="p_gauva_balance">0</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
(function(){
    function n(v){ return Number(parseFloat(v||0).toFixed(2)); }
    function recalc(){
        const fare = n(document.getElementById('preview_fare').value || 0);
        const gstRide = n((document.querySelector('[name=gst_on_ride_percent]').value||0));
        const pfType = document.querySelector('[name=platform_fee_type]').value;
        const pfVal = n((document.querySelector('[name=platform_fee_value]').value||0));
        const gstFee = n((document.querySelector('[name=gst_on_fee_percent]').value||0));
        const cashbackPct = n((document.querySelector('[name=cashback_percent]').value||0));

        const gstOnRideAmt = n(fare * gstRide / 100);
        const platformFeeBase = pfType === 'percent' ? n(fare * pfVal/100) : pfVal;
        const platformFeeGst = n(platformFeeBase * gstFee / 100);
        const platformFeeTotal = n(platformFeeBase + platformFeeGst);
        const cashback = n(fare * cashbackPct / 100);
        const customerPays = n(fare + gstOnRideAmt + platformFeeTotal);
        const driverReceives = n(fare);
        const gauvaBalance = n(customerPays - (driverReceives + cashback + gstOnRideAmt + platformFeeGst));

        document.getElementById('p_fare').innerText = fare.toFixed(2);
        document.getElementById('p_gst_ride').innerText = gstOnRideAmt.toFixed(2);
        document.getElementById('p_platform_fee').innerText = platformFeeBase.toFixed(2);
        document.getElementById('p_platform_fee_gst').innerText = platformFeeGst.toFixed(2);
        document.getElementById('p_cashback').innerText = cashback.toFixed(2);
        document.getElementById('p_customer_pays').innerText = customerPays.toFixed(2);
        document.getElementById('p_driver_receives').innerText = driverReceives.toFixed(2);
        document.getElementById('p_gauva_balance').innerText = gauvaBalance.toFixed(2);
    }
    document.getElementById('btn_preview').addEventListener('click', function(e){ e.preventDefault(); recalc(); });
    document.querySelectorAll('input,select').forEach(function(el){ el.addEventListener('change', recalc); });
    recalc();
})();
</script>
@endpush
