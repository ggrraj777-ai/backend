@extends('adminmodule::layouts.master')

@section('title', 'Wallet Audit Log')

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header mb-3">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-no-gutter">
                        <li class="breadcrumb-item"><a href="{{ route('admin.wallet.index') }}">Wallet Management</a></li>
                        <li class="breadcrumb-item active">Audit Log</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">
                    <i class="tio-shield-outlined"></i> Wallet Operations Audit Log
                </h1>
                <p class="page-header-text">Complete record of all admin wallet operations</p>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Filter by Type</label>
                    <select name="filter" class="form-control" onchange="this.form.submit()">
                        <option value="all" {{ request('filter', 'all') == 'all' ? 'selected' : '' }}>All Operations</option>
                        <option value="credit" {{ request('filter') == 'credit' ? 'selected' : '' }}>Credits Only</option>
                        <option value="debit" {{ request('filter') == 'debit' ? 'selected' : '' }}>Debits Only</option>
                        <option value="bulk_credit" {{ request('filter') == 'bulk_credit' ? 'selected' : '' }}>Bulk Credits</option>
                        <option value="bulk_debit" {{ request('filter') == 'bulk_debit' ? 'selected' : '' }}>Bulk Debits</option>
                    </select>
                </div>
                <div class="col-md-3">
                    @if(request('filter') != 'all')
                    <a href="{{ route('admin.wallet.audit-log') }}" class="btn btn-secondary">
                        <i class="tio-clear"></i> Clear Filter
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Log Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Audit Log ({{ $actions->total() }} records)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-sm">
                <thead class="thead-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Admin</th>
                        <th>Target User</th>
                        <th>User Type</th>
                        <th>Operation</th>
                        <th class="text-right">Amount</th>
                        <th>Reference</th>
                        <th>Note</th>
                        <th>Balance Change</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($actions as $action)
                    <tr>
                        <td>
                            <div>{{ \Carbon\Carbon::parse($action->created_at)->format('d M Y') }}</div>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($action->created_at)->format('h:i A') }}</small>
                        </td>
                        <td>
                            <strong>{{ $action->admin_first_name }} {{ $action->admin_last_name }}</strong>
                        </td>
                        <td>
                            @if($action->user_id)
                                <div>{{ $action->target_first_name }} {{ $action->target_last_name }}</div>
                                <small class="text-muted">{{ $action->target_phone }}</small>
                            @else
                                <span class="badge badge-soft-info">Bulk ({{ $action->affected_users_count }} users)</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-soft-{{ $action->user_type == 'customer' ? 'primary' : ($action->user_type == 'driver' ? 'success' : 'secondary') }}">
                                {{ ucfirst($action->user_type) }}
                            </span>
                        </td>
                        <td>
                            @php
                                $typeMap = [
                                    'credit' => ['label' => 'Credit', 'class' => 'success', 'icon' => 'tio-add'],
                                    'debit' => ['label' => 'Debit', 'class' => 'danger', 'icon' => 'tio-remove'],
                                    'bulk_credit' => ['label' => 'Bulk Credit', 'class' => 'info', 'icon' => 'tio-add-circle'],
                                    'bulk_debit' => ['label' => 'Bulk Debit', 'class' => 'warning', 'icon' => 'tio-remove-circle'],
                                ];
                                $type = $typeMap[$action->transaction_type] ?? ['label' => $action->transaction_type, 'class' => 'secondary', 'icon' => 'tio-info'];
                            @endphp
                            <span class="badge badge-{{ $type['class'] }}">
                                <i class="{{ $type['icon'] }}"></i> {{ $type['label'] }}
                            </span>
                        </td>
                        <td class="text-right">
                            <strong class="text-{{ in_array($action->transaction_type, ['credit', 'bulk_credit']) ? 'success' : 'danger' }}">
                                {{ in_array($action->transaction_type, ['credit', 'bulk_credit']) ? '+' : '-' }}₹{{ number_format($action->amount, 2) }}
                            </strong>
                        </td>
                        <td>
                            @if($action->reference)
                                <code class="small">{{ $action->reference }}</code>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($action->note)
                                <span class="d-inline-block text-truncate" style="max-width: 150px;" data-toggle="tooltip" title="{{ $action->note }}">
                                    {{ $action->note }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($action->balance_before !== null && $action->balance_after !== null)
                                <small>
                                    ₹{{ number_format($action->balance_before, 2) }}
                                    <i class="tio-arrow-forward"></i>
                                    ₹{{ number_format($action->balance_after, 2) }}
                                </small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i class="tio-document-text-outlined" style="font-size: 48px; color: #ccc;"></i>
                            <p class="mt-3">No audit records found</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $actions->links() }}
        </div>
    </div>
</div>

@push('script')
<script>
$(document).ready(function() {
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
@endpush
@endsection

