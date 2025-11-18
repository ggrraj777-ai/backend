@extends('adminmodule::layouts.master')

@section('title', translate('Razorpay Auto-Split Settlements'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4">{{ translate('Razorpay Auto-Split Settlements') }}</h2>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h4>₹{{ number_format($stats['total_settled'], 2) }}</h4>
                            <p class="mb-0">Total Settled to Drivers</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h4>₹{{ number_format($stats['total_platform'], 2) }}</h4>
                            <p class="mb-0">Platform Earnings</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h4>{{ $stats['total_trips'] }}</h4>
                            <p class="mb-0">Total Settlements</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h4>{{ $stats['pending_settlements'] }}</h4>
                            <p class="mb-0">Pending Settlements</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Period</label>
                            <select class="form-select" onchange="window.location.href='?period=' + this.value + '&status={{ $status }}'">
                                <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
                                <option value="week" {{ $period === 'week' ? 'selected' : '' }}>This Week</option>
                                <option value="month" {{ $period === 'month' ? 'selected' : '' }}>This Month</option>
                                <option value="year" {{ $period === 'year' ? 'selected' : '' }}>This Year</option>
                                <option value="all" {{ $period === 'all' ? 'selected' : '' }}>All Time</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" onchange="window.location.href='?status=' + this.value + '&period={{ $period }}'">
                                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                                <option value="settled" {{ $status === 'settled' ? 'selected' : '' }}>Settled</option>
                                <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <a href="{{ route('admin.razorpay.driver-accounts') }}" class="btn btn-primary w-100">
                                <i class="bi bi-people"></i> Manage Driver Accounts
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settlements Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Settlement History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Transfer ID</th>
                                    <th>Driver</th>
                                    <th>Trip ID</th>
                                    <th>Trip Fare</th>
                                    <th>Driver Share</th>
                                    <th>Platform Share</th>
                                    <th>Status</th>
                                    <th>Settled At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($settlements as $settlement)
                                    <tr>
                                        <td><code>{{ substr($settlement->razorpay_transfer_id ?? 'N/A', 0, 20) }}</code></td>
                                        <td>
                                            <div>
                                                <strong>{{ $settlement->first_name }} {{ $settlement->last_name }}</strong><br>
                                                <small class="text-muted">{{ $settlement->phone }}</small>
                                            </div>
                                        </td>
                                        <td><small>#{{ substr($settlement->trip_id, 0, 8) }}</small></td>
                                        <td>₹{{ number_format($settlement->trip_fare, 2) }}</td>
                                        <td class="text-success fw-bold">₹{{ number_format($settlement->driver_share, 2) }}</td>
                                        <td class="text-primary fw-bold">₹{{ number_format($settlement->platform_share, 2) }}</td>
                                        <td>
                                            @if($settlement->status === 'settled')
                                                <span class="badge bg-success">Settled</span>
                                            @elseif($settlement->status === 'pending')
                                                <span class="badge bg-warning">Pending</span>
                                            @elseif($settlement->status === 'failed')
                                                <span class="badge bg-danger">Failed</span>
                                            @else
                                                <span class="badge bg-secondary">{{ ucfirst($settlement->status) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $settlement->settled_at ? \Carbon\Carbon::parse($settlement->settled_at)->format('M d, H:i') : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No settlements found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-3">
                        {{ $settlements->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

