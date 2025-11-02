<?php

namespace Modules\TripManagement\Service;

use App\Events\DriverTripCancelledEvent;
use App\Events\DriverTripCompletedEvent;
use App\Events\RideRequestEvent;
use App\Jobs\SendPushNotificationJob;
use App\Service\BaseService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Carbon\Factory;
use Doctrine\DBAL\Schema\View;
use Facade\FlareClient\Http\Response;
use Illuminate\Database\Eloquent\Collection;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Console\Application;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\BusinessManagement\Repository\SettingRepositoryInterface;
use Modules\Gateways\Traits\SmsGateway;
use Modules\PromotionManagement\Service\Interface\CouponSetupServiceInterface;
use Modules\ReviewModule\Repository\ReviewRepositoryInterface;
use Modules\ReviewModule\Service\Interface\ReviewServiceInterface;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Repository\TripRequestRepositoryInterface;
use Modules\TripManagement\Repository\TripRequestTimeRepositoryInterface;
use Modules\TripManagement\Repository\TripStatusRepositoryInterface;
use Modules\TripManagement\Service\Interface\RejectedDriverRequestServiceInterface;
use Modules\TripManagement\Service\Interface\TempTripNotificationServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestFeeServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestTimeServiceInterface;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Interfaces\UserLastLocationInterface;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Lib\LevelUpdateCheckerTrait;
use Modules\UserManagement\Repository\UserRepositoryInterface;
use Modules\UserManagement\Service\Interface\DriverDetailServiceInterface;
use Modules\ZoneManagement\Repository\ZoneRepositoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripRequestService extends BaseService implements TripRequestServiceInterface
{

    use LevelHistoryManagerTrait, SmsGateway, TransactionTrait, LevelUpdateCheckerTrait;

    protected $tripRequestRepository;
    protected $zoneRepository;
    protected $tripStatusRepository;
    protected $reviewRepository;

    protected $tripRequestFeeService;
    protected $tempTripNotificationService;
    protected $userLastLocation;
    protected $driverDetailService;
    protected $couponService;
    protected $reviewService;
    protected $rejectedDriverRequestService;
    protected $userRepository;
    protected $settingRepository;
    protected $tripRequestTimeRepository;

    public function __construct(
        TripRequestRepositoryInterface        $tripRequestRepository,
        ZoneRepositoryInterface               $zoneRepository,
        TripStatusRepositoryInterface         $tripStatusRepository,
        ReviewRepositoryInterface             $reviewRepository,
        TripRequestFeeServiceInterface        $tripRequestFeeService,
        TempTripNotificationServiceInterface  $tempTripNotificationService,
        UserLastLocationInterface             $userLastLocation,
        DriverDetailServiceInterface          $driverDetailService,
        CouponSetupServiceInterface           $couponService,
        ReviewServiceInterface                $reviewService,
        RejectedDriverRequestServiceInterface $rejectedDriverRequestService,
        UserRepositoryInterface               $userRepository,
        SettingRepositoryInterface            $settingRepository,
        TripRequestTimeRepositoryInterface    $tripRequestTimeRepository
    )
    {
        parent::__construct($tripRequestRepository);
        $this->tripRequestRepository = $tripRequestRepository;
        $this->zoneRepository = $zoneRepository;
        $this->tripStatusRepository = $tripStatusRepository;
        $this->reviewRepository = $reviewRepository;
        $this->tripRequestFeeService = $tripRequestFeeService;
        $this->tempTripNotificationService = $tempTripNotificationService;
        $this->userLastLocation = $userLastLocation;
        $this->driverDetailService = $driverDetailService;
        $this->couponService = $couponService;
        $this->reviewService = $reviewService;
        $this->rejectedDriverRequestService = $rejectedDriverRequestService;
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->tripRequestTimeRepository = $tripRequestTimeRepository;
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        if (array_key_exists('customer_id', $criteria)) {
            $data = array_merge($data, [
                'customer_id' => $criteria['customer_id']
            ]);
        }
        if (array_key_exists('driver_id', $criteria)) {
            $data = array_merge($data, [
                'driver_id' => $criteria['driver_id']
            ]);
        }
        if (array_key_exists('type', $criteria)) {
            $data = array_merge($data, [
                'type' => $criteria['type']
            ]);
        }
        if (array_key_exists('current_status', $criteria)) {
            $data = array_merge($data, [
                'current_status' => $criteria['current_status']
            ]);
        }
        if (array_key_exists('payment_status', $criteria)) {
            $data = array_merge($data, [
                'payment_status' => $criteria['payment_status']
            ]);
        }
        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['ref_id'];
            $searchData['relations'] = [
                'customer' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
                'driver' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
            ];
            $searchData['value'] = $criteria['search'];
        }
        $whereInCriteria = [];
        $whereBetweenCriteria = [];
        if (array_key_exists('filter_date', $criteria)) {
            $whereBetweenCriteria = ['created_at' => $criteria['filter_date']];
        }
        return $this->tripRequestRepository->getBy(criteria: $data, searchCriteria: $searchData, whereInCriteria: $whereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery, appends: $appends, groupBy: $groupBy);
    }

    public function getAnalytics($dateRange): mixed
    {
        $monthlyOrder = [];
        $label = [];

        switch ($dateRange) {
            case THIS_WEEK:
                $weekStartDate = now()->startOfWeek();
                for ($i = 1; $i <= 7; $i++) {
                    $monthlyOrder[$i] = $this->tripRequestRepository->calculateCouponAmount(startDate: $weekStartDate, endDate: $weekStartDate);
                    $label[] = $weekStartDate->format('"D"');
                    $weekStartDate = $weekStartDate->addDays(1);
                }
                break;

            case THIS_MONTH:
                $label = [
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . now()->daysInMonth . '"',
                ];

                $start = now()->startOfMonth();
                $end = now()->startOfMonth()->addDays(6);
                $remainingDays = now()->daysInMonth - 28;

                for ($i = 1; $i <= 4; $i++) {
                    $monthlyOrder[$i] = $this->tripRequestRepository->calculateCouponAmount(startDate: $start, endDate: $end);
                    $start = $start->addDays(7);
                    $end = $i == 3 ? $end->addDays(7 + $remainingDays) : $end->addDays(7);
                }
                break;

            case THIS_YEAR:
                $label = [
                    '"Jan"',
                    '"Feb"',
                    '"Mar"',
                    '"Apr"',
                    '"May"',
                    '"Jun"',
                    '"Jul"',
                    '"Aug"',
                    '"Sep"',
                    '"Oct"',
                    '"Nov"',
                    '"Dec"'
                ];

                for ($i = 1; $i <= 12; $i++) {
                    $monthlyOrder[$i - 1] = $this->tripRequestRepository->calculateCouponAmount(month: $i);
                }
                break;

            case TODAY:
                $label = [
                    '"6:00 am"',
                    '"8:00 am"',
                    '"10:00 am"',
                    '"12:00 pm"',
                    '"2:00 pm"',
                    '"4:00 pm"',
                    '"6:00 pm"',
                    '"8:00 pm"',
                    '"10:00 pm"',
                    '"12:00 am"',
                    '"2:00 am"',
                    '"4:00 am"'
                ];

                $startTime = strtotime('6:00 AM');

                for ($i = 0; $i < 12; $i++) {
                    $monthlyOrder[$i] = $this->tripRequestRepository->calculateCouponAmount(startTime: $startTime);
                    $startTime = strtotime('+2 hours', $startTime);
                }
                break;
            default:
                $businessStartDate = Carbon::parse(BUSINESS_START_DATE);
                $today = Carbon::today();
                if ($businessStartDate?->year < $today->year) {
                    for ($i = $businessStartDate?->year; $i <= $today->year; $i++) {
                        $label[] = '"' . $i . '"';
                        $monthlyOrder[] = $this->tripRequestRepository->calculateCouponAmount(year: $i);
                    }
                } else {
                    $label = [
                        '"Jan"',
                        '"Feb"',
                        '"Mar"',
                        '"Apr"',
                        '"May"',
                        '"Jun"',
                        '"Jul"',
                        '"Aug"',
                        '"Sep"',
                        '"Oct"',
                        '"Nov"',
                        '"Dec"'
                    ];

                    for ($i = 1; $i <= 12; $i++) {
                        $monthlyOrder[$i - 1] = $this->tripRequestRepository->calculateCouponAmount(month: $i);
                    }
                }
        }
        return [$label, $monthlyOrder];
    }

    public function couponRuleValidate($coupon, $pickupCoordinates, $vehicleCategoryId): ?array
    {
        $startDate = Carbon::parse($coupon->start_date);
        $endDate = Carbon::parse($coupon->end_date);
        $today = Carbon::now()->startOfDay();

        if ($startDate->gt($today) || $endDate->lt($today)) {
            return response()->json(responseFormatter(constant: DEFAULT_EXPIRED_200), 200); //coupon expire
        }
        if ($coupon->rules == 'area_wise') {
            $pickupCoordinates = json_decode($pickupCoordinates, true);
            $checkArea = $coupon->areas->filter(function ($area) use ($pickupCoordinates) {
                return haversineDistance(
                        latitudeFrom: $area->latitude,
                        longitudeFrom: $area->longitude,
                        latitudeTo: $pickupCoordinates[0],
                        longitudeTo: $pickupCoordinates[1]
                    ) < $area->radius && $area->is_active == 1;
            });
            if ($checkArea->isEmpty()) {
                return COUPON_AREA_NOT_VALID_403;
            }
        } elseif ($coupon->rules == 'vehicle_category_wise') {
            $checkCategory = $coupon->categories->filter(function ($query) use ($vehicleCategoryId) {
                return $query->id == $vehicleCategoryId && $query->is_active == 1;
            });

            if ($checkCategory->isEmpty()) {
                return COUPON_VEHICLE_CATEGORY_NOT_VALID_403;
            }
        }

        return null;
    }

    public function statusWiseTotalTripRecords(array $attributes)
    {
        return $this->tripRequestRepository->statusWiseTotalTripRecords(attributes: $attributes);
    }

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = [])
    {
        return $this->index(criteria: $criteria, relations: $relations, orderBy: $orderBy)->map(function ($item) {
            return [
                'id' => $item['id'],
                'trip_ID' => $item['ref_id'],
                'date' => date('d F Y', strtotime($item['created_at'])) . ' ' . date('h:i a', strtotime($item['created_at'])),
                'customer' => $item['customer']?->first_name . ' ' . $item['customer']?->last_name,
                'driver' => $item['driver'] ? $item['driver']?->first_name . ' ' . $item['driver']?->last_name : 'no driver assigned',
                'trip_cost' => $item['current_status'] == 'completed' ? $item['actual_fare'] : $item['estimated_fare'],
                'coupon_discount' => $item['coupon_amount'],
                'additional_fee' => $item['fee'] ? ($item['fee']->waiting_fee + $item['fee']->delay_fee + $item['fee']->idle_fee + $item['fee']->cancellation_fee + $item['fee']->vat_tax) : 0,
                'total_trip_cost' => $item['paid_fare'] - $item['tips'],
                'admin_commission' => $item['fee'] ? $item['fee']->admin_commission : 0,
                'trip_status' => $item['current_status']
            ];
        });
    }

    public function getAdminZoneWiseStatistics(array $data)
    {

        $whereBetweenCriteria = [];
        if (array_key_exists('date', $data)) {
            $date = getDateRange($data['date']);
            $whereBetweenCriteria = [
                'created_at' => [$date['start'], $date['end']],
            ];
        }
        $zones = $this->zoneRepository->getBy(criteria: ['is_active' => 1]);
        $zoneTripsByDate = $zones->map(function ($zone) use ($whereBetweenCriteria) {
            $completedCriteria = [
                'zone_id' => $zone->id,
                'current_status' => COMPLETED,
            ];
            $cancelledCriteria = [
                'zone_id' => $zone->id,
                'current_status' => CANCELLED,
            ];
            $ongoingCriteria = [
                'zone_id' => $zone->id,
            ];
            $whereInCriteria = [
                'current_status' => [PENDING, ACCEPTED, ONGOING],
            ];
            $completedTrips = $this->tripRequestRepository->getBy(criteria: $completedCriteria, whereBetweenCriteria: $whereBetweenCriteria);
            $cancelledTrips = $this->tripRequestRepository->getBy(criteria: $cancelledCriteria, whereBetweenCriteria: $whereBetweenCriteria);
            $ongoingTrips = $this->tripRequestRepository->getBy(criteria: $ongoingCriteria, whereInCriteria: $whereInCriteria, whereBetweenCriteria: $whereBetweenCriteria);


            return [
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'completed_trips' => $completedTrips->count(),
                'cancelled_trips' => $cancelledTrips->count(),
                'ongoing_trips' => $ongoingTrips->count(),
                'total_trips' => $completedTrips->count() + $cancelledTrips->count() + $ongoingTrips->count(),
            ];
        });
        $totalTrips = $this->tripRequestRepository->getBy(whereInCriteria: ['zone_id' => $zones->pluck('id')], whereBetweenCriteria: $whereBetweenCriteria)->count();

        return [
            'totalTrips' => $totalTrips,
            'zoneTripsByDate' => $zoneTripsByDate,
        ];
    }

    public function getAdminZoneWiseEarning(array $data)
    {
        $criteria = [];
        if (array_key_exists('zone', $data) && $data['zone'] != 'all') {
            $criteria = [
                'zone_id' => $data['zone']
            ];
        }
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        $criteriaForCommission = array_merge($criteria, [
            'payment_status' => PAID
        ]);

        $totalTripRequest = [];
        $totalAdminCommission = [];
        $label = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        switch ($data['date']) {
            case TODAY:
                $label = [
                    '"6:00 am"',
                    '"8:00 am"',
                    '"10:00 am"',
                    '"12:00 pm"',
                    '"2:00 pm"',
                    '"4:00 pm"',
                    '"6:00 pm"',
                    '"8:00 pm"',
                    '"10:00 pm"',
                    '"12:00 am"',
                    '"2:00 am"',
                    '"4:00 am"'
                ];

                $startTime = strtotime('6:00 AM');
                $startDate = Carbon::parse(now())->startOfDay();

                for ($i = 0; $i < 12; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, startTime: $startTime)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, startTime: $startTime)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $startTime = strtotime('+2 hours', $startTime);
                }
                break;
            case PREVIOUS_DAY:
                $label = [
                    '"6:00 am"',
                    '"8:00 am"',
                    '"10:00 am"',
                    '"12:00 pm"',
                    '"2:00 pm"',
                    '"4:00 pm"',
                    '"6:00 pm"',
                    '"8:00 pm"',
                    '"10:00 pm"',
                    '"12:00 am"',
                    '"2:00 am"',
                    '"4:00 am"'
                ];

                $startTime = strtotime('6:00 AM');
                $startDate = Carbon::yesterday()->startOfDay();
                for ($i = 0; $i < 12; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, startTime: $startTime)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, startTime: $startTime)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $startTime = strtotime('+2 hours', $startTime);
                }
                break;

            case THIS_WEEK:
                $weekStartDate = now()->startOfWeek();
                for ($i = 1; $i <= 7; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $weekStartDate, endDate: $weekStartDate)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $weekStartDate, endDate: $weekStartDate)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $label[] = $weekStartDate->format('"D"');
                    $weekStartDate = $weekStartDate->addDays(1);
                }
                break;
            case LAST_7_DAYS:
                $lastStartDate = now()->subDays(7)->startOfDay();
                for ($i = 1; $i <= 7; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $lastStartDate, endDate: $lastStartDate)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $lastStartDate, endDate: $lastStartDate)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $label[] = $lastStartDate->format('"D"');
                    $lastStartDate = $lastStartDate->addDays(1);
                }
                break;

            case THIS_MONTH:
                $label = [
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . now()->daysInMonth . '"',
                ];

                $start = now()->startOfMonth();
                $end = now()->startOfMonth()->addDays(6);
                $remainingDays = now()->daysInMonth - 28;

                for ($i = 1; $i <= 4; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $start = $start->addDays(7);
                    $end = $i == 3 ? $end->addDays(7 + $remainingDays) : $end->addDays(7);
                }
                break;
            case LAST_MONTH:
                $label = [
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . now()->subMonth()->daysInMonth . '"',
                ];

                $start = now()->subMonth()->startOfMonth();
                $end = now()->subMonth()->startOfMonth()->addDays(6);
                $remainingDays = now()->subMonth()->daysInMonth - 28;

                for ($i = 1; $i <= 4; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $start = $start->addDays(7);
                    $end = $i == 3 ? $end->addDays(7 + $remainingDays) : $end->addDays(7);
                }
                break;

            case THIS_YEAR:
                $label = [
                    '"Jan"',
                    '"Feb"',
                    '"Mar"',
                    '"Apr"',
                    '"May"',
                    '"Jun"',
                    '"Jul"',
                    '"Aug"',
                    '"Sep"',
                    '"Oct"',
                    '"Nov"',
                    '"Dec"'
                ];

                for ($i = 1; $i <= 12; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, month: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], month: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                }
                break;
            default:
                $businessStartDate = Carbon::parse(BUSINESS_START_DATE);
                $today = Carbon::today();
                if ($businessStartDate?->year < $today->year) {
                    for ($i = $businessStartDate?->year; $i <= $today->year; $i++) {
                        $label[] = '"' . $i . '"';
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, year: $i)->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], year: $i)->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    }
                } else {
                    $label = [
                        '"Jan"',
                        '"Feb"',
                        '"Mar"',
                        '"Apr"',
                        '"May"',
                        '"Jun"',
                        '"Jul"',
                        '"Aug"',
                        '"Sep"',
                        '"Oct"',
                        '"Nov"',
                        '"Dec"'
                    ];

                    for ($i = 1; $i <= 12; $i++) {
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, month: $i)->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], month: $i)->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    }
                }
        }
        return [
            'label' => $label,
            'totalTripRequest' => $totalTripRequest,
            'totalAdminCommission' => $totalAdminCommission
        ];
    }

    public function getDateZoneWiseEarningStatistics(array $data)
    {
        $zones = $this->zoneRepository->getBy(withTrashed: true);

        $totalTripRequest = [];
        $totalAdminCommission = [];
        $totalTax = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        foreach ($zones as $zone) {
            $criteria = [
                'zone_id' => $zone->id
            ];
            $criteriaForStatistics = [
                'zone_id' => $zone->id,
                'payment_status' => PAID
            ];
            $whereHasRelations = [];

            // Add criteria for the `fee` relationship to filter by `cancelled_by` being either `null` or `CUSTOMER`
            $whereHasRelations['fee'] = function ($query) {
                $query->whereNull('cancelled_by')
                    ->orWhere('cancelled_by', '=', 'CUSTOMER'); // Handle `null` or `CUSTOMER`
            };
            $totalTripRequest[$zone->name] = $this->tripRequestRepository->getBy(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->count();
            $adminCommission = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.admin_commission');
            $vatTax = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.vat_tax');
            $totalAdminCommission[$zone->name] = number_format($adminCommission - $vatTax, $points, '.', '');
            $totalTax[$zone->name] = number_format($vatTax, $points, '.', '');
        }
        return [
            'label' => $zones->pluck('name')->toArray(),
            'totalTripRequest' => $totalTripRequest,
            'totalAdminCommission' => $totalAdminCommission,
            'totalVatTax' => $totalTax
        ];
    }

    public function getDateRideTypeWiseEarningStatistics(array $data)
    {
        $totalAdminCommission = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        $rideType = [PARCEL, RIDE_REQUEST];
        for ($i = 0; $i <= 1; $i++) {
            $criteriaForStatistics = [
                'type' => $rideType[$i],
                'payment_status' => PAID
            ];
            $whereHasRelations = [];

            // Add criteria for the `fee` relationship to filter by `cancelled_by` being either `null` or `CUSTOMER`
            $whereHasRelations['fee'] = function ($query) {
                $query->whereNull('cancelled_by')
                    ->orWhere('cancelled_by', '=', 'CUSTOMER'); // Handle `null` or `CUSTOMER`
            };
            $adminCommission = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.admin_commission');
            $vatTax = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.vat_tax');
            $totalAdminCommission[$rideType[$i]] = number_format($adminCommission, $points, '.', '');
        }
        return [
            'label' => $rideType,
            'totalAdminCommission' => $totalAdminCommission,
        ];
    }

    public function getDateZoneWiseExpenseStatistics(array $data)
    {
        $zones = $this->zoneRepository->getBy(withTrashed: true);
        $totalExpense = [];
        $totalTripRequest = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        foreach ($zones as $zone) {
            $criteria = [
                'zone_id' => $zone->id
            ];
            $criteriaForStatistics = [
                'zone_id' => $zone->id,
                'payment_status' => PAID
            ];
            $whereHasRelations = [];

            // Add criteria for the `fee` relationship to filter by `cancelled_by` being either `null` or `CUSTOMER`
            $whereHasRelations['fee'] = function ($query) {
                $query->whereNull('cancelled_by')
                    ->orWhere('cancelled_by', '=', 'CUSTOMER'); // Handle `null` or `CUSTOMER`
            };
            $totalTripRequest[$zone->name] = $this->tripRequestRepository->getBy(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->count();
            $adminCouponExpense = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->sum('coupon_amount');
            $adminDiscountExpense = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->sum('discount_amount');
            $totalExpense[$zone->name] = number_format($adminCouponExpense + $adminDiscountExpense, $points, '.', '');
        }
        return [
            'label' => $zones->pluck('name')->toArray(),
            'totalTripRequest' => $totalTripRequest,
            'totalExpense' => $totalExpense,
        ];
    }

    public function getDateRideTypeWiseExpenseStatistics(array $data)
    {
        $totalExpense = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        $rideType = [PARCEL, RIDE_REQUEST];
        for ($i = 0; $i <= 1; $i++) {
            $criteriaForStatistics = [
                'type' => $rideType[$i],
                'payment_status' => PAID
            ];
            $whereHasRelations = [];

            // Add criteria for the `fee` relationship to filter by `cancelled_by` being either `null` or `CUSTOMER`
            $whereHasRelations['fee'] = function ($query) {
                $query->whereNull('cancelled_by')
                    ->orWhere('cancelled_by', '=', 'CUSTOMER'); // Handle `null` or `CUSTOMER`
            };
            $adminCouponExpense = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->sum('coupon_amount');
            $adminDiscountExpense = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->sum('discount_amount');
            $totalExpense[$rideType[$i]] = number_format($adminCouponExpense + $adminDiscountExpense, $points, '.', '');
        }
        return [
            'label' => $rideType,
            'totalExpense' => $totalExpense,
        ];
    }

    public function getLeaderBoard(array $data, $limit = null, $offset = null)
    {
        if ($data['user_type'] == CUSTOMER) {
            $userIdColumn = 'customer_id';
        } else {
            $userIdColumn = 'driver_id';
        }
        $criteria = [];
        if (array_key_exists('zone', $data) && $data['zone'] != 'all') {
            $criteria = array_merge($criteria, ['zone_id' => $data['zone']]);
        }
        if (array_key_exists('driver_id', $data)) {
            $criteria = array_merge($criteria, ['driver_id' => $data['driver_id']]);

        }
        $whereBetweenCriteria = [];
        if (array_key_exists('data', $data) && $data['data'] != ALL_TIME) {
            $date = getDateRange($data['data']);
            $whereBetweenCriteria = [
                'created_at' => [$date['start'], $date['end']],
            ];
        }
        return $this->tripRequestRepository->getLeaderBoard(userType: $userIdColumn, criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, relations: [$data['user_type']], limit: $limit, offset: $offset);
    }


    public function storeTrip(array $attributes): Model
    {
        try {

            DB::beginTransaction();
            $tripData = [];
            $tripData['customer_id'] = $attributes['customer_id'] ?? null;
            $tripData['vehicle_category_id'] = $attributes['vehicle_category_id'] ?? null;
            $tripData['zone_id'] = $attributes['zone_id'] ?? null;
            $tripData['area_id'] = $attributes['area_id'] ?? null;
            $tripData['actual_fare'] = $attributes['estimated_fare'];
            $tripData['estimated_fare'] = $attributes['estimated_fare'] ?? 0;
            $tripData['estimated_distance'] = $attributes['estimated_distance'] ?? null;
            $tripData['payment_method'] = $attributes['payment_method'] ?? null;
            $tripData['note'] = $attributes['note'] ?? null;
            $tripData['type'] = $attributes['type'];
            $tripData['entrance'] = $attributes['entrance'] ?? null;
            $tripData['encoded_polyline'] = $attributes['encoded_polyline'] ?? null;
            $trip = $this->tripRequestRepository->create($tripData);

            $trip->tripStatus()->create([
                'customer_id' => $attributes['customer_id'],
                'pending' => now()
            ]);

            $coordinates = [
                'pickup_coordinates' => $attributes['pickup_coordinates'],
                'start_coordinates' => $attributes['pickup_coordinates'],
                'destination_coordinates' => $attributes['destination_coordinates'],
                'pickup_address' => $attributes['pickup_address'],
                'destination_address' => $attributes['destination_address'],
                'customer_request_coordinates' => $attributes['customer_request_coordinates']
            ];
            $int_coordinates = null;

            if (!is_null($int_coordinates)) {
                foreach ($int_coordinates as $key => $ic) {
                    if ($key == 0) {
                        $coordinates['int_coordinate_1'] = new Point($ic[0], $ic[1]);
                    } elseif ($key == 1) {
                        $coordinates['int_coordinate_2'] = new Point($ic[0], $ic[1]);
                    }
                }
            }
            $coordinates['intermediate_coordinates'] = $attributes['intermediate_coordinates'] ?? null;
            $coordinates['intermediate_addresses'] = $attributes['intermediate_addresses'] ?? null;

            $trip->coordinate()->create($coordinates);
            $trip->fee()->create();
            $delay_time = $trip->time()->create([
                'estimated_time' => $attributes['estimated_time']
            ]);

            if ($attributes['type'] == 'parcel') {
                $trip->parcel()->create([
                    'payer' => $attributes['payer'],
                    'weight' => $attributes['weight'],
                    'parcel_category_id' => $attributes['parcel_category_id'],
                ]);

                $sender = [
                    'name' => $attributes['sender_name'],
                    'contact_number' => $attributes['sender_phone'],
                    'address' => $attributes['sender_address'],
                    'user_type' => 'sender'
                ];
                $receiver = [
                    'name' => $attributes['receiver_name'],
                    'contact_number' => $attributes['receiver_phone'],
                    'address' => $attributes['receiver_address'],
                    'user_type' => 'receiver'
                ];
                $trip->parcelUserInfo()->createMany([$sender, $receiver]);
            }

            DB::commit();
        } catch (\Exception $e) {
            //throw $th;
            DB::rollback();
            abort(403, message: $e->getMessage());
        }

        return $trip;
    }


    public function pendingParcelList(array $attributes)
    {
        return $this->tripRequestRepository->pendingParcelList($attributes);
    }

    public function updateRelationalTable($attributes)
    {
        return $this->tripRequestRepository->updateRelationalTable($attributes);
    }


    public function findOneWithAvg(array $criteria = [], array $relations = [], array $withCountQuery = [], bool $withTrashed = false, bool $onlyTrashed = false, array $withAvgRelation = []): ?Model
    {

        return $this->tripRequestRepository->findOneWithAvg($criteria, $relations, $withCountQuery, $withTrashed, $onlyTrashed, $withAvgRelation);
    }


    public function getWithAvg(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $withAvgRelation = [], array $whereBetweenCriteria = [], array $whereNotNullCriteria = []): Collection|LengthAwarePaginator
    {
        return $this->tripRequestRepository->getWithAvg($criteria, $searchCriteria, $whereInCriteria, $relations, $orderBy, $limit, $offset, $onlyTrashed, $withTrashed, $withCountQuery, $withAvgRelation, $whereBetweenCriteria, $whereNotNullCriteria);
    }

    public function getPendingRides($attributes): mixed
    {
        return $this->tripRequestRepository->getPendingRides($attributes);
    }


    public function makeRideRequest($request, $pickupCoordinates): mixed
    {
        $save_trip = $this->storeTrip(attributes: $request->request->all());

        $search_radius = (float)get_cache('search_radius') ?? (float)5;
        // Find drivers list based on pickup locations
        $find_drivers = $this->findNearestDriver(
            latitude: $pickupCoordinates[0],
            longitude: $pickupCoordinates[1],
            zoneId: $request->header('zoneId'),
            radius: $search_radius,
            vehicleCategoryId: $request->vehicle_category_id
        );
        $saveTripType = $save_trip->type == RIDE_REQUEST ? RIDE_REQUEST : 'parcel_request';
        //Send notifications to drivers
        if (!empty($find_drivers)) {
            $push = getNotification('new_' . $saveTripType);
            $notification = [
                'title' => translate($push['title']),
                'description' => translate($push['description']),
                'status' => $push['status'],
                'ride_request_id' => $save_trip->id,
                'type' => $save_trip->type,
                'action' => $push['action']
            ];
            $notify = [];
            foreach ($find_drivers as $key => $value) {
                broadcast(new RideRequestEvent(user: $value, data: $notification));
                if ($value->user?->fcm_token) {
                    $notify[$key]['user_id'] = $value->user->id;
                    $notify[$key]['trip_request_id'] = $save_trip->id;
                }
            }

            if (!empty($notify)) {

                dispatch(new SendPushNotificationJob($notification, $find_drivers))->onQueue('high');
                $this->tempTripNotificationService->create(['data' => $notify]);
            }
        }
        //Send notifications to admins
        if (!is_null(businessConfig('server_key', NOTIFICATION_SETTINGS))) {
            sendTopicNotification(
                'admin_notification',
                translate('new_request_notification'),
                translate('new_request_has_been_placed'),
                'null',
                $save_trip->id,
                $request->type
            );
        }

        $trip = new TripRequestResource($save_trip);
        return $trip;
    }


    public function allNearestDrivers($latitude, $longitude, $zoneId, $radius = 5, $vehicleCategoryId = null): mixed
    {
        /*
         * replace 6371000 with 6371 for kilometer and 3956 for miles
         */
        $attributes = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius,
            'zone_id' => $zoneId,
        ];
        if ($vehicleCategoryId) {
            $attributes['vehicle_category_id'] = $vehicleCategoryId;
        }
        return $this->userLastLocation->getNearestDrivers($attributes);
    }


    public function validateDiscount($trip, $response, $tripId, $cuponId)
    {
        $admin_trip_commission = (float)get_cache('trip_commission') ?? 0;
        $vat_percent = (float)get_cache('vat_percent') ?? 1;
        $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) - $response['discount'];
        $vat = ($vat_percent * $final_fare_without_tax) / 100;
        $admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
        $updateTrip = $this->findOne(id: $tripId);
        $updateTrip->coupon_id = $cuponId;
        $updateTrip->coupon_amount = $response['discount'];
        $updateTrip->paid_fare = $final_fare_without_tax + $vat + $trip->fee->tips;
        $updateTrip->fee()->update([
            'vat_tax' => $vat,
            'admin_commission' => $admin_commission
        ]);
        $updateTrip->save();

        $push = getNotification('coupon_applied');
        sendDeviceNotification(
            fcm_token: $trip->driver->fcm_token,
            title: translate($push['title']),
            description: translate($push['description']) . ' ' . $response['discount'],
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->driver->id
        );

        $trip = new TripRequestResource($trip->append('distance_wise_fare'));

        return $trip;
    }


    public function handleCancelledTrip($trip, $attributes, $tripId)
    {
        $data = $this->tempTripNotificationService->findOneBy(criteria: [
            'trip_request_id' => $tripId
        ], relations: ['user']);
        $push = getNotification('trip_canceled');
        if (!empty($data)) {
            if ($trip->driver_id) {
                if (!is_null($trip->driver->fcm_token)) {
                    sendDeviceNotification(
                        fcm_token: $trip->driver->fcm_token,
                        title: translate($push['title']),
                        description: translate(textVariableDataFormat(value: $push['description'])),
                        status: $push['status'],
                        ride_request_id: $tripId,
                        type: $trip->type,
                        action: $push['action'],
                        user_id: $trip->driver->id
                    );
                }
                $this->driverDetailService->updatedBy(criteria: ['user_id' => $trip->driver_id], data: ['availability_status' => 'available']);
                $attributes['driver_id'] = $trip->driver_id;
            } else {
                $notification = [
                    'title' => translate($push['title']),
                    'description' => translate($push['description']),
                    'status' => $push['status'],
                    'ride_request_id' => $trip->id,
                    'type' => $trip->type,
                    'action' => $push['action']
                ];
                dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
            }
            $this->tempTripNotificationService->delete(id: $trip->id);
        }
    }


    public function handleCompletedTrip($trip, $request, $attributes)
    {
        if ($request->status == 'cancelled') {
            $attributes['fee']['cancelled_by'] = 'customer';
        }
        $attributes['coordinate']['drop_coordinates'] = new Point($trip->driver->lastLocations->latitude, $trip->driver->lastLocations->longitude);

        $this->driverDetailService->updatedBy(criteria: ['user_id' => $trip->driver_id], data: ['availability_status' => 'available']);
        //Get status wise notification message
        $push = getNotification('trip_' . $request->status);
        if (!is_null($trip->driver->fcm_token)) {
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip->type,
                action: $push['action'],
                user_id: $trip->driver->id
            );
        }
    }


    public function handleCustomerRideStatusUpdate($trip, $request, $attributes)
    {
        DB::beginTransaction();
        if ($request->status == 'cancelled' && $trip->driver_id && $trip->current_status == ONGOING) {
            $this->updateRelationalTable($attributes);
            $this->cancellationPercentChecker(auth('api')->user());
            $this->completedRideChecker($trip->driver);
        } elseif ($request->status == 'completed' && $trip->driver_id && $trip->current_status == ONGOING) {
            $this->updateRelationalTable($attributes);
            $this->completedRideChecker(auth('api')->user());
            $this->completedRideChecker($trip->driver);
        } else {
            $this->updateRelationalTable($attributes);
        }
        DB::commit();
        return $trip;
    }


    public function removeCouponData($trip)
    {
        $coupon = $this->couponService->findOne(id: $trip->coupon_id);
        $coupon->decrement('total_used');
        $coupon->total_amount -= $trip->coupon_amount;
        $coupon->save();


        $trip = $this->findOne(id: $trip->id);
        $vat_percent = (float)get_cache('vat_percent') ?? 1;
        $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) + $trip->coupon_amount;
        $vat = ($vat_percent * $final_fare_without_tax) / 100;
        $trip->coupon_id = null;
        $trip->coupon_amount = 0;
        $trip->paid_fare = $final_fare_without_tax + $vat + $trip->fee->tips;
        $trip->fee()->update([
            'vat_tax' => $vat
        ]);
        $trip->save();
    }


    public function getCustomerIncompleteRide(): mixed
    {
        $trip = $this->tripRequestRepository->findOneBy(criteria: ['customer_id' => auth()->id(), 'type' => RIDE_REQUEST], relations: [
            'customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
            'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo'
        ]);
        if (
            !$trip ||
            ($trip->current_status == CANCELLED) ||
            ($trip->current_status == COMPLETED) ||
            ($trip->driver_id && $trip->fee->cancelled_by == 'customer') ||
            ($trip->driver_id && ($trip->current_status == COMPLETED || $trip->current_status == CANCELLED) && $trip->payment_status == PAID)
        ) {
            return null;
        }

        return $trip;
    }


    public function getDriverIncompleteRide(): mixed
    {
        $trip = $this->findOneWithAvg(criteria: ['driver_id' => auth()->guard('api')->id()], relations: ['tripStatus', 'customer', 'driver', 'time', 'coordinate', 'time', 'fee'], withAvgRelation: ['customerReceivedReviews', 'rating']);

        if (
            !$trip || $trip->fee->cancelled_by == 'driver' ||
            (!$trip->driver_id && $trip->current_status == 'cancelled') ||
            ($trip->driver_id && $trip->payment_status == PAID)
        ) {
            return null;
        }
        return $trip;
    }


    public function handleDriverStatusUpdate($request, $trip)
    {
        $attributes = [
            'column' => 'id',
            'value' => $request['trip_request_id'],
            'trip_status' => $request['status']
        ];
        DB::beginTransaction();
        if ($request->status == 'completed' || $request->status == 'cancelled') {
            if ($request->status == 'cancelled') {
                $attributes['fee']['cancelled_by'] = 'driver';
            }
            $attributes['coordinate']['drop_coordinates'] = new Point($trip->driver->lastLocations->latitude, $trip->driver->lastLocations->longitude);

            $this->driverDetailService->updatedBy(criteria: ['user_id' => auth('api')->id()], data: ['availability_status' => 'available']);
        }

        $data = $this->updateRelationalTable($attributes);


        if ($request->status == 'cancelled') {
            $this->cancellationPercentChecker(auth('api')->user());
            $this->completedRideChecker($trip->customer);
        } elseif ($request->status == 'completed') {
            $this->completedRideChecker(auth('api')->user());
            $this->completedRideChecker($trip->customer);
        }

        DB::commit();
        //Get status wise notification message
        if ($trip->type == 'parcel') {
            $action = 'parcel_' . $request->status;
        } else {
            $action = 'ride_' . $request->status;
        }
        $push = getNotification($action);
        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $request['trip_request_id'],
            type: $trip->type,
            action: $action,
            user_id: $trip->customer->id
        );


        return $data;
    }


    public function handleRequestActionPushNotification($trip, $user)
    {
        DB::beginTransaction();
        Cache::put($trip->id, ACCEPTED, now()->addHour());
        $driverArrivalTime = getRoutes(
            originCoordinates: [
                $trip->coordinate->pickup_coordinates->getLat(),
                $trip->coordinate->pickup_coordinates->getLng()
            ],
            destinationCoordinates: [
                $user->lastLocations->latitude,
                $user->lastLocations->longitude
            ],
        );
        $attributes['driver_arrival_time'] = (float)($driverArrivalTime[0]['duration']) / 60;
        $this->driverDetailService->update(id: $user->id, data: ['availability_status' => 'on_trip']);

        $data = $this->tempTripNotificationService->getBy(criteria: [
            ['trip_request_id' => $trip->id],
            ['user_id', '!=', auth('api')->id()]
        ], relations: ['user']);

        if (!empty($data)) {
            $push = getNotification('trip_started');
            $notification = [
                'title' => translate($push['title']),
                'description' => translate($push['description']),
                'status' => $push['status'],
                'ride_request_id' => $trip->id,
                'type' => $trip->type,
                'action' => $push['action']
            ];
            dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
            $this->tempTripNotificationService->delete(id: $trip->id);
            $this->tempTripNotificationService->deleteBy(criteria: ['user_id', $user->id]);
        }
        //Trip update
        $this->update(data: $attributes, id: $trip->id);
        //deleting exiting rejected driver request for this trip
        $this->rejectedDriverRequestService->deleteBy(criteria: ['trip_request_id', $trip->id]);

        return getNotification('driver_on_the_way');
    }


    public function getTripOverview($data)
    {

        if ($data['filter'] == THIS_WEEK) {
            $dateRange = THIS_WEEK;
        }
        if ($data['filter'] == LAST_WEEK) {
            $dateRange = LAST_WEEK;
        }

        switch ($dateRange) {
            case LAST_WEEK:
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            default:
                $startDate = Carbon::now()->subWeek()->startOfWeek();
                $endDate = Carbon::now()->subWeek()->endOfWeek();
        }
        $period = CarbonPeriod::create($startDate, $endDate);

        $whereBetweenCriteria = [
            'created_at' => [$startDate, $endDate],
        ];
        $trips = $this->tripRequestRepository->getBy(criteria: ['driver_id' => auth()->id()], whereBetweenCriteria: $whereBetweenCriteria);
        $day = ['Mon', 'Tues', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        $incomeStat = [];
        foreach ($period as $key => $p) {
            $incomeStat[$day[$key]] = $trips
                ->whereBetween('created_at', [$p->copy()->startOfDay(), $p->copy()->endOfDay()])
                ->sum('estimated_fare');
        }
        $totalReviews = $this->reviewRepository->getBy(whereBetweenCriteria: $whereBetweenCriteria);
        return [
            'totalReviews' => $totalReviews,
            'trips' => $trips,
            'incomeStat' => $incomeStat
        ];

    }

    public function getPopularTips()
    {
        return $this->tripRequestRepository->getPopularTips();
    }

    public function sendParcelTrackingLinkToReceiver($user, $message): bool
    {
        $dataValues = $this->settingRepository->getBy(criteria: ['settings_type' => SMS_CONFIG]);
        if ($dataValues->where('live_values.status', 1)->isNotEmpty()) {
            try {
                self::send($user->phone, $message);
                return true;
            } catch (\Exception $exception) {
                return false;
            }
        }
        return false;
    }

    public function getTripHeatMapCompareDataBy(array $data)
    {
        $criteria = [
            'zone_id' => $data['zone_id']
        ];

        $date = getCustomDateRange($data['date_range']);
        $startDate = $date['start'];
        $endDate = $date['end'];
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];

        return $this->tripRequestRepository->getTripHeatMapCompareDataBy(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $endDate);
    }


    public function getTripHeatMapCompareZoneDateWiseEarningStatistics(array $data)
    {
        $criteria = [
            'zone_id' => $data['zone_id']
        ];
        $date = getCustomDateRange($data['date_range']);
        $startDate = $date['start'];
        $endDate = $date['end'];
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        $criteriaForCommission = array_merge($criteria, [
            'payment_status' => PAID
        ]);

        $totalTripRequest = [];
        $totalAdminCommission = [];
        $label = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        // Adjust to the beginning of the week (Monday)
        $startOfWeek = $startDate->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $endDate->copy()->startOfWeek(Carbon::MONDAY);
        // Determine if the custom date range is for today, this month, or this year
        if ($startDate->isSameDay($endDate)) {
            // Logic for TODAY
            $label = [
                "6:00 am",
                "8:00 am",
                "10:00 am",
                "12:00 pm",
                "2:00 pm",
                "4:00 pm",
                "6:00 pm",
                "8:00 pm",
                "10:00 pm",
                "12:00 am",
                "2:00 am",
                "4:00 am"
            ];
            $startTime = strtotime('6:00 AM');
            for ($i = 0; $i < 12; $i++) {
                $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, startTime: $startTime)->count();
                $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, startTime: $startTime)->sum('fee.admin_commission');
                $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                $startTime = strtotime('+2 hours', $startTime);
            }
        } elseif ($startOfWeek->isSameWeek($endOfWeek)) {
            $start = $startOfWeek->copy()->startOfWeek(CarbonInterface::MONDAY);
            for ($i = 1; $i <= 7; $i++) {
                if ($start >= $startDate && $start <= $endDate) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $start)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $start)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } else {
                    $totalTripRequest[$i] = 0;
                    $totalAdminCommission[$i] = 0;
                }

                $label[] = $start->format("D");
                $start = $start->addDays(1);
            }
        } elseif ($startDate->isSameMonth($endDate)) {
            // Logic for THIS_MONTH
            $label = [
                "Day 1-7",
                "Day 8-14",
                "Day 15-21",
                "Day 22-" . now()->daysInMonth . "",
            ];
            $start = $startDate->copy()->startOfMonth();
            $end = $start->copy()->addDays(6);
            $remainingDays = $startDate->daysInMonth - 28;
            for ($i = 1; $i <= 4; $i++) {
                if ($start < $startDate && $end <= $endDate) {
                    // Current range starts before and ends within the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($start >= $startDate && $end > $endDate) {
                    // Current range starts within and ends after the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $endDate)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $endDate)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($start >= $startDate && $end <= $endDate) {
                    // Current range is completely within the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($start < $startDate && $end > $startDate) {
                    // Current range overlaps the start of the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($start < $endDate && $end > $endDate) {
                    // Current range overlaps the end of the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $endDate)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $endDate)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } else {
                    // If none of the conditions are met, set totals to zero
                    $totalTripRequest[$i] = 0;
                    $totalAdminCommission[$i] = 0;
                }
                $start = $start->addDays(7);
                $end = $i == 3 ? $end->addDays(7 + $remainingDays) : $end->addDays(7);
            }
        } elseif ($startDate->isSameYear($endDate)) {
            $year = $startDate->year;
            $startDateMonth = $startDate->month;
            $endDateMonth = $endDate->month;
            $label = [
                "Jan", "Feb", "Mar", "Apr", "May", "Jun",
                "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
            ];
            for ($i = 1; $i <= 12; $i++) {
                if ($i >= $startDateMonth && $i <= $endDateMonth) {
                    if ($startDateMonth == $i && $endDateMonth == $i) {
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteria,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            startDate: $startDate,
                            endDate: $endDate,
                            month: $i,
                            year: $year
                        )->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteriaForCommission,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            relations: ['fee'],
                            startDate: $startDate,
                            endDate: $endDate,
                            month: $i,
                            year: $year
                        )->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    } elseif ($startDateMonth == $i && $endDateMonth > $i) {
                        $endDateOfMonth = Carbon::create($year, $i, 1)->endOfMonth();
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteria,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            startDate: $startDate,
                            endDate: $endDateOfMonth,
                            month: $i,
                            year: $year
                        )->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteriaForCommission,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            relations: ['fee'],
                            startDate: $startDate,
                            endDate: $endDateOfMonth,
                            month: $i,
                            year: $year
                        )->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    } elseif ($startDateMonth < $i && $endDateMonth > $i) {
                        $startDateOfMonth = Carbon::create($year, $i, 1)->startOfMonth();
                        $endDateOfMonth = Carbon::create($year, $i, 1)->endOfMonth();
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteria,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            startDate: $startDateOfMonth,
                            endDate: $endDateOfMonth,
                            month: $i,
                            year: $year
                        )->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteriaForCommission,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            relations: ['fee'],
                            startDate: $startDateOfMonth,
                            endDate: $endDateOfMonth,
                            month: $i,
                            year: $year
                        )->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    } elseif ($startDateMonth < $i && $endDateMonth == $i) {
                        $startDateOfMonth = Carbon::create($year, $i, 1)->startOfMonth();
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteria,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            startDate: $startDateOfMonth,
                            endDate: $endDate,
                            month: $i,
                            year: $year
                        )->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteriaForCommission,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            relations: ['fee'],
                            startDate: $startDateOfMonth,
                            endDate: $endDate,
                            month: $i,
                            year: $year
                        )->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    }

                } else {
                    $totalTripRequest[$i] = 0;
                    $totalAdminCommission[$i] = 0;
                }
            }
        } elseif ($startDate->year <= $endDate->year) {
            $startYear = $startDate->year;
            $endYear = $endDate->year;
            for ($i = $startYear; $i <= $endYear; $i++) {
                $label[] = $i;
                if ($i == $startYear && $i == $endYear) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $endDate, year: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, endDate: $endDate, year: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($i == $startYear && $i < $endYear) {
                    $endDay = Carbon::create($i, 12, 31)->endOfDay();
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $endDay, year: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, endDate: $endDay, year: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($i > $startYear && $i < $endYear) {
                    $startDay = Carbon::create($i, 11, 11)->startOfDay();
                    $endDay = Carbon::create($i, 12, 31)->endOfDay();
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDay, endDate: $endDay, year: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDay, endDate: $endDay, year: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($i > $startYear && $i == $endYear) {
                    $startDay = Carbon::create($i, 1, 1)->startOfDay();
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDay, endDate: $endDate, year: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDay, endDate: $endDate, year: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } else {
                    $totalTripRequest[$i] = 0;
                    $totalAdminCommission[$i] = 0;
                }
            }

        }


        return [
            'label' => $label,
            'totalTripRequest' => $totalTripRequest,
            'totalAdminCommission' => $totalAdminCommission
        ];
    }

    public function allRideList(): mixed
    {
        $criteria = ['driver_id' => auth()->id(), 'type' => RIDE_REQUEST];
        $relations = ['fee', 'customer'];
        $orderBy = ['created_at' => 'desc'];
        return $this->tripRequestRepository->allRideList($criteria, $relations, $orderBy);
    }

    public function rideWaiting(Model $trip, Model $time): void
    {
        $now = now();
        $timeDifference = Carbon::parse($time->idle_timestamp)->diffInMinutes($now);

        $updateTrip = ['is_paused' => !$trip->is_paused];
        $updateTime = ['idle_timestamp' => $now];

        if ($trip->is_paused) {
            $updateTime['idle_time'] = $time->idle_time + $timeDifference;
        }

        $this->tripRequestRepository->update(id: $trip->id, data: $updateTrip);
        $this->tripRequestTimeRepository->update(id: $time->id, data: $updateTime);
    }

    public function rideList(array $data = [])
    {
        if (!is_null($data['filter']) && $data['filter'] != CUSTOM_DATE) {
            $date = getDateRange($data['filter']);
        } elseif (!is_null($data['filter'])) {
            $date = getDateRange([
                'start' => $data['start'],
                'end' => $data['end']
            ]);
        }

        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];

        $criteria = [
            'driver_id' => auth('api')->id(),
        ];

        $whereInCriteria = [];
        if (!is_null($data['status']) && $data['status'] != ALL) {
            $whereInCriteria = [
                'current_status' => [$data['status']],
            ];
        }

        $relations = ['customer', 'vehicle.model', 'vehicleCategory', 'time', 'coordinate', 'fee', 'parcel.parcelCategory', 'parcelRefund'];
        $withAvgRelations = [
            ['relation' => 'driverReceivedReviews', 'column' => 'rating'],
        ];
        $orderBy = ['created_at' => 'desc'];

        return $this->tripRequestRepository->getBy(criteria: $criteria, whereInCriteria: $whereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, withAvgRelations: $withAvgRelations, relations: $relations, orderBy: $orderBy, limit: $data['limit'], offset: $data['offset']);
    }

    public function getPendingParcel(array $data = []): mixed
    {
        $criteria = [
            $data['user_column'] => auth('api')->id(),
            [$data['user_column'], '!=', NULL],
            'type' => PARCEL
        ];

        $relations = ['customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
            'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo'];
        $orderBy = ['created_at' => 'desc'];

        $limit = $data['limit'];
        $offset = $data['offset'];

        return $this->tripRequestRepository->getPendingParcel(criteria: $criteria, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset);
    }

    public function getPendingRide(array $data = []): mixed
    {
        $criteria = [
            'zone_id' => $data['zone_id'],
            'current_status' => PENDING,
        ];

        $relations = [
            'driver.driverDetails' => [], 'customer' => [], 'ignoredRequests' => [], 'time' => [], 'fee' => [],
            'parcel' => [], 'parcelRefund' => [],
            'fare_biddings' => [['driver_id', '=', auth('api')->id()]],
            'coordinate' => fn($query) => $query->distanceSphere('pickup_coordinates', $data['driver_locations'], $data['distance'])
        ];
        $whereHasRelations = [];

        $whereHasRelations['coordinate'] = function ($query) use ($data) {
            $query->distanceSphere('pickup_coordinates', $data['driver_locations'], $data['distance']);
        };
        $orderBy = ['created_at' => 'desc'];

        return $this->tripRequestRepository->getPendingRide(criteria: $criteria, relations: $relations, whereHasRelations: $whereHasRelations, orderBy: $orderBy, attributes: $data);
    }

    public function tripOverview(array $data = []): mixed
    {
        if ($data['filter'] == THIS_WEEK) {
            $start = now()->startOfWeek();
            $end = now()->endOfWeek();
            $day = ['Mon', 'Tues', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        }
        if ($data['filter'] == LAST_WEEK) {
            $start = Carbon::now()->subWeek()->startOfWeek();
            $end = Carbon::now()->subWeek()->endOfWeek();
            $day = ['Mon', 'Tues', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        }
        if ($data['filter'] == TODAY) {
            $start = Carbon::today()->startOfDay();
            $end = Carbon::today()->endOfDay();
            $day = [
                '6:00 am',
                '10:00 am',
                '2:00 pm',
                '6:00 pm',
                '10:00 pm',
                '2:00 am',
            ];
        }
        $whereBetweenCriteria = [
            'created_at' => [$start, $end],
        ];
        $trips = $this->tripRequestRepository->getBy(criteria: ['driver_id' => auth('api')->id()], whereBetweenCriteria: $whereBetweenCriteria);

        if ($data['filter'] == TODAY) {
            $incomeStat = [];
            $startTime = strtotime('6:00 AM');

            for ($i = 0; $i < 6; $i++) {
                $incomeStat[$day[$i]] = $trips
                    ->whereBetween('created_at', [
                        date('Y-m-d', strtotime(TODAY)) . ' ' . date('H:i:s', $startTime),
                        date('Y-m-d', strtotime(TODAY)) . ' ' . date('H:i:s', strtotime('+4 hours', $startTime))
                    ])
                    ->sum('paid_fare');
                $startTime = strtotime('+4 hours', $startTime);
            }
        } else {
            $period = CarbonPeriod::create($start, $end);
            $whereBetweenCriteria = [
                'created_at' => [$start, $end],
            ];
            $trips = $this->tripRequestRepository->getBy(criteria: ['driver_id' => auth('api')->id()], whereBetweenCriteria: $whereBetweenCriteria);
            $incomeStat = [];
            foreach ($period as $key => $p) {
                $incomeStat[$day[$key]] = $trips
                    ->whereBetween('created_at', [$p->copy()->startOfDay(), $p->copy()->endOfDay()])
                    ->sum('paid_fare');
            }
        }
        $reviewCriteria = [
            'received_by' => auth('api')->id(),
        ];
        $whereBetweenCriteria = [
            'created_at' => [$start, $end],
        ];
        $totalReviews = $this->reviewService->getBy(criteria: $reviewCriteria, whereBetweenCriteria: $whereBetweenCriteria, orderBy: ['created_at' => 'desc']);
        $totalReviews = $totalReviews->count();
        $totalTrips = $trips->count();
        if ($totalTrips == 0) {
            $fallback = 1;
        } else {
            $fallback = $totalTrips;
        }
        $successTrips = $trips->where('current_status', 'completed')->count();
        $cancelTrips = $trips->where('current_status', 'cancelled')->count();
        $totalEarn = $trips->sum('paid_fare');

        return [
            'success_rate' => ($successTrips / $fallback) * 100,
            'total_trips' => $totalTrips,
            'total_earn' => $totalEarn,
            'total_cancel' => $cancelTrips,
            'total_reviews' => $totalReviews,
            'income_stat' => $incomeStat
        ];
    }

    public function updateRideStatus(array $data = []): mixed
    {
        $trip = $data['trip'];
        DB::beginTransaction();
        if ($data['status'] == 'completed' || $data['status'] == 'cancelled') {
            if ($data['status'] == 'cancelled') {
                $data['fee']['cancelled_by'] = 'driver';
                if ($trip->customer->referralCustomerDetails && $trip->customer->referralCustomerDetails->is_used == 0) {
                    $trip->customer->referralCustomerDetails()->update([
                        'is_used' => 1
                    ]);
                    if ($trip->customer?->referralCustomerDetails?->ref_by_earning_amount && $trip->customer?->referralCustomerDetails?->ref_by_earning_amount > 0) {
                        $shareReferralUser = $trip->customer?->referralCustomerDetails?->shareRefferalCustomer;
                        $this->customerReferralEarningTransaction($shareReferralUser, $trip->customer?->referralCustomerDetails?->ref_by_earning_amount);
                        $push = getNotification('referral_reward_received');
                        sendDeviceNotification(fcm_token: $shareReferralUser?->fcm_token,
                            title: translate($push['title']),
                            description: translate(textVariableDataFormat(value: $push['description'], referralRewardAmount: getCurrencyFormat($trip->customer?->referralCustomerDetails?->ref_by_earning_amount))),
                            status: $push['status'],
                            ride_request_id: $shareReferralUser?->id,
                            action: $push['action'],
                            user_id: $shareReferralUser?->id
                        );
                    }
                }
            }
            $data['coordinate']['drop_coordinates'] = new Point($trip->driver->lastLocations->latitude, $trip->driver->lastLocations->longitude);
            $driverDetails = $this->driverDetailService->findOneBy(criteria: ['user_id' => auth('api')->id()]);
            if ($trip->type == RIDE_REQUEST) {
                $driverDetails->ride_count = --$driverDetails->ride_count;
            } else if ($data['status'] == 'completed' || ($trip->driver_id && $data['status'] == 'cancelled' && $trip->current_status == ACCEPTED)) {
                --$driverDetails->parcel_count;
            }
            $driverDetails->save();
        }

        if ($data['status'] ?? null) {
            $trip->current_status = $data['status'];
            $trip->save();
            $trip->tripStatus()->update([
                $data['status'] => now()
            ]);
        }

        if ($data['cancel_reason'] ?? null) {
            $trip->trip_cancellation_reason = $data['cancel_reason'];
            $trip->save();
        }

        if ($data['driver_id'] ?? null) {
            $trip->driver_id = null;
            $trip->save();
        }

        if ($data['coordinate'] ?? null) {
            $coordinate = $trip->coordinate;
            if ($coordinate) {
                $coordinate->update([
                    'drop_coordinates' => $data['coordinate']['drop_coordinates'],
                ]);
                $coordinate->save();
            }
        }

        if ($data['fee'] ?? null) {
            $trip->fee()->update($data['fee']);
        }

        $tripStatus = $trip->tripStatus;

        if ($data['status'] == 'cancelled') {
            $this->customerLevelUpdateChecker($trip->customer);
            $this->driverLevelUpdateChecker(auth()->user());
        } elseif ($data['status'] == 'completed') {
            $this->customerLevelUpdateChecker($trip->customer);
            $this->driverLevelUpdateChecker(auth()->user());
        }

        DB::commit();
        if ($trip->driver_id && $data['status'] == 'cancelled' && $trip->current_status == ONGOING && $trip->type == PARCEL) {
            $env = env('APP_MODE');
            $otp = $env != "live" ? '0000' : rand(1000, 9999);
            $trip->otp = $otp;
            $trip->return_fee = 0;
            $trip->current_status = RETURNING;
            $trip->return_time = Carbon::parse($data['return_time']);
            $trip->save();
            $trip->tripStatus()->update([
                RETURNING => now()
            ]);
            if ($trip->cancellation_fee > 0) {
                $this->driverParcelCancellationTransaction($trip);
            }
            if ($trip?->parcel?->payer === 'sender' && $trip->payment_status == PAID) {
                if ($trip->payment_method === 'cash') {
                    $this->senderCashPaymentDriverParcelCancelReverseTransaction($trip);
                } elseif ($trip->payment_method === 'wallet') {
                    $this->senderWalletPaymentDriverParcelCancelReverseTransaction($trip);
                } else {
                    $this->senderDigitalPaymentDriverParcelCancelReverseTransaction($trip);
                }
            }
        }

        return $tripStatus;
    }

    public function getLockedTrip(array $data = []): mixed
    {
        return $this->tripRequestRepository->getLockedTrip(data: $data);
    }

    public function updateTripRequestAction(array $attributes, Model $trip): Model
    {
        $trip_request_keys = ['customer_id', 'driver_id', 'vehicle_category_id', 'vehicle_id', 'zone_id', 'estimated_fare',
            'actual_fare', 'extra_fare_amount', 'estimated_distance', 'paid_fare', 'actual_distance', 'accepted_by', 'payment_method',
            'payment_status', 'coupon_id', 'coupon_amount', 'vat_tax', 'additional_charge', 'trx_id', 'note', 'otp', 'rise_request_count',
            'type', 'current_status', 'tips', 'is_paused', 'map_screenshot'];

        $result = [];
        foreach ($trip_request_keys as $key) {
            $result[$key] = $attributes[$key] ?? $trip->$key ?? null;
        }

        if ($attributes['rise_request_count'] ?? null) {
            $trip->increment('rise_request_count');
        }

        $trip->update($result);

        if ($attributes['tripStatus'] ?? null) {
            $trip->tripStatus()->update([$attributes['current_status'] => now()]);
        }

        if ($attributes['driver_arrival_time'] ?? null) {
            $trip->time()->update(['driver_arrival_time' => $attributes['driver_arrival_time']]);
        }

        if ($attributes['coordinate'] ?? null) {
            $trip->coordinate()->update($attributes['coordinate']);
        }

        return $trip->refresh();
    }

    public function findNearestDrivers(string $latitude, string $longitude, string $zoneId, int|string $radius, string $vehicleCategoryId = null, string $requestType = null, int|string $parcelWeight = null): mixed
    {
        $attributes = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius,
            'zone_id' => $zoneId,
        ];

        if ($vehicleCategoryId) {
            $attributes['vehicle_category_id'] = $vehicleCategoryId;
        }
        if ($parcelWeight) {
            $attributes['parcel_weight_capacity'] = $parcelWeight;
        }
        if ($requestType) {
            $attributes['service'] = $requestType;
        }

        $maxParcelRequestAcceptLimit = businessConfig(key: 'maximum_parcel_request_accept_limit', settingsType: DRIVER_SETTINGS);
        $maxParcelRequestAcceptLimitStatus = (bool)($maxParcelRequestAcceptLimit?->value['status'] ?? false);
        $maxParcelRequestAcceptLimitCount = (int)($maxParcelRequestAcceptLimit?->value['limit'] ?? 0);
        if ($maxParcelRequestAcceptLimitStatus) {
            $attributes['parcel_accept_limit'] = $maxParcelRequestAcceptLimitCount;
        }
        if ($requestType) {
            $driverList = $this->userLastLocation->getNearestDrivers($attributes);
            return $driverList->filter(function ($driver) use ($requestType, $maxParcelRequestAcceptLimitStatus, $maxParcelRequestAcceptLimitCount) {
                $rideCount = $driver->driverDetails->ride_count ?? 0;
                $parcelCount = $driver->driverDetails->parcel_count ?? 0;
                if ($requestType === RIDE_REQUEST && ($rideCount >= 2 || $driver->user->getDriverAcceptedTrip())) {
                    return false;
                }
                $destinationCoordinates = json_decode($driver->user->getDriverOngoingTrip()?->coordinate, true);


                if ($requestType === RIDE_REQUEST && $destinationCoordinates && isset($destinationCoordinates['destination_coordinates']['coordinates'])) {
                    $driverLastLongitude = (float)$driver->longitude;
                    $driverLastLatitude = (float)$driver->latitude;
                    $destinationLongitude = (float)$destinationCoordinates['destination_coordinates']['coordinates'][0];
                    $destinationLatitude = (float)$destinationCoordinates['destination_coordinates']['coordinates'][1];
                    $latDifference = deg2rad($destinationLatitude - $driverLastLatitude);
                    $lonDifference = deg2rad($destinationLongitude - $driverLastLongitude);
                    $earthRadius = 6371;

                    $a = sin($latDifference / 2) * sin($latDifference / 2) +
                        cos(deg2rad($driverLastLatitude)) * cos(deg2rad($destinationLatitude)) *
                        sin($lonDifference / 2) * sin($lonDifference / 2);

                    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                    $distance = $earthRadius * $c;
                    if ($distance > 1) {
                        return false;
                    }
                }

                if ($requestType === PARCEL && $maxParcelRequestAcceptLimitStatus) {
                    return $parcelCount < $maxParcelRequestAcceptLimitCount;
                }

                return true;
            })->values();
        }

        return $this->userLastLocation->getNearestDrivers($attributes);
    }

    public function getIncompleteRide(): mixed
    {
        $criteria = ['type' => RIDE_REQUEST , 'customer_id' => auth('api')->id()];
        $tripRequest = $this->tripRequestRepository->getIncompleteRide(criteria: $criteria);
        if (!$tripRequest) {
            return null;
        }
        return $this->tripRequestRepository->findOneWithAvg(
            criteria: ['id' => $tripRequest->id],
            relations: ['customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
            'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo', 'customerReceivedReviews', 'driverReceivedReviews'],
            withAvgRelation: ['driverReceivedReviews', 'rating']
        );
    }

    public function createRideRequest(array $attributes = []): mixed
    {
        try {
            DB::beginTransaction();
            $trip = $this->tripRequestRepository->create([
                'customer_id' => $attributes['customer_id'] ?? null,
                'vehicle_category_id' => $attributes['vehicle_category_id'] ?? null,
                'zone_id' => $attributes['zone_id'] ?? null,
                'area_id' => $attributes['area_id'] ?? null,
                'actual_fare' => $attributes['actual_fare'],
                'estimated_fare' => $attributes['estimated_fare'] ?? 0,
                'return_fee' => $attributes['return_fee'] ?? 0,
                'cancellation_fee' => $attributes['cancellation_fee'] ?? 0,
                'extra_fare_fee' => $attributes['extra_fare_fee'] ?? 0,
                'extra_fare_amount' => $attributes['extra_fare_amount'] ?? 0,
                'rise_request_count' => $attributes['rise_request_count'] ?? 0,
                'estimated_distance' => str_replace(',', '', $attributes['estimated_distance']) ?? null,
                'payment_method' => $attributes['payment_method'] ?? null,
                'note' => $attributes['note'] ?? null,
                'type' => $attributes['type'],
                'entrance' => $attributes['entrance'] ?? null,
                'encoded_polyline' => $attributes['encoded_polyline'] ?? null,
            ]);


            $trip->tripStatus()->create([
                'customer_id' => $attributes['customer_id'],
                'pending' => now()
            ]);

            $coordinates = [
                'pickup_coordinates' => $attributes['pickup_coordinates'],
                'start_coordinates' => $attributes['pickup_coordinates'],
                'destination_coordinates' => $attributes['destination_coordinates'],
                'pickup_address' => $attributes['pickup_address'],
                'destination_address' => $attributes['destination_address'],
                'customer_request_coordinates' => $attributes['customer_request_coordinates']
            ];
            $int_coordinates = json_decode($attributes['intermediate_coordinates']);
            if (!is_null($int_coordinates)) {
                foreach ($int_coordinates as $key => $ic) {
                    if ($key == 0) {
                        $coordinates['int_coordinate_1'] = new Point($ic[0], $ic[1]);
                    } elseif ($key == 1) {
                        $coordinates['int_coordinate_2'] = new Point($ic[0], $ic[1]);
                    }
                }

            }
            $coordinates['intermediate_coordinates'] = $attributes['intermediate_coordinates'] ?? null;
            $coordinates['intermediate_addresses'] = $attributes['intermediate_addresses'] ?? null;

            $trip->coordinate()->create($coordinates);
            $trip->fee()->create();
            $trip->time()->create([
                'estimated_time' => str_replace(',', '', $attributes['estimated_time'])
            ]);

            if ($attributes['type'] == 'parcel') {
                $trip->parcel()->create([
                    'payer' => $attributes['payer'],
                    'weight' => $attributes['weight'],
                    'parcel_category_id' => $attributes['parcel_category_id'],
                ]);

                $sender = [
                    'name' => $attributes['sender_name'],
                    'contact_number' => $attributes['sender_phone'],
                    'address' => $attributes['sender_address'],
                    'user_type' => 'sender'
                ];
                $receiver = [
                    'name' => $attributes['receiver_name'],
                    'contact_number' => $attributes['receiver_phone'],
                    'address' => $attributes['receiver_address'],
                    'user_type' => 'receiver'
                ];
                $trip->parcelUserInfo()->createMany([$sender, $receiver]);

            }

            DB::commit();
        } catch (\Exception $e) {
            //throw $th;
            DB::rollback();
            abort(403, message: $e->getMessage());
        }

        return $trip;
    }

}
