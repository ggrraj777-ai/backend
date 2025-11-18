@extends('adminmodule::layouts.master')

@section('title', 'Driver Access Rules Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h2 class="fs-22 text-capitalize">Driver Access Rules Dashboard</h2>
        <div class="d-flex gap-3">
            <a href="{{ route('admin.driver-access.fee-configurations') }}" class="btn btn-primary">
                <i class="bi bi-gear-fill"></i> Fee Configurations
            </a>
            <a href="{{ route('admin.driver-access.daily-activities') }}" class="btn btn-outline-primary">
                <i class="bi bi-list-ul"></i> View All Activities
            </a>
        </div>
    </div>

    <!-- Today's Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-sm-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="card-subtitle">Total Active Drivers</h6>
                        <i class="bi bi-people-fill text-primary fs-20"></i>
                    </div>
                    <h2 class="card-title">{{ $stats['total_drivers'] }}</h2>
                    <small class="text-muted">Today's Activity</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card border-success">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="card-subtitle">Free Access Achieved</h6>
                        <i class="bi bi-trophy-fill text-success fs-20"></i>
                    </div>
                    <h2 class="card-title text-success">{{ $stats['free_access'] }}</h2>
                    <small class="text-muted">Drivers met their target</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="card-subtitle">Welcome Period</h6>
                        <i class="bi bi-gift-fill text-warning fs-20"></i>
                    </div>
                    <h2 class="card-title text-warning">{{ $stats['welcome_period'] }}</h2>
                    <small class="text-muted">First 3 days FREE</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="card-subtitle">Pending Deductions</h6>
                        <i class="bi bi-cash-stack text-danger fs-20"></i>
                    </div>
                    <h2 class="card-title text-danger">{{ $stats['fees_pending'] }}</h2>
                    <small class="text-muted">To be processed EOD</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Month Statistics -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title">This Month Statistics</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center p-3 border rounded">
                        <h4 class="text-success">₹{{ number_format($monthStats['total_fees'], 2) }}</h4>
                        <p class="mb-0 text-muted">Total Fees Collected</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3 border rounded">
                        <h4 class="text-primary">{{ $monthStats['free_days'] }}</h4>
                        <p class="mb-0 text-muted">Free Access Days</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3 border rounded">
                        <h4 class="text-warning">{{ $monthStats['paid_days'] }}</h4>
                        <p class="mb-0 text-muted">Paid Days (Fees Deducted)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Deductions -->
    @if($pending['drivers_count'] > 0)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title">Pending Fee Deductions ({{ $pending['date'] }})</h5>
            <form action="{{ route('admin.driver-access.process-fees') }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="date" value="{{ $pending['date'] }}">
                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Process {{ $pending['drivers_count'] }} fee deductions totaling ₹{{ number_format($pending['total_amount'], 2) }}?')">
                    <i class="bi bi-lightning-fill"></i> Process Now (₹{{ number_format($pending['total_amount'], 2) }})
                </button>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Vehicle</th>
                            <th>Trips (Completed/Target)</th>
                            <th>Fee Amount</th>
                            <th>Wallet Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending['activities'] as $activity)
                        <tr>
                            <td>{{ $activity['driver_name'] }}</td>
                            <td><span class="badge bg-info">{{ strtoupper($activity['vehicle_type']) }}</span></td>
                            <td>{{ $activity['trips'] }}</td>
                            <td><strong>₹{{ number_format($activity['fee'], 2) }}</strong></td>
                            <td>
                                @if($activity['wallet_balance'] >= $activity['fee'])
                                    <span class="text-success">₹{{ number_format($activity['wallet_balance'], 2) }}</span>
                                @else
                                    <span class="text-danger">₹{{ number_format($activity['wallet_balance'], 2) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($activity['wallet_balance'] >= $activity['fee'])
                                    <span class="badge bg-success">Ready</span>
                                @else
                                    <span class="badge bg-danger">Insufficient</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Today's Activities Summary -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title">Today's Driver Activities</h5>
            <a href="{{ route('admin.driver-access.export-activities', ['date' => today()->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Vehicle</th>
                            <th>Days Active</th>
                            <th>Status</th>
                            <th>Trips Progress</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($todayActivities as $activity)
                        <tr>
                            <td>
                                <a href="{{ route('admin.driver-access.driver-statistics', $activity->driver_id) }}">
                                    {{ $activity->driver->first_name ?? 'N/A' }} {{ $activity->driver->last_name ?? '' }}
                                </a>
                            </td>
                            <td><span class="badge bg-secondary">{{ strtoupper($activity->vehicle_type) }}</span></td>
                            <td>
                                @if($activity->is_welcome_period)
                                    <span class="badge bg-warning">Day {{ $activity->days_since_joining }}/3 (Welcome)</span>
                                @else
                                    Day {{ $activity->days_since_joining }}
                                @endif
                            </td>
                            <td>
                                @if($activity->is_welcome_period)
                                    <span class="badge bg-warning">🎁 Welcome</span>
                                @elseif($activity->counted_trips == 0)
                                    <span class="badge bg-secondary">No Activity</span>
                                @elseif($activity->free_access_achieved)
                                    <span class="badge bg-success">✅ Free Access</span>
                                @else
                                    <span class="badge bg-primary">In Progress</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress" style="width: 100px; height: 20px;">
                                        <div class="progress-bar {{ $activity->free_access_achieved ? 'bg-success' : 'bg-primary' }}" 
                                             style="width: {{ ($activity->counted_trips / $activity->target_trips) * 100 }}%">
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-dark">{{ $activity->counted_trips }}/{{ $activity->target_trips }}</span>
                                </div>
                            </td>
                            <td>
                                @if($activity->free_access_achieved)
                                    <span class="text-success"><strong>₹0</strong> (Free)</span>
                                @elseif($activity->is_welcome_period)
                                    <span class="text-warning"><strong>₹0</strong> (Welcome)</span>
                                @elseif($activity->counted_trips == 0)
                                    <span class="text-muted"><strong>₹0</strong> (No trips)</span>
                                @elseif($activity->fee_deducted)
                                    <span class="text-danger"><strong>-₹{{ number_format($activity->fee_amount_deducted, 2) }}</strong></span>
                                @else
                                    <span class="text-warning"><strong>₹{{ number_format($activity->daily_fee, 2) }}</strong> Pending</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No driver activities today
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tagline Section -->
    <div class="card mt-4 bg-primary text-white">
        <div class="card-body text-center py-4">
            <h3 class="mb-3">Every Day is Free Access — If You Earn More!</h3>
            <p class="mb-2">Complete 9 (Bike/Auto) or 10 (Car) Trips to Keep Your Day Free</p>
            <p class="mb-0"><small>Miss Target → Fee Deducted at Day End | Work Smart, Earn Smart — Grow with GAUVA!</small></p>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    // Auto-refresh every 60 seconds
    setTimeout(function(){
        location.reload();
    }, 60000);
</script>
@endpush

