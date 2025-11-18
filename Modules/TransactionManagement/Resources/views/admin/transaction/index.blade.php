@extends('adminmodule::layouts.master')

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('assets/admin-module/css/transaction.css') }}">
@endpush

@section('title', translate('Transaction'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                <div>
                    <h2 class="fs-22 text-capitalize mb-1">{{ translate('transaction_list') }}</h2>
                    <span class="text-muted small">{{ translate('total_transactions') }}: {{ $overallSummary?->total ?? $transactions->total() }}</span>
                </div>
            </div>

            @php
                $overallCredit = $overallSummary->total_credit ?? 0;
                $overallDebit = $overallSummary->total_debit ?? 0;
                $overallNet = $overallCredit - $overallDebit;

                $todayCredit = $todaySummary->total_credit ?? 0;
                $todayDebit = $todaySummary->total_debit ?? 0;
                $todayNet = $todayCredit - $todayDebit;

                $monthCredit = $monthSummary->total_credit ?? 0;
                $monthDebit = $monthSummary->total_debit ?? 0;
                $monthNet = $monthCredit - $monthDebit;
            @endphp

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="transaction-summary-card p-4 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="summary-meta">{{ translate('today') }}</div>
                                <h3 class="summary-title">{{ getCurrencyFormat($todayCredit + $todayDebit) }}</h3>
                                <div class="summary-subtitle">{{ translate('total_volume') }}</div>
                            </div>
                            <span class="summary-icon"><i class="bi bi-lightning-charge-fill"></i></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-end gap-3">
                            <div>
                                <h5 class="mb-0 text-success">{{ getCurrencyFormat($todayCredit) }}</h5>
                                <small class="summary-subtitle text-uppercase">{{ translate('credit') }}</small>
                            </div>
                            <div class="text-end">
                                <h5 class="mb-0 text-danger">{{ getCurrencyFormat($todayDebit) }}</h5>
                                <small class="summary-subtitle text-uppercase">{{ translate('debit') }}</small>
                            </div>
                        </div>
                        <div class="summary-footer">
                            <span class="net-label">{{ translate('net') }}</span>
                            <span class="net-amount {{ $todayNet >= 0 ? 'net-positive' : 'net-negative' }}">{{ getCurrencyFormat($todayNet) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="transaction-summary-card p-4 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="summary-meta">{{ translate('this_month') }}</div>
                                <h3 class="summary-title">{{ getCurrencyFormat($monthCredit + $monthDebit) }}</h3>
                                <div class="summary-subtitle">{{ translate('total_volume') }}</div>
                            </div>
                            <span class="summary-icon bg-soft-secondary"><i class="bi bi-calendar-event"></i></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-end gap-3">
                            <div>
                                <h5 class="mb-0 text-success">{{ getCurrencyFormat($monthCredit) }}</h5>
                                <small class="summary-subtitle text-uppercase">{{ translate('credit') }}</small>
                            </div>
                            <div class="text-end">
                                <h5 class="mb-0 text-danger">{{ getCurrencyFormat($monthDebit) }}</h5>
                                <small class="summary-subtitle text-uppercase">{{ translate('debit') }}</small>
                            </div>
                        </div>
                        <div class="summary-footer">
                            <span class="net-label">{{ translate('net') }}</span>
                            <span class="net-amount {{ $monthNet >= 0 ? 'net-positive' : 'net-negative' }}">{{ getCurrencyFormat($monthNet) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="transaction-summary-card p-4 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="summary-meta">{{ translate('overall') }}</div>
                                <h3 class="summary-title">{{ getCurrencyFormat($overallCredit + $overallDebit) }}</h3>
                                <div class="summary-subtitle">{{ translate('lifetime_volume') }}</div>
                            </div>
                            <span class="summary-icon bg-soft-success"><i class="bi bi-graph-up-arrow"></i></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-end gap-3">
                            <div>
                                <h5 class="mb-0 text-success">{{ getCurrencyFormat($overallCredit) }}</h5>
                                <small class="summary-subtitle text-uppercase">{{ translate('credit') }}</small>
                            </div>
                            <div class="text-end">
                                <h5 class="mb-0 text-danger">{{ getCurrencyFormat($overallDebit) }}</h5>
                                <small class="summary-subtitle text-uppercase">{{ translate('debit') }}</small>
                            </div>
                        </div>
                        <div class="summary-footer">
                            <span class="net-label">{{ translate('net') }}</span>
                            <span class="net-amount {{ $overallNet >= 0 ? 'net-positive' : 'net-negative' }}">{{ getCurrencyFormat($overallNet) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-end transaction-filters">
                        <form action="{{ route('admin.transaction.index') }}" method="GET" class="w-100">
                            <div class="row g-3">
                                <div class="col-12 col-lg-3">
                                    <label class="filter-label" for="search">{{ translate('search') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="search" name="search" id="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ translate('Search_Here_by_Transaction_Id') }}">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-2">
                                    <label class="filter-label" for="account">{{ translate('account') }}</label>
                                    <select name="account" id="account" class="form-select">
                                        <option value="all">{{ translate('all') }}</option>
                                        @foreach($accountOptions as $account)
                                            <option value="{{ $account }}" {{ $filters['account'] === $account ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $account)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-2">
                                    <label class="filter-label" for="transaction_type">{{ translate('transaction_type') }}</label>
                                    <select name="transaction_type" id="transaction_type" class="form-select">
                                        <option value="all">{{ translate('all') }}</option>
                                        @foreach($transactionTypeOptions as $type)
                                            <option value="{{ $type }}" {{ $filters['transaction_type'] === $type ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-2">
                                    <label class="filter-label" for="date_range">{{ translate('date_range') }}</label>
                                    <select name="date_range" id="date_range" class="form-select">
                                        <option value="today" {{ $filters['date_range'] === 'today' ? 'selected' : '' }}>{{ translate('today') }}</option>
                                        <option value="last_7_days" {{ $filters['date_range'] === 'last_7_days' ? 'selected' : '' }}>{{ translate('last_7_days') }}</option>
                                        <option value="last_30_days" {{ $filters['date_range'] === 'last_30_days' ? 'selected' : '' }}>{{ translate('last_30_days') }}</option>
                                        <option value="this_month" {{ $filters['date_range'] === 'this_month' ? 'selected' : '' }}>{{ translate('this_month') }}</option>
                                        <option value="custom" {{ $filters['date_range'] === 'custom' ? 'selected' : '' }}>{{ translate('custom') }}</option>
                                        <option value="all" {{ $filters['date_range'] === 'all' ? 'selected' : '' }}>{{ translate('all_time') }}</option>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-3 d-none" id="custom-date-fields">
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="filter-label" for="start_date">{{ translate('start_date') }}</label>
                                            <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $filters['start_date'] }}">
                                        </div>
                                        <div class="col-6">
                                            <label class="filter-label" for="end_date">{{ translate('end_date') }}</label>
                                            <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $filters['end_date'] }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-12 d-flex flex-wrap gap-2 mt-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-sliders me-1"></i> {{ translate('filter') }}
                                    </button>
                                    <a href="{{ route('admin.transaction.index') }}" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> {{ translate('reset') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                        @can('transaction_export')
                            <div class="flex-shrink-0">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown">
                                        <i class="bi bi-download"></i>
                                        {{ translate('download') }}
                                        <i class="bi bi-caret-down-fill ms-1"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.transaction.export', ['file' => 'excel'] + request()->all()) }}">
                                                {{ translate('excel') }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endcan
                    </div>

                    <div class="table-responsive mt-4">
                        <table class="table table-borderless align-middle table-hover">
                            <thead class="table-light">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('transaction_id') }}</th>
                                <th>{{ translate('reference') }}</th>
                                <th>{{ translate('transaction_date') }}</th>
                                <th>{{ translate('transaction_to') }}</th>
                                <th class="text-center">{{ translate('credit') }}</th>
                                <th class="text-center">{{ translate('debit') }}</th>
                                <th class="text-end">{{ translate('balance') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($transactions as $key => $transaction)
                                @php
                                    $fullName = trim(($transaction?->user?->first_name ?? '') . ' ' . ($transaction?->user?->last_name ?? ''));
                                    $fullName = $fullName !== '' ? $fullName : translate('not_available');
                                @endphp
                                <tr>
                                    <td class="fw-semibold text-muted">{{ $key + $transactions->firstItem() }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="transaction-id" title="{{ $transaction->id }}">{{ \Illuminate\Support\Str::limit($transaction->id, 14) }}</span>
                                            <button type="button" class="btn btn-link btn-sm text-muted copy-btn" data-copy="{{ $transaction->id }}" title="{{ translate('copy') }}"><i class="bi bi-clipboard"></i></button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $transaction->trx_ref_id ?? '-' }}</div>
                                        <small class="text-muted">{{ $transaction->note ?? '' }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ date('d M, Y', strtotime($transaction->created_at)) }}</div>
                                        <small class="text-muted">{{ date('h:i A', strtotime($transaction->created_at)) }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $fullName }}</div>
                                        <div class="d-flex flex-wrap gap-2 small mt-1">
                                            <span class="badge badge-soft-primary">{{ ucwords(str_replace('_', ' ', $transaction->account)) }}</span>
                                            @if($transaction->transaction_type)
                                                <span class="badge badge-soft-secondary">{{ ucwords(str_replace('_', ' ', $transaction->transaction_type)) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center fw-semibold text-success">{{ getCurrencyFormat($transaction->credit) }}</td>
                                    <td class="text-center fw-semibold text-danger">{{ getCurrencyFormat($transaction->debit) }}</td>
                                    <td class="text-end fw-semibold">{{ getCurrencyFormat($transaction->balance) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="d-flex flex-column justify-content-center align-items-center gap-2 empty-state">
                                            <img src="{{ asset('assets/admin-module/img/empty-icons/no-data-found.svg') }}" alt="">
                                            <p class="text-center">{{ translate('no_data_available') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-sm-end gap-3 mt-4">
                        {!! $transactions->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script>
        const dateRangeSelect = document.getElementById('date_range');
        const customDateFields = document.getElementById('custom-date-fields');

        function toggleCustomDates() {
            if (!dateRangeSelect) return;
            const shouldShow = dateRangeSelect.value === 'custom';
            customDateFields.classList.toggle('d-none', !shouldShow);
        }

        toggleCustomDates();
        dateRangeSelect?.addEventListener('change', toggleCustomDates);

        document.querySelectorAll('.copy-btn').forEach(button => {
            button.addEventListener('click', () => {
                const value = button.dataset.copy;
                navigator.clipboard.writeText(value).then(() => {
                    button.classList.add('text-success');
                    setTimeout(() => button.classList.remove('text-success'), 1500);
                });
            });
        });
    </script>
@endpush


