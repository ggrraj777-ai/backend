@extends('adminmodule::layouts.master')

@section('title', 'Wallet Management')

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header mb-3">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-wallet"></i> Wallet Management
                </h1>
                <p class="page-header-text">
                    Add money via Razorpay (UPI/NetBanking/Card) or deduct money from wallets
                    <span class="badge badge-soft-success ml-2">
                        <i class="tio-checkmark-circle"></i> Razorpay Integrated
                    </span>
                </p>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.wallet.payment-history') }}" class="btn btn-success">
                    <i class="tio-credit-card"></i> Payment History
                </a>
                <a href="{{ route('admin.wallet.audit-log') }}" class="btn btn-outline-info">
                    <i class="tio-history"></i> Audit Log
                </a>
            </div>
        </div>
    </div>

    <!-- User Type Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label">User Type</label>
                    <select name="user_type" class="form-control" onchange="this.form.submit()">
                        <option value="customer" {{ request('user_type', 'customer') == 'customer' ? 'selected' : '' }}>Customers</option>
                        <option value="driver" {{ request('user_type') == 'driver' ? 'selected' : '' }}>Drivers</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, phone, email..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-primary"><i class="tio-search"></i> Search</button>
                    </div>
                </div>
                <div class="col-md-3">
                    @if(request('search'))
                    <a href="{{ route('admin.wallet.index', ['user_type' => request('user_type', 'customer')]) }}" class="btn btn-secondary">
                        <i class="tio-clear"></i> Clear
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Users List -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">{{ ucfirst($userType) }} Wallets ({{ $users->total() }})</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>{{ ucfirst($userType) }}</th>
                        <th>Phone</th>
                        <th>Wallet Balance</th>
                        <th>Receivable</th>
                        <th>Payable</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="media align-items-center">
                                <div class="avatar avatar-circle mr-3">
                                    @if($user->profile_image)
                                    <img class="avatar-img" src="{{ $user->profile_image }}" alt="{{ $user->first_name }}">
                                    @else
                                    <span class="avatar-initials">{{ substr($user->first_name, 0, 1) }}</span>
                                    @endif
                                </div>
                                <div class="media-body">
                                    <h5 class="mb-0">{{ $user->first_name }} {{ $user->last_name }}</h5>
                                    <span class="text-muted">{{ $user->email ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user->phone }}</td>
                        <td>
                            <span class="badge badge-soft-success" style="font-size: 14px;">
                                ₹{{ number_format($user->wallet_balance, 2) }}
                            </span>
                        </td>
                        <td>₹{{ number_format($user->receivable_balance, 2) }}</td>
                        <td>₹{{ number_format($user->payable_balance, 2) }}</td>
                        <td>
                            @if($user->is_active)
                            <span class="badge badge-success">Active</span>
                            @else
                            <span class="badge badge-danger">Inactive</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-success" onclick="openWalletModal('{{ $user->id }}', '{{ $user->first_name }} {{ $user->last_name }}', '{{ $user->wallet_balance }}', 'credit')">
                                    <i class="tio-add"></i> Add
                                </button>
                                <button type="button" class="btn btn-sm btn-warning" onclick="openWalletModal('{{ $user->id }}', '{{ $user->first_name }} {{ $user->last_name }}', '{{ $user->wallet_balance }}', 'debit')">
                                    <i class="tio-remove"></i> Deduct
                                </button>
                                <a href="{{ route('admin.wallet.history', $user->id) }}" class="btn btn-sm btn-outline-info">
                                    <i class="tio-history"></i> History
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-center">
                                <i class="tio-users-switch" style="font-size: 48px; color: #ccc;"></i>
                                <p class="mt-3">No {{ $userType }}s found</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $users->links() }}
        </div>
    </div>
</div>

<!-- Wallet Operation Modal -->
<div class="modal fade" id="walletOperationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.wallet.add-money') }}" method="POST" id="walletForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="walletModalTitle">Add Money to Wallet</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="user_id">
                    <input type="hidden" name="transaction_type" id="transaction_type">

                    <div class="alert alert-info" id="userInfo"></div>

                    <div class="form-group">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control form-control-lg" step="0.01" min="1" required placeholder="Enter amount">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference" class="form-control" placeholder="Optional reference number">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Note/Reason</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Optional note for this transaction"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-white" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Operation Modal -->
<div class="modal fade" id="bulkOperationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.wallet.bulk-add-money') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="tio-add"></i> Bulk Add Money</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="tio-info"></i> This will add the specified amount to ALL active users of the selected type.
                    </div>

                    <div class="form-group">
                        <label class="form-label">User Type <span class="text-danger">*</span></label>
                        <select name="user_type" class="form-control" required>
                            <option value="customer">All Customers</option>
                            <option value="driver">All Drivers</option>
                            <option value="all">All Users (Customers + Drivers)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control form-control-lg" step="0.01" min="1" required placeholder="Enter amount per user">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reference Number <span class="text-danger">*</span></label>
                        <input type="text" name="reference" class="form-control" required placeholder="e.g., PROMO-2025-01">
                        <small class="form-text text-muted">Required for bulk operations</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Note/Reason</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="e.g., Festival bonus, Promotional credit"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-white" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Bulk Operation</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('script')
<script>
function openWalletModal(userId, userName, currentBalance, type) {
    // If adding money (credit), redirect to Razorpay payment form
    if (type === 'credit') {
        window.location.href = "{{ route('admin.wallet.payment-form', ':userId') }}".replace(':userId', userId);
        return;
    }
    
    // For debit operations, open modal
    $('#user_id').val(userId);
    $('#transaction_type').val(type);
    
    $('#walletModalTitle').text('Deduct Money from Wallet');
    $('#submitBtn').removeClass('btn-success').addClass('btn-danger').text('Deduct Money');
    $('#userInfo').removeClass('alert-info').addClass('alert-warning')
        .html(`<strong>${userName}</strong><br>Current Balance: ₹${parseFloat(currentBalance).toFixed(2)}<br><small class="text-danger">⚠ Ensure sufficient balance</small>`);
    
    $('#walletOperationModal').modal('show');
}
</script>
@endpush
@endsection

