@extends('adminmodule::layouts.master')

@section('title', 'Razorpay Payment History')

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header mb-3">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-credit-card"></i> Razorpay Payment History
                </h1>
                <p class="page-header-text">View all wallet top-up payments via Razorpay</p>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.wallet.index') }}" class="btn btn-outline-primary">
                    <i class="tio-arrow-backward"></i> Back to Wallets
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Payment Status</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="all" {{ $status == 'all' ? 'selected' : '' }}>All Payments</option>
                        <option value="completed" {{ $status == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="failed" {{ $status == 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="cancelled" {{ $status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">User ID (Optional)</label>
                    <input type="text" name="user_id" class="form-control" placeholder="Filter by user ID" value="{{ request('user_id') }}">
                </div>
                <div class="col-md-3">
                    @if(request('user_id'))
                    <a href="{{ route('admin.wallet.payment-history') }}" class="btn btn-secondary">
                        <i class="tio-clear"></i> Clear Filter
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Payment History Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="tio-list"></i> Payment Records
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>Order ID</th>
                        <th>Admin</th>
                        <th>Target User</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Razorpay Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    <tr>
                        <td>
                            <code>{{ $payment->order_id }}</code>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm avatar-circle mr-2">
                                    <span class="avatar-initials">{{ substr($payment->admin_name, 0, 1) }}</span>
                                </div>
                                <div>
                                    <strong>{{ $payment->admin_name }}</strong>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong>{{ $payment->target_name }}</strong><br>
                                <small class="text-muted">
                                    {{ $payment->target_phone }} | 
                                    <span class="badge badge-soft-{{ $payment->target_user_type == 'driver' ? 'warning' : 'primary' }}">
                                        {{ ucfirst($payment->target_user_type) }}
                                    </span>
                                </small>
                            </div>
                        </td>
                        <td>
                            <strong class="text-success">₹{{ number_format($payment->amount, 2) }}</strong>
                        </td>
                        <td>
                            <span class="badge badge-soft-info">
                                {{ ucfirst($payment->payment_method_used ?? $payment->payment_method) }}
                            </span>
                        </td>
                        <td>
                            @if($payment->status == 'completed')
                                <span class="badge badge-success">
                                    <i class="tio-checkmark-circle"></i> Completed
                                </span>
                            @elseif($payment->status == 'pending')
                                <span class="badge badge-warning">
                                    <i class="tio-time"></i> Pending
                                </span>
                            @elseif($payment->status == 'failed')
                                <span class="badge badge-danger">
                                    <i class="tio-clear-circle"></i> Failed
                                </span>
                            @else
                                <span class="badge badge-secondary">
                                    <i class="tio-info"></i> {{ ucfirst($payment->status) }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <div>
                                {{ \Carbon\Carbon::parse($payment->created_at)->format('d M Y') }}<br>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($payment->created_at)->format('h:i A') }}</small>
                            </div>
                        </td>
                        <td>
                            @if($payment->razorpay_payment_id)
                                <small>
                                    <strong>Payment ID:</strong><br>
                                    <code class="text-xs">{{ $payment->razorpay_payment_id }}</code>
                                </small>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-center">
                                <img src="{{ asset('assets/admin-module/img/empty-icons/no-data-found.svg') }}" alt="No payments" class="mb-3" style="width: 100px;">
                                <p class="text-muted">No payment records found</p>
                                @if($status != 'all')
                                <a href="{{ route('admin.wallet.payment-history') }}" class="btn btn-sm btn-primary">
                                    View All Payments
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($payments->hasPages())
        <div class="card-footer">
            <div class="row justify-content-center justify-content-sm-between align-items-sm-center">
                <div class="col-sm-auto">
                    <div class="d-flex justify-content-center justify-content-sm-end">
                        {{ $payments->links() }}
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Summary Cards -->
    <div class="row mt-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="media align-items-center">
                        <div class="media-body">
                            <span class="d-block font-size-sm text-muted">Total Completed</span>
                            <span class="h3 mb-0">{{ $payments->where('status', 'completed')->count() }}</span>
                        </div>
                        <div class="ml-2">
                            <span class="icon icon-sm icon-soft-success icon-circle">
                                <i class="tio-checkmark"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="media align-items-center">
                        <div class="media-body">
                            <span class="d-block font-size-sm text-muted">Total Pending</span>
                            <span class="h3 mb-0">{{ $payments->where('status', 'pending')->count() }}</span>
                        </div>
                        <div class="ml-2">
                            <span class="icon icon-sm icon-soft-warning icon-circle">
                                <i class="tio-time"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="media align-items-center">
                        <div class="media-body">
                            <span class="d-block font-size-sm text-muted">Total Failed</span>
                            <span class="h3 mb-0">{{ $payments->where('status', 'failed')->count() }}</span>
                        </div>
                        <div class="ml-2">
                            <span class="icon icon-sm icon-soft-danger icon-circle">
                                <i class="tio-clear"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="media align-items-center">
                        <div class="media-body">
                            <span class="d-block font-size-sm text-muted">Total Amount</span>
                            <span class="h3 mb-0">₹{{ number_format($payments->where('status', 'completed')->sum('amount'), 2) }}</span>
                        </div>
                        <div class="ml-2">
                            <span class="icon icon-sm icon-soft-success icon-circle">
                                <i class="tio-money"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

