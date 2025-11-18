@extends('adminmodule::layouts.master')

@section('title', translate('Platform Charges Management'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('GAUVA Platform Charges Configuration') }}</h2>

            <div class="row g-4">
                @php
                    $vehicleTypes = ['bike' => 'Bike', 'auto' => 'Auto', 'car' => 'Car'];
                    $charges = $platformCharges->keyBy('vehicle_type');
                @endphp

                @foreach($vehicleTypes as $type => $label)
                    @php
                        $charge = $charges->get($type);
                    @endphp
                    <div class="col-lg-4 col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h4 class="mb-0">
                                    <i class="bi bi-{{ $type === 'bike' ? 'bicycle' : ($type === 'auto' ? 'car-front' : 'truck') }}"></i>
                                    {{ $label }} Model
                                </h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('admin.platform.update') }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="vehicle_type" value="{{ $type }}">

                                    <!-- Platform Charges -->
                                    <h5 class="text-primary mb-3">Platform Charges</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Per Trip Fee (₹)</label>
                                        <input type="number" step="0.01" name="per_trip_fee" 
                                               class="form-control" value="{{ $charge->per_trip_fee ?? 0 }}" required>
                                        @if($type === 'bike')
                                            <small class="text-muted">Default: ₹5</small>
                                        @elseif($type === 'auto')
                                            <small class="text-muted">Default: ₹3</small>
                                        @else
                                            <small class="text-muted">Default: ₹11 (or ₹0 with day pass)</small>
                                        @endif
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Daily Fee (₹)</label>
                                        <input type="number" step="0.01" name="daily_fee" 
                                               class="form-control" value="{{ $charge->daily_fee ?? 0 }}" required>
                                        @if($type === 'bike')
                                            <small class="text-muted">Default: ₹7 (after first trip)</small>
                                        @elseif($type === 'auto')
                                            <small class="text-muted">Default: ₹11 (after first trip)</small>
                                        @else
                                            <small class="text-muted">Not applicable for car</small>
                                        @endif
                                    </div>

                                    @if($type === 'car')
                                        <div class="mb-3">
                                            <label class="form-label">Day Pass Fee (₹)</label>
                                            <input type="number" step="0.01" name="day_pass_fee" 
                                                   class="form-control" value="{{ $charge->day_pass_fee ?? 0 }}">
                                            <small class="text-muted">Default: ₹55 (unlimited trips for the day)</small>
                                        </div>
                                    @else
                                        <input type="hidden" name="day_pass_fee" value="0">
                                    @endif

                                    <!-- Insurance -->
                                    <h5 class="text-primary mb-3 mt-4">Insurance Fees</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Customer Insurance (₹)</label>
                                        <input type="number" step="0.01" name="customer_insurance" 
                                               class="form-control" value="{{ $charge->customer_insurance ?? 0 }}" required>
                                        <small class="text-muted">
                                            Default: {{ $type === 'car' ? '₹2' : '₹1' }}
                                        </small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Driver Insurance (₹)</label>
                                        <input type="number" step="0.01" name="driver_insurance" 
                                               class="form-control" value="{{ $charge->driver_insurance ?? 0 }}" required>
                                        <small class="text-muted">
                                            Default: {{ $type === 'car' ? '₹2' : '₹1' }}
                                        </small>
                                    </div>

                                    <!-- Cashback (Bike Only) -->
                                    @if($type === 'bike')
                                        <h5 class="text-primary mb-3 mt-4">Cashback Settings</h5>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Cashback Percentage (%)</label>
                                            <input type="number" step="0.01" name="cashback_percent" 
                                                   class="form-control" value="{{ $charge->cashback_percent ?? 0 }}" max="100">
                                            <small class="text-muted">Default: 10%</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Max Cashback Amount (₹)</label>
                                            <input type="number" step="0.01" name="cashback_max_amount" 
                                                   class="form-control" value="{{ $charge->cashback_max_amount ?? 0 }}">
                                            <small class="text-muted">Default: ₹5</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Wallet Use Limit Per Ride (₹)</label>
                                            <input type="number" step="0.01" name="wallet_use_limit" 
                                                   class="form-control" value="{{ $charge->wallet_use_limit ?? 0 }}">
                                            <small class="text-muted">Default: ₹5</small>
                                        </div>
                                    @else
                                        <input type="hidden" name="cashback_percent" value="0">
                                        <input type="hidden" name="cashback_max_amount" value="0">
                                        <input type="hidden" name="wallet_use_limit" value="0">
                                    @endif

                                    <!-- Summary Box -->
                                    <div class="alert alert-info mt-4">
                                        <h6 class="fw-bold mb-2">Summary:</h6>
                                        <ul class="mb-0 ps-3">
                                            <li>Per Trip Fee: ₹{{ $charge->per_trip_fee ?? 0 }}</li>
                                            @if($charge->daily_fee ?? 0 > 0)
                                                <li>Daily Fee: ₹{{ $charge->daily_fee }} (first trip)</li>
                                            @endif
                                            <li>Insurance: ₹{{ ($charge->customer_insurance ?? 0) + ($charge->driver_insurance ?? 0) }} total</li>
                                            @if($type === 'bike' && ($charge->cashback_percent ?? 0) > 0)
                                                <li>Cashback: {{ $charge->cashback_percent }}% (max ₹{{ $charge->cashback_max_amount }})</li>
                                            @endif
                                            @if($type === 'car' && ($charge->day_pass_fee ?? 0) > 0)
                                                <li>Day Pass: ₹{{ $charge->day_pass_fee }} (unlimited trips)</li>
                                            @endif
                                        </ul>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-save"></i> Update {{ $label }} Charges
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Platform Rules Reference -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h4 class="mb-0">GAUVA Platform Rules Reference</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <h5 class="text-primary">🏍️ Bike Model</h5>
                                    <ul>
                                        <li>Per Trip Fee: ₹5</li>
                                        <li>Daily Fee: ₹7 (after first trip)</li>
                                        <li>Insurance: ₹1 + ₹1 = ₹2</li>
                                        <li>Cashback: 10% max ₹5</li>
                                        <li>Wallet Use: Max ₹5</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="text-primary">🚕 Auto Model</h5>
                                    <ul>
                                        <li>Daily Fee: ₹11 (after first trip)</li>
                                        <li>Per Trip Fee: ₹3</li>
                                        <li>Insurance: ₹1 + ₹1 = ₹2</li>
                                        <li>Bonus: ₹50 after 20 trips/day</li>
                                        <li>No Cashback</li>
                                    </ul>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="text-primary">🚗 Car Model</h5>
                                    <ul>
                                        <li>Per Trip Fee: ₹11</li>
                                        <li>OR Day Pass: ₹55 (unlimited)</li>
                                        <li>Insurance: ₹2 + ₹2 = ₹4</li>
                                        <li>No Cashback</li>
                                        <li>No Daily Fee</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";
        
        // Form validation and confirmation
        $('form').on('submit', function(e) {
            const vehicleType = $(this).find('input[name="vehicle_type"]').val();
            const perTripFee = $(this).find('input[name="per_trip_fee"]').val();
            
            if (!confirm(`Update ${vehicleType} charges with per trip fee ₹${perTripFee}?`)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
@endpush

