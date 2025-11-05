@extends('adminmodule::layouts.master')

@section('title', translate('Tiered Fare Configuration'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('GAUVA Tiered KM-Based Fare Configuration') }}</h2>
            
            <div class="alert alert-info">
                <strong>ℹ️ Tiered Pricing Model:</strong> Different per-km rates based on distance bands for fair and competitive pricing.
            </div>

            <div class="row g-4">
                @php
                    $vehicleTypes = [
                        'bike' => ['label' => 'Bike', 'icon' => 'bicycle', 'color' => 'primary'],
                        'auto' => ['label' => 'Auto', 'icon' => 'car-front', 'color' => 'success'],
                        'car' => ['label' => 'Car', 'icon' => 'truck', 'color' => 'warning']
                    ];
                @endphp

                @foreach($vehicleTypes as $type => $info)
                    @php
                        $fare = $tieredFares->get($type);
                    @endphp
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header bg-{{ $info['color'] }} text-white">
                                <h4 class="mb-0">
                                    <i class="bi bi-{{ $info['icon'] }}"></i>
                                    {{ $info['label'] }} Model
                                </h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('admin.tiered.update') }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="vehicle_type" value="{{ $type }}">

                                    <!-- Base Fare -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Base Fare (0-2 km) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" name="base_fare" 
                                                   class="form-control" value="{{ $fare->base_fare ?? 0 }}" required>
                                        </div>
                                        <small class="text-muted">
                                            Default: {{ $type === 'bike' ? '₹25' : ($type === 'auto' ? '₹45' : '₹75') }}
                                        </small>
                                    </div>

                                    <!-- Tier 1: 2-6 km -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Tier 1: 2-6 km (per km) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" name="tier1_per_km" 
                                                   class="form-control" value="{{ $fare->tier1_per_km ?? 0 }}" required>
                                            <span class="input-group-text">/km</span>
                                        </div>
                                        <small class="text-muted">
                                            Default: {{ $type === 'bike' ? '₹8' : ($type === 'auto' ? '₹15' : '₹18') }}/km
                                        </small>
                                    </div>

                                    <!-- Tier 2: 6-8 km -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Tier 2: 6-8 km (per km) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" name="tier2_per_km" 
                                                   class="form-control" value="{{ $fare->tier2_per_km ?? 0 }}" required>
                                            <span class="input-group-text">/km</span>
                                        </div>
                                        <small class="text-muted">
                                            Default: {{ $type === 'bike' ? '₹9' : ($type === 'auto' ? '₹16' : '₹20') }}/km
                                        </small>
                                    </div>

                                    <!-- Tier 3: Above 8 km -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Tier 3: Above 8 km (per km) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" step="0.01" name="tier3_per_km" 
                                                   class="form-control" value="{{ $fare->tier3_per_km ?? 0 }}" required>
                                            <span class="input-group-text">/km</span>
                                        </div>
                                        <small class="text-muted">
                                            Default: {{ $type === 'bike' ? '₹10' : ($type === 'auto' ? '₹18' : '₹22') }}/km
                                        </small>
                                    </div>

                                    <!-- GST Configuration -->
                                    <h6 class="text-{{ $info['color'] }} mt-4 mb-3">GST Configuration</h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">ECO-GST (%)</label>
                                        <input type="number" step="0.01" name="eco_gst_percent" 
                                               class="form-control" value="{{ $fare->eco_gst_percent ?? 5 }}">
                                        <small class="text-muted">Default: 5% (inclusive in customer fare)</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Platform GST (%)</label>
                                        <input type="number" step="0.01" name="platform_gst_percent" 
                                               class="form-control" value="{{ $fare->platform_gst_percent ?? 18 }}">
                                        <small class="text-muted">Default: 18% (on platform income)</small>
                                    </div>

                                    <!-- Example Calculation -->
                                    <div class="alert alert-light border mt-4">
                                        <h6 class="fw-bold mb-2">Example: 5 km trip</h6>
                                        @if($type === 'bike')
                                            <ul class="small mb-0 ps-3">
                                                <li>Base (0-2 km): ₹{{ $fare->base_fare ?? 25 }}</li>
                                                <li>Tier 1 (3 km): ₹{{ ($fare->tier1_per_km ?? 8) * 3 }}</li>
                                                <li><strong>Subtotal: ₹{{ ($fare->base_fare ?? 25) + (($fare->tier1_per_km ?? 8) * 3) }}</strong></li>
                                            </ul>
                                        @elseif($type === 'auto')
                                            <ul class="small mb-0 ps-3">
                                                <li>Base (0-2 km): ₹{{ $fare->base_fare ?? 45 }}</li>
                                                <li>Tier 1 (3 km): ₹{{ ($fare->tier1_per_km ?? 15) * 3 }}</li>
                                                <li><strong>Subtotal: ₹{{ ($fare->base_fare ?? 45) + (($fare->tier1_per_km ?? 15) * 3) }}</strong></li>
                                            </ul>
                                        @else
                                            <ul class="small mb-0 ps-3">
                                                <li>Base (0-2 km): ₹{{ $fare->base_fare ?? 75 }}</li>
                                                <li>Tier 1 (3 km): ₹{{ ($fare->tier1_per_km ?? 18) * 3 }}</li>
                                                <li><strong>Subtotal: ₹{{ ($fare->base_fare ?? 75) + (($fare->tier1_per_km ?? 18) * 3) }}</strong></li>
                                            </ul>
                                        @endif
                                    </div>

                                    <button type="submit" class="btn btn-{{ $info['color'] }} w-100">
                                        <i class="bi bi-save"></i> Update {{ $info['label'] }} Fares
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Detailed Examples Section -->
            <div class="card mt-4">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">📊 Fare Calculation Examples</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach(['bike_5km', 'bike_10km', 'auto_5km', 'auto_12km', 'car_5km', 'car_20km'] as $exampleKey)
                            @if(isset($examples[$exampleKey]))
                                <div class="col-lg-4 col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-header">
                                            <strong>{{ $examples[$exampleKey]['description'] }}</strong>
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-sm table-borderless">
                                                @foreach($examples[$exampleKey]['calculation'] as $label => $value)
                                                    <tr class="{{ str_contains($label, 'pays') || str_contains($label, 'Total') ? 'fw-bold' : '' }}">
                                                        <td>{{ $label }}</td>
                                                        <td class="text-end">{{ $value }}</td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Fare Structure Reference -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">📋 GAUVA Fare Structure Reference</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5 class="text-primary">🏍️ Bike Tiered Rates</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Distance</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>0-2 km</td><td>₹25 (Base)</td></tr>
                                    <tr><td>2-6 km</td><td>₹8/km</td></tr>
                                    <tr><td>6-8 km</td><td>₹9/km</td></tr>
                                    <tr><td>Above 8 km</td><td>₹10/km</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-success">🚕 Auto Tiered Rates</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Distance</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>0-2 km</td><td>₹45 (Base)</td></tr>
                                    <tr><td>2-6 km</td><td>₹15/km</td></tr>
                                    <tr><td>6-8 km</td><td>₹16/km</td></tr>
                                    <tr><td>Above 8 km</td><td>₹18/km</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <h5 class="text-warning">🚗 Car Tiered Rates</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Distance</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>0-2 km</td><td>₹75 (Base)</td></tr>
                                    <tr><td>2-6 km</td><td>₹18/km</td></tr>
                                    <tr><td>6-8 km</td><td>₹20/km</td></tr>
                                    <tr><td>Above 8 km</td><td>₹22/km</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Calculator Tool -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">🧮 Live Fare Calculator</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Vehicle Type</label>
                                <select id="calc_vehicle" class="form-select">
                                    <option value="bike">Bike</option>
                                    <option value="auto">Auto</option>
                                    <option value="car">Car</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Distance (km)</label>
                                <input type="number" step="0.1" id="calc_distance" class="form-control" value="5">
                            </div>
                            <button type="button" class="btn btn-primary" onclick="calculateFare()">
                                Calculate Fare
                            </button>
                        </div>
                        <div class="col-md-6">
                            <div id="calc_result" class="alert alert-secondary">
                                <strong>Result will appear here</strong>
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
        
        function calculateFare() {
            const vehicle = document.getElementById('calc_vehicle').value;
            const distance = parseFloat(document.getElementById('calc_distance').value);
            
            if (!distance || distance <= 0) {
                alert('Please enter a valid distance');
                return;
            }

            fetch('{{ route("admin.tiered.preview") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    vehicle_type: vehicle,
                    distance: distance
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayResult(data.breakdown);
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to calculate fare');
            });
        }

        function displayResult(breakdown) {
            let html = '<div class="fare-breakdown">';
            html += '<h5 class="mb-3">Fare Breakdown</h5>';
            html += '<table class="table table-sm">';
            
            breakdown.tier_breakdown.forEach(tier => {
                html += `<tr>
                    <td>${tier.range}</td>
                    <td>${tier.rate}</td>
                    <td>${tier.distance} km</td>
                    <td class="text-end fw-bold">₹${tier.amount}</td>
                </tr>`;
            });
            
            html += `<tr class="table-primary">
                <td colspan="3"><strong>Subtotal (before charges)</strong></td>
                <td class="text-end"><strong>₹${breakdown.subtotal_before_charges}</strong></td>
            </tr>`;
            html += '</table></div>';
            
            document.getElementById('calc_result').innerHTML = html;
        }

        // Form validation
        $('form').on('submit', function(e) {
            const vehicleType = $(this).find('input[name="vehicle_type"]').val();
            const baseFare = $(this).find('input[name="base_fare"]').val();
            
            if (!confirm(`Update ${vehicleType} tiered fares with base fare ₹${baseFare}?`)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
@endpush

