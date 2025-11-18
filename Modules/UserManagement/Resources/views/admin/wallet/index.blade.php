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
            <form id="bulkOperationForm" action="{{ route('admin.wallet.bulk-add-money') }}" method="POST">
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
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-3">
                            <label class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="payment_method" value="upi" checked>
                                <span class="form-check-label">UPI</span>
                            </label>
                            <label class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="payment_method" value="netbanking">
                                <span class="form-check-label">Net Banking</span>
                            </label>
                            <label class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="payment_method" value="card">
                                <span class="form-check-label">Card</span>
                            </label>
                            <label class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="payment_method" value="wallet">
                                <span class="form-check-label">Wallet</span>
                            </label>
                        </div>
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

                    <div id="bulkSummary" class="alert alert-info d-none">
                        <i class="bi bi-info-circle"></i>
                        <span class="summary-text"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-white" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bulkSubmitButton">
                        <i class="bi bi-shield-lock"></i> Proceed to Razorpay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('script')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const openWalletModal = (userId, userName, currentBalance, type) => {
    if (type === 'credit') {
        window.location.href = "{{ route('admin.wallet.payment-form', ':userId') }}".replace(':userId', userId);
        return;
    }

    $('#user_id').val(userId);
    $('#transaction_type').val(type);

    $('#walletModalTitle').text('Deduct Money from Wallet');
    $('#submitBtn').removeClass('btn-success').addClass('btn-danger').text('Deduct Money');
    $('#userInfo').removeClass('alert-info').addClass('alert-warning')
        .html(`<strong>${userName}</strong><br>Current Balance: ₹${parseFloat(currentBalance).toFixed(2)}<br><small class="text-danger">⚠ Ensure sufficient balance</small>`);

    $('#walletOperationModal').modal('show');
};

const RAZORPAY_KEY = "{{ env('RAZORPAY_KEY_ID') }}";
const BULK_CREATE_ORDER_URL = "{{ route('admin.wallet.bulk-create-payment-order') }}";
const VERIFY_BULK_PAYMENT_URL = "{{ route('admin.wallet.verify-bulk-payment') }}";
const PAYMENT_FAILED_URL = "{{ route('admin.wallet.payment-failed') }}";

let currentBulkOrder = null;

document.getElementById('bulkOperationForm').addEventListener('submit', async (event) => {
    event.preventDefault();

    const form = event.currentTarget;
    const submitButton = document.getElementById('bulkSubmitButton');
    const summaryBox = document.getElementById('bulkSummary');
    const summaryText = summaryBox.querySelector('.summary-text');

    const formData = new FormData(form);
    const userType = formData.get('user_type');
    const amount = parseFloat(formData.get('amount'));
    const paymentMethod = formData.get('payment_method');
    const reference = formData.get('reference');
    const notes = formData.get('note');

    if (!amount || amount <= 0) {
        toastr.error('Please enter a valid amount greater than 0.');
        return;
    }

    submitButton.disabled = true;
    const originalText = submitButton.innerHTML;
    submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Preparing Razorpay...';

    try {
        const createResponse = await fetch(BULK_CREATE_ORDER_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                user_type: userType,
                amount: amount,
                payment_method: paymentMethod,
                reference: reference,
                notes: notes
            })
        });

        const orderData = await createResponse.json();

        if (!orderData.success) {
            throw new Error(orderData.message || 'Failed to create Razorpay order');
        }

        currentBulkOrder = orderData;

        summaryText.innerHTML = `Will credit <strong>₹${Number(orderData.per_user_amount).toFixed(2)}</strong> to <strong>${orderData.target_users_count}</strong> ${userType === 'all' ? 'users' : userType + 's'} (Total: <strong>₹${Number(orderData.total_amount).toFixed(2)}</strong>)`;
        summaryBox.classList.remove('d-none');

        const razorpayOptions = {
            key: orderData.key_id || RAZORPAY_KEY,
            amount: Math.round(Number(orderData.total_amount) * 100),
            currency: 'INR',
            name: 'GAUVA Platform',
            description: 'Bulk Wallet Top-up',
            order_id: orderData.razorpay_order_id,
            prefill: {
                name: '{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}',
                email: '{{ auth()->user()->email }}',
                contact: '{{ auth()->user()->phone }}'
            },
            notes: {
                admin_id: '{{ auth()->user()->id }}',
                order_reference: orderData.order_id,
                user_type: userType,
                per_user_amount: orderData.per_user_amount
            },
            handler: async (paymentResult) => {
                try {
                    const verifyResponse = await fetch(VERIFY_BULK_PAYMENT_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            razorpay_order_id: paymentResult.razorpay_order_id,
                            razorpay_payment_id: paymentResult.razorpay_payment_id,
                            razorpay_signature: paymentResult.razorpay_signature
                        })
                    });

                    const verifyData = await verifyResponse.json();

                    if (!verifyData.success) {
                        throw new Error(verifyData.message || 'Verification failed');
                    }

                    toastr.success(`Wallet credited for ${verifyData.target_users_count} users.`);
                    $('#bulkOperationModal').modal('hide');
                    setTimeout(() => window.location.reload(), 1500);

                } catch (error) {
                    await fetch(PAYMENT_FAILED_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            razorpay_order_id: paymentResult.razorpay_order_id,
                            reason: error.message || 'Verification failed'
                        })
                    });

                    toastr.error(error.message || 'Bulk payment verification failed');
                } finally {
                    currentBulkOrder = null;
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            },
            modal: {
                ondismiss: async () => {
                    if (currentBulkOrder) {
                        await fetch(PAYMENT_FAILED_URL, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                razorpay_order_id: currentBulkOrder.razorpay_order_id,
                                reason: 'Payment cancelled by admin'
                            })
                        });

                        toastr.info('Bulk wallet payment was cancelled.');
                        currentBulkOrder = null;
                    }

                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            },
            theme: {
                color: '#0d6efd'
            }
        };

        new Razorpay(razorpayOptions).open();

    } catch (error) {
        toastr.error(error.message || 'Failed to initiate bulk payment');
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }
});

$('#bulkOperationModal').on('hidden.bs.modal', () => {
    const summaryBox = document.getElementById('bulkSummary');
    summaryBox.classList.add('d-none');
    summaryBox.querySelector('.summary-text').innerHTML = '';
    document.getElementById('bulkOperationForm').reset();
    const submitButton = document.getElementById('bulkSubmitButton');
    submitButton.disabled = false;
    submitButton.innerHTML = '<i class="bi bi-shield-lock"></i> Proceed to Razorpay';
    currentBulkOrder = null;
});
</script>
@endpush
@endsection

