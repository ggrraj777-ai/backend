<?php

namespace Modules\TransactionManagement\Http\Controllers\Web\New\Admin\Transaction;

use App\Http\Controllers\BaseController;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\TransactionManagement\Entities\Transaction;
use Modules\TransactionManagement\Service\Interface\TransactionServiceInterface;

class TransactionController extends BaseController
{
    use AuthorizesRequests;

    protected $transactionService;

    public function __construct(TransactionServiceInterface $transactionService)
    {
        parent::__construct($transactionService);
        $this->transactionService = $transactionService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('transaction_view');

        $filters = [
            'search' => $request?->get('search'),
            'account' => $request?->get('account', 'all'),
            'transaction_type' => $request?->get('transaction_type', 'all'),
            'date_range' => $request?->get('date_range', 'last_30_days'),
            'start_date' => $request?->get('start_date'),
            'end_date' => $request?->get('end_date'),
        ];

        $criteria = [
            'search' => $filters['search'],
            'date_range' => $filters['date_range'],
            'start_date' => $filters['start_date'],
            'end_date' => $filters['end_date'],
        ];

        if (!empty($filters['account']) && $filters['account'] !== 'all') {
            $criteria['account'] = $filters['account'];
        }

        if (!empty($filters['transaction_type']) && $filters['transaction_type'] !== 'all') {
            $criteria['transaction_type'] = $filters['transaction_type'];
        }

        $transactions = $this->transactionService->index(
            criteria: $criteria,
            relations: [
                'user' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'phone');
                },
            ],
            orderBy: ['created_at' => 'desc'],
            limit: paginationLimit(),
            offset: $request['page'] ?? 1
        );

        $baseQuery = Transaction::query();
        $this->applyFiltersToBuilder($baseQuery, $filters);

        $overallSummary = (object)[
            'total' => 0,
            'total_credit' => 0,
            'total_debit' => 0,
        ];

        $todaySummary = (object)[
            'total_credit' => 0,
            'total_debit' => 0,
        ];

        $monthSummary = (object)[
            'total_credit' => 0,
            'total_debit' => 0,
        ];

        try {
            $startOfToday = Carbon::today()->startOfDay();
            $endOfToday = Carbon::today()->endOfDay();
            $startOfMonth = Carbon::now()->startOfMonth()->startOfDay();
            $endOfMonth = Carbon::now()->endOfMonth()->endOfDay();

            $aggregates = (clone $baseQuery)
                ->selectRaw('
                    COUNT(*) as overall_total,
                    COALESCE(SUM(credit), 0) as overall_credit,
                    COALESCE(SUM(debit), 0) as overall_debit,
                    COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN credit ELSE 0 END), 0) as today_credit,
                    COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN debit ELSE 0 END), 0) as today_debit,
                    COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN credit ELSE 0 END), 0) as month_credit,
                    COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN debit ELSE 0 END), 0) as month_debit
                ', [
                    $startOfToday, $endOfToday,
                    $startOfToday, $endOfToday,
                    $startOfMonth, $endOfMonth,
                    $startOfMonth, $endOfMonth,
                ])
                ->first();

            $overallSummary->total = $aggregates?->overall_total ?? 0;
            $overallSummary->total_credit = $aggregates?->overall_credit ?? 0;
            $overallSummary->total_debit = $aggregates?->overall_debit ?? 0;

            $todaySummary->total_credit = $aggregates?->today_credit ?? 0;
            $todaySummary->total_debit = $aggregates?->today_debit ?? 0;

            $monthSummary->total_credit = $aggregates?->month_credit ?? 0;
            $monthSummary->total_debit = $aggregates?->month_debit ?? 0;
        } catch (\Throwable $exception) {
            \Log::warning('Transaction summary aggregation failed', [
                'error' => $exception->getMessage(),
            ]);
        }

        $accountOptions = Transaction::query()
            ->select('account')
            ->whereNotNull('account')
            ->distinct()
            ->orderBy('account')
            ->pluck('account');

        $transactionTypeOptions = Transaction::query()
            ->select('transaction_type')
            ->whereNotNull('transaction_type')
            ->distinct()
            ->orderBy('transaction_type')
            ->pluck('transaction_type');

        return view(
            'transactionmanagement::admin.transaction.index',
            compact(
                'transactions',
                'overallSummary',
                'todaySummary',
                'monthSummary',
                'filters',
                'accountOptions',
                'transactionTypeOptions'
            )
        );
    }

    public function export(Request $request)
    {
        $this->authorize('transaction_export');
        $exportData = $this->transactionService->export(criteria: $request->all());
        return exportData($exportData, $request['file'],'');
    }

    private function applyFiltersToBuilder(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $query->where(function ($subQuery) use ($filters) {
                $value = $filters['search'];
                $subQuery->where('id', 'like', "%{$value}%")
                    ->orWhere('trx_ref_id', 'like', "%{$value}%")
                    ->orWhereHas('user', function ($userQuery) use ($value) {
                        $userQuery->where('first_name', 'like', "%{$value}%")
                            ->orWhere('last_name', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%");
                    });
            });
        }

        if (!empty($filters['account']) && $filters['account'] !== 'all') {
            $query->where('account', $filters['account']);
        }

        if (!empty($filters['transaction_type']) && $filters['transaction_type'] !== 'all') {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        $dateRange = $this->resolveDateRangeForFilters($filters);
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }
    }

    private function resolveDateRangeForFilters(array $filters): ?array
    {
        $range = $filters['date_range'] ?? null;

        if ($range && $range !== 'all') {
            return match ($range) {
                'today' => [
                    'start' => Carbon::today()->startOfDay(),
                    'end' => Carbon::today()->endOfDay(),
                ],
                'last_7_days' => [
                    'start' => Carbon::now()->subDays(6)->startOfDay(),
                    'end' => Carbon::now()->endOfDay(),
                ],
                'last_30_days' => [
                    'start' => Carbon::now()->subDays(29)->startOfDay(),
                    'end' => Carbon::now()->endOfDay(),
                ],
                'this_month' => [
                    'start' => Carbon::now()->startOfMonth()->startOfDay(),
                    'end' => Carbon::now()->endOfMonth()->endOfDay(),
                ],
                'custom' => $this->customRange($filters),
                default => null,
            };
        }

        return $this->customRange($filters);
    }

    private function customRange(array $filters): ?array
    {
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            return [
                'start' => Carbon::parse($filters['start_date'])->startOfDay(),
                'end' => Carbon::parse($filters['end_date'])->endOfDay(),
            ];
        }

        return null;
    }
}
