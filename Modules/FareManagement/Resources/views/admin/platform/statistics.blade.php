@extends('adminmodule::layouts.master')

@section('title', translate('Platform Statistics'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('Platform Statistics - Today') }}</h2>

            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h3 class="mb-2">{{ $bonusStats->where('is_credited', true)->count() }}</h3>
                            <p class="mb-0">Bonuses Credited Today</p>
                            <small>₹{{ $bonusStats->where('is_credited', true)->count() * 50 }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h3 class="mb-2">{{ $dayPassStats->count() }}</h3>
                            <p class="mb-0">Day Passes Sold</p>
                            <small>₹{{ $dayPassStats->sum('pass_amount') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h3 class="mb-2">₹{{ $cashbackStats->total_cashback ?? 0 }}</h3>
                            <p class="mb-0">Total Cashback Given</p>
                            <small>{{ $cashbackStats->total_trips ?? 0 }} bike trips</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h3 class="mb-2">{{ $bonusStats->where('trip_count', '>=', 20)->count() }}</h3>
                            <p class="mb-0">Drivers Eligible for Bonus</p>
                            <small>20+ trips completed</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Driver Bonus Progress -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Auto Driver Bonus Progress (20 Trips = ₹50)</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Driver ID</th>
                                    <th>Trip Count</th>
                                    <th>Progress</th>
                                    <th>Bonus Status</th>
                                    <th>Remaining Trips</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bonusStats->where('vehicle_type', 'auto') as $stat)
                                    <tr>
                                        <td>#{{ $stat->driver_id }}</td>
                                        <td>{{ $stat->trip_count }}</td>
                                        <td>
                                            <div class="progress" style="height: 25px;">
                                                <div class="progress-bar {{ $stat->trip_count >= 20 ? 'bg-success' : 'bg-primary' }}" 
                                                     style="width: {{ min(($stat->trip_count / 20) * 100, 100) }}%">
                                                    {{ round(($stat->trip_count / 20) * 100) }}%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($stat->is_credited)
                                                <span class="badge bg-success">✓ Credited ₹50</span>
                                            @elseif($stat->trip_count >= 20)
                                                <span class="badge bg-warning">Eligible - Pending</span>
                                            @else
                                                <span class="badge bg-secondary">In Progress</span>
                                            @endif
                                        </td>
                                        <td>{{ max(20 - $stat->trip_count, 0) }} trips</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No auto drivers with trips today</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Day Pass Purchases -->
            <div class="card">
                <div class="card-header">
                    <h4>Car Driver Day Passes Purchased</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Driver ID</th>
                                    <th>Vehicle Type</th>
                                    <th>Pass Amount</th>
                                    <th>Purchased At</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dayPassStats as $pass)
                                    <tr>
                                        <td>#{{ $pass->driver_id }}</td>
                                        <td><span class="badge bg-primary">{{ ucfirst($pass->vehicle_type) }}</span></td>
                                        <td>₹{{ $pass->pass_amount }}</td>
                                        <td>{{ \Carbon\Carbon::parse($pass->purchased_at)->format('h:i A') }}</td>
                                        <td><span class="badge bg-success">Active</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No day passes purchased today</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

