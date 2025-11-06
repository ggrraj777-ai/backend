@extends('adminmodule::layouts.master')

@section('title', 'Driver Fee Configurations')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h2 class="fs-22 text-capitalize">Driver Fee Configurations</h2>
        <a href="{{ route('admin.driver-access.dashboard') }}" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Configuration Cards -->
    <div class="row g-4">
        @foreach($configurations as $config)
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-{{$config->vehicle_type == 'bike' ? 'info' : ($config->vehicle_type == 'auto' ? 'warning' : 'primary')}} text-white">
                    <h4 class="card-title mb-0 text-white">
                        <i class="bi bi-{{$config->vehicle_type == 'bike' ? 'bicycle' : ($config->vehicle_type == 'auto' ? 'truck' : 'car-front')}}"></i>
                        {{ strtoupper($config->vehicle_type) }}
                    </h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.driver-access.update-configuration', $config->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Daily Target -->
                        <div class="mb-3">
                            <label class="form-label">Daily Target Trips</label>
                            <input type="number" name="daily_target_trips" class="form-control" 
                                   value="{{ $config->daily_target_trips }}" min="1" required>
                            <small class="text-muted">Number of trips to achieve free access</small>
                        </div>

                        <!-- Daily Fee -->
                        <div class="mb-3">
                            <label class="form-label">Daily Fee (₹)</label>
                            <input type="number" step="0.01" name="daily_fee" class="form-control" 
                                   value="{{ $config->daily_fee }}" min="0" required>
                            <small class="text-muted">Fee if target not met</small>
                        </div>

                        <!-- Per Trip Fee -->
                        <div class="mb-3">
                            <label class="form-label">Per Trip Fee (₹)</label>
                            <input type="number" step="0.01" name="per_trip_fee" class="form-control" 
                                   value="{{ $config->per_trip_fee }}" min="0" required>
                            <small class="text-muted">Fee per individual trip (if applicable)</small>
                        </div>

                        <!-- Minimum Balance -->
                        <div class="mb-3">
                            <label class="form-label">Minimum Wallet Balance (₹)</label>
                            <input type="number" step="0.01" name="minimum_wallet_balance" class="form-control" 
                                   value="{{ $config->minimum_wallet_balance }}" min="0" required>
                            <small class="text-muted">Required to accept trips</small>
                        </div>

                        <!-- Welcome Period -->
                        <div class="mb-3">
                            <label class="form-label">Welcome Period (Days)</label>
                            <input type="number" name="welcome_period_days" class="form-control" 
                                   value="{{ $config->welcome_period_days }}" min="0" required>
                            <small class="text-muted">Free days for new drivers</small>
                        </div>

                        <!-- Max Cancellations -->
                        <div class="mb-3">
                            <label class="form-label">Max Allowed Cancellations</label>
                            <input type="number" name="max_allowed_cancellations" class="form-control" 
                                   value="{{ $config->max_allowed_cancellations }}" min="0" required>
                            <small class="text-muted">Before blocking free access</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Configuration
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <small class="text-muted">
                        <strong>Current Rule:</strong> 
                        @if($config->daily_target_trips == 9)
                        Complete 9 trips = Free access
                        @else
                        Complete {{ $config->daily_target_trips }} trips = Free access
                        @endif
                    </small>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Rules Summary -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title">Fee Deduction Rules</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary">English Version:</h6>
                    <ul class="list-unstyled">
                        <li>✅ <strong>Day 1-3:</strong> Welcome Period - Completely FREE</li>
                        <li>✅ <strong>0 Trips:</strong> No deduction (no activity = no charge)</li>
                        <li>⚠️ <strong>1-8 Trips (Bike/Auto):</strong> Daily fee deducted</li>
                        <li>⚠️ <strong>1-9 Trips (Car):</strong> Daily fee deducted</li>
                        <li>🎉 <strong>9+ Trips (Bike/Auto):</strong> FREE ACCESS</li>
                        <li>🎉 <strong>10+ Trips (Car):</strong> FREE ACCESS</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary">Telugu Version (తెలుగు):</h6>
                    <ul class="list-unstyled">
                        <li>✅ <strong>రోజు 1-3:</strong> స్వాగత కాలం - పూర్తిగా ఉచితం</li>
                        <li>✅ <strong>0 ట్రిప్స్:</strong> డెడక్షన్ లేదు</li>
                        <li>⚠️ <strong>1-8 ట్రిప్స్ (బైక్/ఆటో):</strong> రోజువారీ ఫీ డెడక్ట్ అవుతుంది</li>
                        <li>⚠️ <strong>1-9 ట్రిప్స్ (కార్):</strong> రోజువారీ ఫీ డెడక్ట్ అవుతుంది</li>
                        <li>🎉 <strong>9+ ట్రిప్స్ (బైక్/ఆటో):</strong> ఫ్రీ యాక్సెస్</li>
                        <li>🎉 <strong>10+ ట్రిప్స్ (కార్):</strong> ఫ్రీ యాక్సెస్</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

