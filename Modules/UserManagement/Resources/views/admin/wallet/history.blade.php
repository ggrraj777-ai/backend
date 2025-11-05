@extends('adminmodule::layouts.master')

@section('title', 'Wallet Transaction History')

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header mb-3">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-no-gutter">
                        <li class="breadcrumb-item"><a href="{{ route('admin.wallet.index') }}">Wallet Management</a></li>
                        <li class="breadcrumb-item active">Transaction History</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">
                    <i class="tio-history"></i> Wallet Transaction History
                </h1>
            </div>
        </div>
    </div>

    <!-- User Info Card -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar avatar-xl avatar-circle mb-3">
                        @if($user->profile_image)
                        <img class="avatar-img" src="{{ $user->profile_image }}" alt="{{ $user->first_name }}">
                        @else
                        <span class="avatar-initials">{{ substr($user->first_name, 0, 1) }}</span>
                        @endif
                    </div>
                    <h4>{{ $user->first_name }} {{ $user->last_name }}</h4>
                    <p class="text-muted">{{ $user->phone }}</p>
                    <span class="badge badge-soft-{{ $user->user_type == 'customer' ? 'primary' : 'success' }}">
                        {{ ucfirst($user->user_type) }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Wallet Balance</h6>
                            <h3 class="text-success">₹{{ number_format($account->wallet_balance, 2) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Receivable</h6>
                            <h3 class="text-info">₹{{ number_format($account->receivable_balance, 2) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Total Withdrawn</h6>
                            <h3 class="text-warning">₹{{ number_format($account->total_withdrawn, 2) }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Transaction History ({{ $transactions->total() }})</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Transaction Type</th>
                        <th>Reference</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($transactions as $transaction)
                    <tr>
                        <td>
                            <div>{{ \Carbon\Carbon::parse($transaction->created_at)->format('d M Y') }}</div>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($transaction->created_at)->format('h:i A') }}</small>
                        </td>
                        <td>
                            @php
                                $attributeLabels = [
                                    'wallet_fund_by_admin' => ['label' => 'Added by Admin', 'class' => 'success'],
                                    'wallet_deduct_by_admin' => ['label' => 'Deducted by Admin', 'class' => 'danger'],
                                    'bulk_wallet_fund_by_admin' => ['label' => 'Bulk Credit by Admin', 'class' => 'info'],
                                    'fund_by_admin' => ['label' => 'Fund by Admin', 'class' => 'success'],
                                    'trip_payment' => ['label' => 'Trip Payment', 'class' => 'primary'],
                                    'trip_refund' => ['label' => 'Trip Refund', 'class' => 'warning'],
                                ];
                                $attr = $attributeLabels[$transaction->attribute] ?? ['label' => ucfirst(str_replace('_', ' ', $transaction->attribute)), 'class' => 'secondary'];
                            @endphp
                            <span class="badge badge-soft-{{ $attr['class'] }}">{{ $attr['label'] }}</span>
                        </td>
                        <td>
                            @if($transaction->trx_ref_id)
                                <code>{{ $transaction->trx_ref_id }}</code>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($transaction->credit > 0)
                                <span class="text-success font-weight-bold">+₹{{ number_format($transaction->credit, 2) }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($transaction->debit > 0)
                                <span class="text-danger font-weight-bold">-₹{{ number_format($transaction->debit, 2) }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <strong>₹{{ number_format($transaction->balance, 2) }}</strong>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="tio-receipt" style="font-size: 48px; color: #ccc;"></i>
                            <p class="mt-3">No transactions found</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $transactions->links() }}
        </div>
    </div>
</div>
@endsection

