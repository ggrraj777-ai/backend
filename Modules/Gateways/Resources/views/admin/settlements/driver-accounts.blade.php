@extends('adminmodule::layouts.master')

@section('title', translate('Driver Razorpay Accounts'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fs-22">{{ translate('Driver Razorpay Accounts') }}</h2>
                <div class="btn-group">
                    <a href="?linked=all" class="btn btn-sm {{ $linked === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
                        All
                    </a>
                    <a href="?linked=yes" class="btn btn-sm {{ $linked === 'yes' ? 'btn-success' : 'btn-outline-success' }}">
                        Linked
                    </a>
                    <a href="?linked=no" class="btn btn-sm {{ $linked === 'no' ? 'btn-warning' : 'btn-outline-warning' }}">
                        Not Linked
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Driver Accounts for Auto-Split</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Driver</th>
                                    <th>Phone</th>
                                    <th>Account Status</th>
                                    <th>Total Settled</th>
                                    <th>Settlements</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($drivers as $driver)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @if($driver->profile_image)
                                                    <img src="{{ asset('storage/'.$driver->profile_image) }}" 
                                                         class="rounded-circle me-2" 
                                                         width="40" height="40">
                                                @endif
                                                <div>
                                                    <strong>{{ $driver->first_name }} {{ $driver->last_name }}</strong><br>
                                                    <small class="text-muted">#{{ substr($driver->id, 0, 8) }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $driver->phone }}</td>
                                        <td>
                                            @if($driver->verification_status === 'verified')
                                                <span class="badge bg-success">✓ Linked</span>
                                            @elseif($driver->verification_status)
                                                <span class="badge bg-warning">{{ ucfirst($driver->verification_status) }}</span>
                                            @else
                                                <span class="badge bg-secondary">Not Linked</span>
                                            @endif
                                        </td>
                                        <td>₹{{ number_format($driver->total_settled_amount ?? 0, 2) }}</td>
                                        <td>{{ $driver->total_settlements ?? 0 }} trips</td>
                                        <td>
                                            @if($driver->verification_status)
                                                <a href="{{ route('admin.razorpay.driver-account', $driver->id) }}" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> View Details
                                                </a>
                                            @else
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    No Account
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No drivers found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $drivers->links() }}
                </div>
            </div>

            <!-- Info Box -->
            <div class="alert alert-info mt-4">
                <h5>ℹ️ About Razorpay Auto-Split</h5>
                <ul class="mb-0">
                    <li>Drivers must link their bank account or UPI for auto-settlements</li>
                    <li>Payment is automatically split: Driver receives their share instantly</li>
                    <li>Platform keeps commission, fees, and insurance</li>
                    <li>No manual transfer needed - fully automated</li>
                    <li>Settlement happens in real-time during payment</li>
                </ul>
            </div>
        </div>
    </div>
@endsection

