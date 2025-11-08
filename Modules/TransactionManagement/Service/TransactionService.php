<?php

namespace Modules\TransactionManagement\Service;

use App\Repository\EloquentRepositoryInterface;
use App\Service\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\TransactionManagement\Repository\TransactionRepositoryInterface;
use Modules\TransactionManagement\Service\Interface\TransactionServiceInterface;

class TransactionService extends BaseService implements Interface\TransactionServiceInterface
{
    protected $transactionRepository;

    public function __construct(TransactionRepositoryInterface $transactionRepository)
    {
        parent::__construct($transactionRepository);
        $this->transactionRepository = $transactionRepository;
    }

    public function customerWalletTransaction(array $data, array $relations = [], array $orderBy = [], int $limit = null, int $offset = null): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {

        $whereBetweenCriteria = [];
        if (array_key_exists('data', $data) && $data['data'] != ALL_TIME) {
            if ($data['data'] == 'custom_date') {
                $date['start'] = $data['start'];
                $date['end'] = $data['end'];

            } else {
                $date = $data['data'];
            }
            $date = getDateRange($date);
            $whereBetweenCriteria = [
                'created_at' => [$date['start'], $date['end']],
            ];
        }
        $criteria = [
            'account' => 'wallet_balance',
            'attribute' => 'fund_by_admin'
        ];
        $searchCriteria = [
            'fields' => ['id'],
            'value' => $data['search'] ?? ""
        ];
        if (array_key_exists('user_id', $data) && $data['user_id'] != "all") {
            $criteria = array_merge($criteria, [
                'user_id' => $data['user_id']
            ]);
        }

        return $this->transactionRepository
            ->getBy(
                criteria: $criteria,
                searchCriteria: $searchCriteria,
                whereBetweenCriteria: $whereBetweenCriteria,
                relations: [
                    'user' => function ($query) {
                        $query->select('id', 'first_name', 'last_name', 'phone');
                    },
                ],
                orderBy: $orderBy,
                limit: $limit,
                offset: $offset,
                appends: $data
            );
    }

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        return $this->index(
            criteria: $criteria,
            relations: [
                'user' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'phone');
                },
            ],
            orderBy: ['created_at' => 'desc'],
            limit: $limit,
            offset: $offset
        )->map(function ($item) {
            return [
                'Transaction Id' => $item['id'],
                'Reference' => $item['trx_ref_id'],
                'Type' => ucwords(str_replace("_", ' ', $item['account'])),
                'Transaction Date' => date('d-m-Y h:i A', strtotime($item['created_at'])),
                'Transaction To' => $item->user?->first_name . ' ' . $item->user?->last_name,
                'Credit' => getCurrencyFormat($item['credit']),
                'Debit' => getCurrencyFormat($item['debit']),
                'Balance' => getCurrencyFormat($item['balance']),
            ];
        });
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        if (array_key_exists('customer_id', $criteria)){
            $data['user_id'] = $criteria['customer_id'];
        }
        if (array_key_exists('driver_id', $criteria)){
            $data['user_id']=$criteria['driver_id'];
        }
        if (array_key_exists('status', $criteria) && $criteria['status'] !== 'all') {
            $data['is_active'] = $criteria['status'] == 'active' ? 1 : 0;
        }
        if (!empty($criteria['account']) && $criteria['account'] !== 'all') {
            $data['account'] = $criteria['account'];
        }

        if (!empty($criteria['transaction_type']) && $criteria['transaction_type'] !== 'all') {
            $data['transaction_type'] = $criteria['transaction_type'];
        }

        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['id', 'trx_ref_id'];
            $searchData['relations'] = [
                'user' => ['first_name', 'last_name', 'phone']
            ];
            $searchData['value'] = $criteria['search'];
        }

        $whereInCriteria = [];
        $whereBetweenCriteria = [];

        $dateRange = $this->resolveDateRange($criteria);
        if ($dateRange) {
            $whereBetweenCriteria['created_at'] = [$dateRange['start'], $dateRange['end']];
        }

        $appends = array_filter([
            'search' => $criteria['search'] ?? null,
            'account' => $criteria['account'] ?? null,
            'transaction_type' => $criteria['transaction_type'] ?? null,
            'date_range' => $criteria['date_range'] ?? null,
            'start_date' => $criteria['start_date'] ?? null,
            'end_date' => $criteria['end_date'] ?? null,
        ], function ($value) {
            return !is_null($value) && $value !== '' && $value !== 'all';
        });

        return $this->transactionRepository->getBy(
            criteria: $data,
            searchCriteria: $searchData,
            whereInCriteria: $whereInCriteria,
            whereBetweenCriteria: $whereBetweenCriteria,
            whereHasRelations: $whereHasRelations,
            relations: $relations,
            orderBy: $orderBy,
            limit: $limit,
            offset: $offset,
            withCountQuery: $withCountQuery,
            appends: $appends
        );
    }

    private function resolveDateRange(array $criteria): ?array
    {
        $dateRange = $criteria['date_range'] ?? null;

        if ($dateRange && $dateRange !== 'all') {
            return match ($dateRange) {
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
                'custom' => $this->customRange($criteria),
                default => null,
            };
        }

        return $this->customRange($criteria);
    }

    private function customRange(array $criteria): ?array
    {
        if (!empty($criteria['start_date']) && !empty($criteria['end_date'])) {
            return [
                'start' => Carbon::parse($criteria['start_date'])->startOfDay(),
                'end' => Carbon::parse($criteria['end_date'])->endOfDay(),
            ];
        }

        return null;
    }
}
