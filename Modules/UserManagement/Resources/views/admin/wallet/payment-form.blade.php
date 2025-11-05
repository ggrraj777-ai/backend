@extends('adminmodule::layouts.master')

@section('title', 'Add Money via Razorpay - ' . $user->first_name . ' ' . $user->last_name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Add Money to Wallet</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.wallet.index') }}">Wallets</a></li>
                        <li class="breadcrumb-item active">Add Money</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-wallet2"></i> Add Money via Razorpay
                    </h5>
                </div>
                <div class="card-body">
                    <!-- User Info -->
                    <div class="alert alert-info mb-4">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-person-circle fs-2"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">{{ $user->first_name }} {{ $user->last_name }}</h6>
                                <p class="mb-0">
                                    <small>
                                        Phone: {{ $user->phone }} | 
                                        Type: <span class="badge bg-primary">{{ ucfirst($user->user_type) }}</span> |
                                        Current Balance: <strong>₹{{ number_format($account->wallet_balance ?? 0, 2) }}</strong>
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Form -->
                    <form id="payment-form">
                        <input type="hidden" name="user_id" value="{{ $user->id }}">

                        <!-- Amount -->
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount to Add (₹) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-lg" id="amount" name="amount" 
                                   min="10" max="50000" step="0.01" required
                                   placeholder="Enter amount (Min: ₹10, Max: ₹50,000)">
                            <small class="text-muted">Minimum: ₹10 | Maximum: ₹50,000</small>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="form-check form-check-card">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="upi" value="upi" checked>
                                        <label class="form-check-label" for="upi">
                                            <div class="text-center p-3 border rounded">
                                                <i class="bi bi-phone fs-2 text-success"></i>
                                                <div class="mt-2">UPI</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check form-check-card">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="netbanking" value="netbanking">
                                        <label class="form-check-label" for="netbanking">
                                            <div class="text-center p-3 border rounded">
                                                <i class="bi bi-bank fs-2 text-primary"></i>
                                                <div class="mt-2">Net Banking</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check form-check-card">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="card" value="card">
                                        <label class="form-check-label" for="card">
                                            <div class="text-center p-3 border rounded">
                                                <i class="bi bi-credit-card fs-2 text-info"></i>
                                                <div class="mt-2">Card</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check form-check-card">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="wallet" value="wallet">
                                        <label class="form-check-label" for="wallet">
                                            <div class="text-center p-3 border rounded">
                                                <i class="bi bi-wallet2 fs-2 text-warning"></i>
                                                <div class="mt-2">Wallet</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-4">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Add any notes or remarks..."></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-lg btn-success" id="pay-button">
                                <i class="bi bi-shield-check"></i> Pay Securely with Razorpay
                            </button>
                            <a href="{{ route('admin.wallet.index') }}" class="btn btn-lg btn-light">
                                <i class="bi bi-arrow-left"></i> Back to Wallets
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Razorpay Script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
    const RAZORPAY_KEY = "{{ env('RAZORPAY_KEY_ID') }}";
    const CREATE_ORDER_URL = "{{ route('admin.wallet.create-payment-order') }}";
    const VERIFY_PAYMENT_URL = "{{ route('admin.wallet.verify-payment') }}";
    const PAYMENT_FAILED_URL = "{{ route('admin.wallet.payment-failed') }}";
    const REDIRECT_URL = "{{ route('admin.wallet.index') }}";

    document.getElementById('payment-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const amount = document.getElementById('amount').value;
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
        const notes = document.getElementById('notes').value;
        const userId = document.querySelector('input[name="user_id"]').value;

        // Validate amount
        if (amount < 10 || amount > 50000) {
            toastr.error('Amount must be between ₹10 and ₹50,000');
            return;
        }

        // Disable button
        const payButton = document.getElementById('pay-button');
        payButton.disabled = true;
        payButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';

        try {
            // Create order
            const createResponse = await fetch(CREATE_ORDER_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    user_id: userId,
                    amount: amount,
                    payment_method: paymentMethod,
                    notes: notes
                })
            });

            const orderData = await createResponse.json();

            if (!orderData.success) {
                throw new Error(orderData.message || 'Failed to create order');
            }

            // Razorpay options
            const options = {
                key: RAZORPAY_KEY,
                amount: orderData.amount * 100,
                currency: 'INR',
                name: 'GAUVA Platform',
                description: 'Wallet Top-up',
                order_id: orderData.razorpay_order_id,
                prefill: {
                    name: "{{ $user->first_name }} {{ $user->last_name }}",
                    email: "{{ $user->email ?? 'admin@gauva.com' }}",
                    contact: "{{ $user->phone }}"
                },
                notes: {
                    purpose: 'Admin Wallet Top-up'
                },
                theme: {
                    color: '#3399cc'
                },
                handler: async function (response) {
                    // Payment successful, verify
                    try {
                        const verifyResponse = await fetch(VERIFY_PAYMENT_URL, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature
                            })
                        });

                        const verifyData = await verifyResponse.json();

                        if (verifyData.success) {
                            toastr.success('Payment successful! Wallet credited.');
                            setTimeout(() => {
                                window.location.href = REDIRECT_URL;
                            }, 2000);
                        } else {
                            throw new Error(verifyData.message || 'Payment verification failed');
                        }
                    } catch (error) {
                        toastr.error('Payment verification failed: ' + error.message);
                        payButton.disabled = false;
                        payButton.innerHTML = '<i class="bi bi-shield-check"></i> Pay Securely with Razorpay';
                    }
                },
                modal: {
                    ondismiss: async function() {
                        // Payment cancelled
                        await fetch(PAYMENT_FAILED_URL, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                razorpay_order_id: orderData.razorpay_order_id,
                                reason: 'Payment cancelled by user'
                            })
                        });

                        toastr.warning('Payment cancelled');
                        payButton.disabled = false;
                        payButton.innerHTML = '<i class="bi bi-shield-check"></i> Pay Securely with Razorpay';
                    }
                }
            };

            // Open Razorpay
            const razorpay = new Razorpay(options);
            razorpay.open();

        } catch (error) {
            toastr.error(error.message || 'An error occurred');
            payButton.disabled = false;
            payButton.innerHTML = '<i class="bi bi-shield-check"></i> Pay Securely with Razorpay';
        }
    });
</script>
@endsection

