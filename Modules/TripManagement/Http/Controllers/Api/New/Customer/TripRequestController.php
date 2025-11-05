<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Customer;

use App\Events\CustomerTripCancelledAfterOngoingEvent;
use App\Events\CustomerTripCancelledEvent;
use App\Events\CustomerTripRequestEvent;
use App\Jobs\SendPushNotificationJob;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Http\Requests\RideListRequest;
use Modules\FareManagement\Service\Interface\ParcelFareServiceInterface;
use Modules\FareManagement\Service\Interface\ParcelFareWeightServiceInterface;
use Modules\FareManagement\Service\Interface\TripFareServiceInterface;
use Modules\Gateways\Traits\Payment;
use Modules\ParcelManagement\Service\Interface\ParcelWeightServiceInterface;
use Modules\PromotionManagement\Service\Interface\CouponSetupServiceInterface;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Entities\TripRequestCoordinate;
use Modules\TripManagement\Http\Requests\GetEstimatedFaresOrNotRequest;
use Modules\TripManagement\Http\Requests\RideRequestCreate;
use Modules\TripManagement\Lib\CommonTrait;
use Modules\TripManagement\Lib\CouponCalculationTrait;
use Modules\TripManagement\Service\Interface\FareBiddingServiceInterface;
use Modules\TripManagement\Service\Interface\RecentAddressServiceInterface;
use Modules\TripManagement\Service\Interface\RejectedDriverRequestServiceInterface;
use Modules\TripManagement\Service\Interface\TempTripNotificationServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestCoordinateServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestTimeServiceInterface;
use Modules\TripManagement\Transformers\FareBiddingResource;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Interfaces\UserLastLocationInterface;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Service\Interface\DriverDetailServiceInterface;
use Modules\UserManagement\Service\Interface\UserServiceInterface;
use Modules\UserManagement\Transformers\LastLocationResource;
use Modules\ZoneManagement\Service\Interface\ZoneServiceInterface;

class TripRequestController extends Controller
{
    use CommonTrait, TransactionTrait, Payment, CouponCalculationTrait, LevelHistoryManagerTrait;

    protected $tripRequestservice;
    protected $tempTripNotificationService;
    protected $fareBiddingService;
    protected $userLastLocation;
    protected $userService;
    protected $driverDetailService;
    protected $rejectedDriverRequestService;
    protected $couponService;
    protected $zoneService;
    protected $tripFareService;
    protected $parcelFareService;
    protected $parcelFareWeightService;
    protected $recentAddressService;
    protected $tripRequestTimeService;

    protected $tripRequestCoordinateService;

    protected $parcelWeightService;

    public function __construct(
        TripRequestServiceInterface           $tripRequestservice,
        TempTripNotificationServiceInterface  $tempTripNotificationService,
        FareBiddingServiceInterface           $fareBiddingService,
        UserLastLocationInterface             $userLastLocation,
        UserServiceInterface                  $userService,
        DriverDetailServiceInterface          $driverDetailService,
        RejectedDriverRequestServiceInterface $rejectedDriverRequestService,
        CouponSetupServiceInterface           $couponService,
        ZoneServiceInterface                  $zoneService,
        TripFareServiceInterface              $tripFareService,
        ParcelFareWeightServiceInterface      $parcelFareWeightService,
        ParcelFareServiceInterface            $parcelFareService,
        RecentAddressServiceInterface         $recentAddressService,
        TripRequestTimeServiceInterface       $tripRequestTimeService,
        TripRequestCoordinateServiceInterface $tripRequestCoordinateService,
        ParcelWeightServiceInterface          $parcelWeightService,
    )
    {
        $this->tripRequestservice = $tripRequestservice;
        $this->tempTripNotificationService = $tempTripNotificationService;
        $this->fareBiddingService = $fareBiddingService;
        $this->userLastLocation = $userLastLocation;
        $this->userService = $userService;
        $this->driverDetailService = $driverDetailService;
        $this->rejectedDriverRequestService = $rejectedDriverRequestService;
        $this->couponService = $couponService;
        $this->zoneService = $zoneService;
        $this->tripFareService = $tripFareService;
        $this->parcelFareWeightService = $parcelFareWeightService;
        $this->parcelFareService = $parcelFareService;
        $this->recentAddressService = $recentAddressService;
        $this->tripRequestTimeService = $tripRequestTimeService;
        $this->tripRequestCoordinateService = $tripRequestCoordinateService;
        $this->parcelWeightService = $parcelWeightService;
    }


    public function createRideRequest(RideRequestCreate $request): JsonResponse
    {
        $trip = $this->tripRequestservice->getIncompleteRide();
        if ($request->type == "ride_request" && $trip && $request->trip_request_id == null) {
            return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
        }

        if ($request->trip_request_id) {
            $save_trip = $this->tripRequestservice->findOneBy(criteria: ['id' => $request['trip_request_id']]);
            $pickup_point = $save_trip->coordinate->pickup_coordinates;
            $destination_point = $save_trip->coordinate->destination_coordinates;
        } else {
            $pickup_coordinates = json_decode($request['pickup_coordinates'], true);
            $destination_coordinates = json_decode($request['destination_coordinates'], true);
            $pickup_point = new Point($pickup_coordinates[0], $pickup_coordinates[1]);
            $destination_point = new Point($destination_coordinates[0], $destination_coordinates[1]);
        }

        $zone = $this->zoneService->getByPoints($pickup_point)->where('is_active', 1)->first();
        if (!$zone) {
            return response()->json(responseFormatter(ZONE_404), 403);
        }
        $pickup_location_coverage = $this->zoneService->getByPoints($pickup_point)->whereId($zone->id)->first();
        $destination_location_coverage = $this->zoneService->getByPoints($destination_point)->whereId($zone->id)->first();
        if (!$pickup_location_coverage || !$destination_location_coverage) {
            return response()->json(responseFormatter(ZONE_RESOURCE_404), 403);
        }
        $extraFare = $this->checkZoneExtraFare($zone);
        if (array_key_exists('bid', $request->all()) && $request['bid']) {
            $estimatedFare = $request['actual_fare'];
            $actualFare = $request['actual_fare'];
            $riseRequestCount = 1;
            $returnFee = $request->type == PARCEL ? $request->return_fee : 0;
            $cancellationFee = $request->type == PARCEL ? $request->cancellation_fee : 0;
        } elseif (!empty($extraFare)) {
            $estimatedFare = $request['extra_estimated_fare'];
            $actualFare = $request['extra_estimated_fare'];
            $riseRequestCount = 0;
            $returnFee = $request->type == PARCEL ? $request->extra_return_fee : 0;
            $cancellationFee = $request->type == PARCEL ? $request->extra_cancellation_fee : 0;
        } else {
            $estimatedFare = $request['estimated_fare'];
            $actualFare = $request['estimated_fare'];
            $riseRequestCount = 0;
            $returnFee = $request->type == PARCEL ? $request->return_fee : 0;
            $cancellationFee = $request->type == PARCEL ? $request->cancellation_fee : 0;
        }

        DB::beginTransaction();
        if ($request->trip_request_id) {
            $save_trip = $this->tripRequestservice->findOneBy(criteria: ['id' => $request['trip_request_id']]);
            $save_trip->estimated_fare = $estimatedFare;
            $save_trip->actual_fare = $actualFare;
            $save_trip->rise_request_count = $riseRequestCount;
            if ($save_trip->discount_id != null) {
                $save_trip->discount_id = null;
                $save_trip->discount_amount = null;
            }
            $save_trip->save();
        } else {
            $customer_coordinates = json_decode($request['customer_coordinates'], true);
            $customer_point = new Point($customer_coordinates[0], $customer_coordinates[1]);
            $request->merge([
                'customer_id' => auth('api')->id(),
                'zone_id' => $zone->id,
                'pickup_coordinates' => $pickup_point,
                'destination_coordinates' => $destination_point,
                'estimated_fare' => $estimatedFare,
                'actual_fare' => $actualFare,
                'return_fee' => $returnFee,
                'cancellation_fee' => $cancellationFee,
                'customer_request_coordinates' => $customer_point,
                'rise_request_count' => $riseRequestCount
            ]);
            $save_trip = $this->tripRequestservice->createRideRequest(attributes: $request->all());
        }

        if ($request->bid) {
            $final = $this->tripRequestservice->findOneBy(criteria: ['id' => $save_trip->id], relations: ['driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
        } else {
            $tripDiscount = $this->tripRequestservice->findOneBy(criteria: ['id' => $save_trip->id]);
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $estimatedAmount = $tripDiscount->actual_fare / (1 + ($vat_percent / 100));
            $discount = $this->getEstimatedDiscount(user: $tripDiscount->customer, zoneId: $tripDiscount->zone_id, tripType: $tripDiscount->type, vehicleCategoryId: $tripDiscount->vehicle_category_id, estimatedAmount: $estimatedAmount);
            if ($discount['discount_amount'] != 0) {
                $save_trip->discount_amount = $discount['discount_amount'];
                $save_trip->discount_id = $discount['discount_id'];
                $save_trip->save();
            }
            $final = $this->tripRequestservice->findOneBy(criteria: ['id' => $tripDiscount->id], relations: ['driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
        }
        $search_radius = (double)get_cache('search_radius') ?? 5;
        $find_drivers = $this->tripRequestservice->findNearestDrivers(
            latitude: $pickup_coordinates[0] ?? $final->coordinate->pickup_coordinates->latitude,
            longitude: $pickup_coordinates[1] ?? $final->coordinate->pickup_coordinates->longitude,
            zoneId: $zone->id,
            radius: $search_radius,
            vehicleCategoryId: $request->vehicle_category_id ?? $final->vehicle_category_id,
            requestType: $request->type ?? $final->type,
            parcelWeight: $request->weight ?? null
        );
        if (!empty($find_drivers)) {
            $notify = [];
            foreach ($find_drivers as $key => $value) {
                if ($value->user?->fcm_token) {
                    $notify[$key]['user_id'] = $value->user->id;
                    $notify[$key]['trip_request_id'] = $final->id;
                }

            }
            $requestType = $final->type == PARCEL ? 'parcel_request' : RIDE_REQUEST;
            $push = getNotification('new_' . $requestType);
            $notification = [
                'title' => translate($push['title']),
                'description' => translate($push['description']),
                'status' => $push['status'],
                'ride_request_id' => $final->id,
                'type' => $final->type,
                'action' => $push['action'],
            ];
            if (!empty($notify)) {
                dispatch(new SendPushNotificationJob($notification, $find_drivers))->onQueue('high');
                $this->tempTripNotificationService->createMany($notify);
            }
            if (checkReverbConnection()) {
                foreach ($find_drivers as $key => $value) {
                    CustomerTripRequestEvent::broadcast($value->user, $final);
                }
            }
        }
        DB::commit();
        if (!is_null(businessConfig('server_key', NOTIFICATION_SETTINGS))) {
            sendTopicNotification(
                'admin_notification',
                translate('new_request_notification'),
                translate('new_request_has_been_placed'),
                'null',
                $final->id,
                $request->type);
        }
        //Trip API resource
        $trip = new TripRequestResource($final);

        return response()->json(responseFormatter(TRIP_REQUEST_STORE_200, $trip));
    }

    public function getEstimatedFare(GetEstimatedFaresOrNotRequest $request): JsonResponse
    {
        $trip = $this->tripRequestservice->getIncompleteRide();
        if ($request->type == "ride_request" && $trip) {
            return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
        }

        $zoneId = $request->header('zoneId');
        $requestedZone = $this->zoneService->findOne(id: $zoneId);
        if (!$requestedZone) {
            return response()->json(responseFormatter(ZONE_404), 403);
        }

        $user = auth('api')->user();
        $pickupCoordinates = json_decode($request->pickup_coordinates, true);
        $destinationCoordinates = json_decode($request->destination_coordinates, true);
        $pickupPoint = new Point($pickupCoordinates[0], $pickupCoordinates[1]);
        $destinationPoint = new Point($destinationCoordinates[0], $destinationCoordinates[1]);
        $intermediateCoordinates = [];
        if (!is_null($request['intermediate_coordinates'])) {
            $intermediateCoordinates = json_decode($request->intermediate_coordinates, true);
            $maximumIntermediatePoint = 2;
            if (count($intermediateCoordinates) > $maximumIntermediatePoint) {

                return response()->json(responseFormatter(MAXIMUM_INTERMEDIATE_POINTS_403), 403);
            }
        }
        $zone = $this->zoneService->getByPoints($pickupPoint)->where('is_active', 1)->first();
        if (!$zone) {
            return response()->json(responseFormatter(ZONE_404), 403);
        }

        $pickupLocationCoverage = $this->zoneService->getByPoints($pickupPoint)->where('id', $zone->id)->first();

        $destinationLocationCoverage = $this->zoneService->getByPoints($destinationPoint)->whereId($zone->id)->first();

        if (!$pickupLocationCoverage || !$destinationLocationCoverage) {
            return response()->json(responseFormatter(ZONE_RESOURCE_404), 403);
        }

        if ($request->type == 'ride_request') {
            $tripFare = $this->tripFareService->getBy(criteria: ['zone_id' => $zone->id], relations: ['zone', 'vehicleCategory']);
            $tripFare = $tripFare->filter(function ($item) {
                return $item->vehicleCategory !== null && $item->vehicleCategory->is_active != 0;
            });
            $tripFare = $tripFare->values();
            $availableCategories = $tripFare->map(function ($query) {
                return $query->vehicleCategory->type;
            })->unique()->toArray();
            if (empty($availableCategories)) {
                return response()->json(responseFormatter(NO_ACTIVE_CATEGORY_IN_ZONE_404), 403);
            }
        } else {
            $parcelWeights = $this->parcelWeightService->getBy(limit: 9999, offset: 1);
            $parcelWeightId = null;
            $parcelCategoryId = $request->parcel_category_id;
            foreach ($parcelWeights as $parcelWeight) {
                if ($request->parcel_weight >= $parcelWeight->min_weight && $request->parcel_weight <= $parcelWeight->max_weight) {
                    $parcelWeightId = $parcelWeight['id'];
                }
            }
            if (is_null($parcelWeightId)) {
                return response()->json(responseFormatter(PARCEL_WEIGHT_400), 403);
            }

            $relations = [
                'fares' => [
                    ['parcel_weight_id', '=', $parcelWeightId],
                    ['zone_id', '=', $zone->id],
                    ['parcel_category_id', '=', $parcelCategoryId],
                ],
                'zone' => []
            ];
            $whereHasRelations = [
                'fares' => [
                    'parcel_weight_id' => $parcelWeightId,
                    'zone_id' => $zone->id,
                    'parcel_category_id' => $parcelCategoryId,
                ]
            ];
            $tripFare = $this->parcelFareService->findOneBy(criteria: ['zone_id' => $zone->id], whereHasRelations: $whereHasRelations, relations: $relations);
        }
        $getRoutes = getRoutes(
            originCoordinates: $pickupCoordinates,
            destinationCoordinates: $destinationCoordinates,
            intermediateCoordinates: $intermediateCoordinates,
            drivingMode: $request->type == 'ride_request' ? (count($availableCategories) == 2 ? ["DRIVE", 'TWO_WHEELER'] : ($availableCategories[0] == 'car' ? ['DRIVE'] : ['TWO_WHEELER'])) : ['TWO_WHEELER'],
        );

        if ($getRoutes[1]['status'] !== "OK") {
            return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, $getRoutes[1]['error_detail']), 403);
        }

        $estimatedFare = $this->estimatedFare(
            tripRequest: $request->all(),
            routes: $getRoutes,
            zone_id: $zone->id,
            zone: $zone,
            tripFare: $tripFare,
            beforeCreate: true
        );
        $pickup_point = DB::raw("ST_GeomFromText('POINT({$pickupCoordinates[0]} {$pickupCoordinates[1]})', 4326)");
        $destination_point = DB::raw("ST_GeomFromText('POINT({$destinationCoordinates[0]} {$destinationCoordinates[1]})', 4326)");

        $this->recentAddressService->create(data: [
            'user_id' => $user?->id,
            'zone_id' => $zone->id,
            'pickup_coordinates' => $pickup_point,
            'destination_coordinates' => $destination_point,
            'pickup_address' => $request->pickup_address,
            'destination_address' => $request->destination_address,
        ]);

        return response()->json(responseFormatter(DEFAULT_200, $estimatedFare), 200);
    }

    public function rideList(RideListRequest $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filter' => Rule::in([TODAY, PREVIOUS_DAY, THIS_WEEK, LAST_WEEK, LAST_7_DAYS, THIS_MONTH, LAST_MONTH, THIS_YEAR, ALL_TIME, CUSTOM_DATE]),
            'status' => Rule::in([ALL, PENDING, ONGOING, COMPLETED, CANCELLED, RETURNED]),
            'start' => 'required_if:filter,==,custom_date|required_with:end',
            'end' => 'required_if:filter,==,custom_date|required_with:end',
            'limit' => 'required|numeric',
            'offset' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        if (!is_null($request->filter) && $request->filter != CUSTOM_DATE) {
            $date = getDateRange($request->filter);
        } elseif (!is_null($request->filter)) {
            $date = getDateRange([
                'start' => $request->start,
                'end' => $request->end
            ]);
        }

        $criteria = ['customer_id' => auth('api')->id()];
        $whereBetweenCriteria = [];
        if (!empty($date)) {
            $whereBetweenCriteria = ['created_at', [$date['start'], $date['end']]];
        }
        if (!is_null($request->status)) {
            $criteria['current_status'] = [$request->status];
        }

        $relations = ['driver', 'vehicle.model', 'vehicleCategory', 'time', 'coordinate', 'fee'];
        $data = $this->tripRequestservice->getWithAvg(
            criteria: $criteria,
            relations: $relations,
            orderBy: ['id' => 'desc'],
            limit: $request['limit'],
            offset: $request['offset'],
            withAvgRelation: ['driverReceivedReviews', 'rating'],
            whereBetweenCriteria: $whereBetweenCriteria
        );
        $resource = TripRequestResource::setData('distance_wise_fare')::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $resource, limit: $request['limit'], offset: $request['offset']));
    }

    public function rideDetails($trip_request_id): JsonResponse
    {
        $criteria = ['id' => $trip_request_id];
        $relations = ['driver', 'vehicle.model', 'vehicleCategory', 'tripStatus',
            'coordinate', 'fee', 'time', 'parcel', 'parcelUserInfo', 'parcelRefund'];
        $withAvgRelation = ['driverReceivedReviews', 'rating'];

        $data = $this->tripRequestservice->findOneWithAvg(criteria: $criteria, relations: $relations, withAvgRelation: $withAvgRelation);
        if (!$data) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        $resource = TripRequestResource::make($data->append('distance_wise_fare'));
        return response()->json(responseFormatter(DEFAULT_200, $resource));
    }

    public function biddingList($trip_request_id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $tripRequest = $this->tripRequestservice->findOneBy(criteria: ['id' => $trip_request_id]);

        if (!$tripRequest) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($tripRequest->current_status == PENDING) {
            $bidding = $this->fareBiddingService->getWithAvg(
                criteria: ['trip_request_id' => $trip_request_id],
                relations: ['driver_last_location', 'driver', 'trip_request', 'driver.vehicle.model'],
                limit: $request['limit'],
                offset: $request['offset'],
                withAvgRelation: ['driverReceivedReviews', 'rating']
            );
            $bidding = FareBiddingResource::collection($bidding);

            return response()->json(responseFormatter(constant: DEFAULT_200, content: $bidding, limit: $request['limit'], offset: $request['offset']));
        }

        return response()->json(responseFormatter(constant: DEFAULT_200, content: []));
    }


    public function driversNearMe(Request $request): JsonResponse
    {
        if (is_null($request->header('zoneId'))) {

            return response()->json(responseFormatter(ZONE_404));
        }

        $driverList = $this->tripRequestservice->allNearestDrivers(
            latitude: $request->latitude,
            longitude: $request->longitude,
            zoneId: $request->header('zoneId'),
            radius: (float)(get_cache('search_radius') ?? 5)
        );
        $lastLocationDriver = LastLocationResource::collection($driverList);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $lastLocationDriver));
    }

    public function finalFareCalculation(Request $request): JsonResponse
    {
        $trip = $this->tripRequestservice->findOne(
            id: $request['trip_request_id'],
            relations: ['vehicleCategory.tripFares', 'customer', 'driver', 'coupon', 'discount', 'time', 'coordinate', 'fee', 'tripStatus', 'parcelRefund']
        );
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->current_status != 'completed' && $trip->current_status != 'cancelled' && $trip->type == 'ride_request') {

            return response()->json(responseFormatter(constant: TRIP_STATUS_NOT_COMPLETED_200));
        }
        if ($trip->customer_id != auth('api')->id() && $trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 403);
        }
        if (($trip->discount_amount != null && $trip->discount_amount > 0 && $trip->actual_fare == $trip->discount_amount) || $trip->paid_fare != 0) {
            $tripData = new TripRequestResource($trip->append('distance_wise_fare'));
            return response()->json(responseFormatter(constant: DEFAULT_200, content: $tripData));
        }
        if ($trip->type == 'ride_request') {
            $fare = $trip->vehicleCategory->tripFares->where('zone_id', $trip->zone_id)->first();
            if (!$fare) {
                return response()->json(responseFormatter(ZONE_404), 403);
            }
        } else {
            $fare = null;
        }
        DB::beginTransaction();
        $calculated_data = $this->calculateFinalFare($trip, $fare);
        $attributes = [
            'extra_fare_amount' => round($calculated_data['extra_fare_amount'], 2),
            'paid_fare' => round($calculated_data['final_fare'], 2),
            'actual_fare' => round($calculated_data['actual_fare'], 2),
            'actual_distance' => $calculated_data['actual_distance'],
        ];
        $this->tripRequestservice->update(id: $request->trip_request_id, data: $attributes);
        $trip->refresh();
        $bidOnFare = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $trip->trip_request_id, 'is_ignored' => 0]);
        $response = $this->getFinalCouponDiscount(user: $trip->customer, trip: $trip);
        if ($response['discount'] != 0) {
            $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) - $response['discount'];
            $vat = ($vat_percent * $final_fare_without_tax) / 100;
            $admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
            $attributes = [
                'coupon_id' => $response->coupon_id,
                'coupon_amount' => $response->discount,
                'paid_fare' => $final_fare_without_tax + $vat + $trip->fee->tips,
            ];
            $trip->fee()->update([
                'vat_tax' => $vat,
                'admin_commision' => $admin_commission
            ]);
            $this->tripRequestservice->update(id: $trip->id, data: $attributes);
            $trip->refresh();
            $this->updateCouponCount($response['coupon'], $response['discount']);
        }
        if (($bidOnFare || $trip->rise_request_count > 0) && $trip->type == 'ride_request') {
        } else {
            $attributes = [
                'discount_id' => null,
                'discount_amount' => null,
            ];
            $this->tripRequestservice->update(id: $trip->id, data: $attributes);
            $trip->refresh();
            $response = $this->getFinalDiscount(user: $trip->customer, trip: $trip);
            if ($response['discount_amount'] != 0) {
                $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
                $vat_percent = (double)get_cache('vat_percent') ?? 1;
                $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) - $response['discount_amount'];
                $vat = ($vat_percent * $final_fare_without_tax) / 100;
                $admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
                $finalAttributes = [
                    'discount_id' => $response['discount_id'],
                    'discount_amount' => $response['discount_amount'],
                    'paid_fare' => $final_fare_without_tax + $vat + $trip->fee->tips,
                ];
                $trip->fee()->update([
                    'vat_tax' => $vat,
                    'admin_commission' => $admin_commission
                ]);
                $this->tripRequestservice->update(id: $trip->id, data: $finalAttributes);
                $trip->refresh();
                if ($response['discount_id'] != null) {
                    $this->updateDiscountCount($response['discount_id'], $response['discount_amount']);
                }
            }
        }
        DB::commit();
        $amount = $trip->paid_fare + $trip->return_fee;
        $updatedPaidFare = $trip->paid_fare + $trip->return_fee;
        if ($trip->type == PARCEL && $trip->current_status == RETURNING && $trip?->parcel?->payer == "receiver") {
            $this->tripRequestservice->update(id: $trip->id, data: [
                'paid_fare' => $updatedPaidFare,
                'due_amount' => $amount
            ]);
            $trip->refresh();
        }
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
        $trip = new TripRequestResource($trip->append('distance_wise_fare'));

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }


    public function requestAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'driver_id' => 'required',
            'action' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->tripRequestservice->findOneBy(['id' => $request->trip_request_id], relations: ['coordinate']);
        $driver = $this->userService->findOneBy(criteria: ['id' => $request->driver_id], relations: ['vehicle', 'driverDetails', 'lastLocations']);
        if (Cache::get($request['trip_request_id']) == ACCEPTED && $trip->driver_id == $driver->id) {
            return response()->json(responseFormatter(DEFAULT_UPDATE_200));
        }
        $user_status = $driver->driverDetails->availability_status;
        if ($user_status != 'on_bidding' && $user_status != 'available') {
            return response()->json(responseFormatter(constant: DRIVER_403), 403);
        }
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if (!$driver->vehicle) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 403);
        }
        if (get_cache('bid_on_fare') ?? 0) {
            $checkBid = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id, 'driver_id' => $request->driver_id]);

            if (!$checkBid) {
                return response()->json(responseFormatter(constant: DRIVER_BID_NOT_FOUND_403), 403);
            }

        }
        $env = env('APP_MODE');
        $otp = $env != "live" ? '0000' : rand(1000, 9999);

        $attributes = [
            'driver_id' => $driver->id,
            'otp' => $otp,
            'vehicle_id' => $driver->vehicle->id,
            'current_status' => ACCEPTED,
            'vehicle_category_id' => $driver->vehicle->category_id,
        ];

        if ($request['action'] == ACCEPTED) {
            DB::beginTransaction();
            Cache::put($trip->id, ACCEPTED, now()->addHour());
            $driverDetails = $this->driverDetailService->findOneBy(criteria: ['user_id' => $driver->id]);
            $this->driverDetailService->update(id: $driverDetails->id, data: [
                'ride_count' => $trip->type == RIDE_REQUEST ? $driverDetails->ride_count + 1 : 0,
                'parcel_count' => $trip->type == PARCEL ? ++$driverDetails->parcel_count : 0,
            ]);
            $this->rejectedDriverRequestService->deleteBy(criteria: ['trip_request_id' => $trip->id]);
            if (get_cache('bid_on_fare') ?? 0) {
                $all_bidding = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id, 'driver_id' => $request->driver_id]);

                if ($all_bidding) {
                    $attributes['actual_fare'] = $all_bidding->bid_fare;
                    $attributes['estimated_fare'] = $all_bidding->bid_fare;
                }
            }
            $data = $this->tempTripNotificationService->getBy(criteria: ['trip_request_id' => $request->trip_request_id, ['user_id', '!=', $driver->id]], relations: ['user']);
            $push = getNotification('another_driver_assigned');
            if (!empty($data)) {
                $notification['title'] = translate($push['title']);
                $notification['description'] = translate($push['description']);
                $notification['status'] = $push['status'];
                $notification['ride_request_id'] = $trip->id;
                $notification['type'] = $trip->type;
                $notification['action'] = $push['action'];

                dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
                $this->tempTripNotificationService->deleteBy(criteria: ['trip_request_id' => $trip->id]);
            }

            $driver_arrival_time = getRoutes(
                originCoordinates: [
                    $trip->coordinate->pickup_coordinates->latitude,
                    $trip->coordinate->pickup_coordinates->longitude
                ],
                destinationCoordinates: [
                    $driver->lastLocations->latitude,
                    $driver->lastLocations->longitude
                ],
            );
            if ($driver_arrival_time[1]['status'] !== "OK") {
                return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, $driver_arrival_time[1]['error_detail']), 403);
            }
            if ($trip->type == 'ride_request') {
                $attributes['driver_arrival_time'] = (double)($driver_arrival_time[0]['duration']) / 60;
            }
            $this->tripRequestservice->update(id: $trip->id, data: $attributes);
            $trip->refresh();
            $trip->tripStatus()->update([$attributes['current_status'] => now()]);
            $trip->time()->update(['driver_arrival_time' => $attributes['driver_arrival_time']]);
            DB::commit();
            if (get_cache('bid_on_fare') ?? 0) {
                $acceptDriverBid = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request['trip_request_id'], 'driver_id' => $request['driver_id']]);
                $all_bidding = $this->fareBiddingService->getBy(criteria: ['trip_request_id' => $request['trip_request_id'], ['id', '!=', $acceptDriverBid->id]]);
                if ($all_bidding->isNotEmpty()) {
                    $this->fareBiddingService->deleteBy(criteria: ['trip_request_id' => $request['trip_request_id'], ['id' => $all_bidding->id]]);
                }
            }
            $push = getNotification('bid_accepted');
            sendDeviceNotification(fcm_token: $driver->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: $push['action'],
                user_id: $driver->id);
        } else {
            if (get_cache('bid_on_fare') ?? 0) {
                $all_bidding = $this->fareBiddingService->getBy(criteria: ['trip_request_id' => $request['trip_request_id']]);

                if (count($all_bidding) > 0) {
                    $this->fareBiddingService->deleteBy(criteria: ['trip_request_id' => $request['trip_request_id'], ['id' => $all_bidding->id]]);
                }

            }
        }
        return response()->json(responseFormatter(constant: BIDDING_ACTION_200));
    }


    public function rideResumeStatus(): JsonResponse
    {
        $trip = $this->tripRequestservice->getIncompleteRide();
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        $trip = TripRequestResource::make($trip);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

    public function pendingParcelList(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $data = $this->tripRequestservice->getPendingParcel(data: array_merge($validator->validated(), ['user_column' => 'customer_id']));
        $trips = TripRequestResource::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    public function applyCoupon(Request $request): JsonResponse
    {

        $trip = $this->tripRequestservice->findOne(id: $request->trip_request_id, relations: ['driver', 'fee']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->coupon_id) {

            return response()->json(responseFormatter(constant: COUPON_APPLIED_403), 403);
        }
        $user = auth('api')->user();
        $date = date('Y-m-d');

        $criteria = [
            ['coupon_code', $request->coupon_code],
            ['min_trip_amount', '<=', $trip->paid_fare],
            ['start_date', '<=', $date],
            ['end_date', '>=', $date]
        ];
        $coupon = $this->couponService->findOneBy($criteria);
        if (!$coupon) {

            return response()->json(responseFormatter(constant: COUPON_404, content: ['discount' => 0]), 403);
        }
        $response = $this->getCouponDiscount($user, $trip, $coupon);

        if ($response['discount'] != 0) {

            $trip = $this->tripRequestservice->validateDiscount(trip: $trip, response: $response, tripId: $request->trip_request_id, cuponId: $coupon->id);

            return response()->json(responseFormatter(constant: $response['message'], content: $trip));
        }

        return response()->json(responseFormatter(constant: $response['message'], content: $trip), 403);
    }

    public function rideStatusUpdate($trip_request_id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->tripRequestservice->findOne(id: $trip_request_id, relations: ['driver', 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->current_status == CANCELLED) {
            return response()->json(responseFormatter(TRIP_STATUS_CANCELLED_403), 403);
        }
        if ($trip->current_status == COMPLETED) {
            return response()->json(responseFormatter(TRIP_STATUS_COMPLETED_403), 403);
        }
        if ($trip->current_status == RETURNING) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNING_403), 403);
        }

        $attributes = [
            'trip_status' => $request['status'],
            'trip_cancellation_reason' => $request['cancel_reason'] ?? null
        ];

        if ($request->status == 'cancelled' && ($trip->current_status == ACCEPTED || $trip->current_status == PENDING)) {

            //referral
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

            $data = $this->tempTripNotificationService->findOneBy(criteria: [
                'trip_request_id' => $request['trip_request_id']
            ], relations: ['user']);
            $push = getNotification('customer_canceled_trip');
            if (!empty($data)) {
                if ($trip->driver_id) {
                    if (!is_null($trip->driver->fcm_token)) {
                        sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
                            title: translate($push['title']),
                            description: translate(textVariableDataFormat(value: $push['description'])),
                            status: $push['status'],
                            ride_request_id: $request['trip_request_id'],
                            type: $trip->type,
                            action: $push['action'],
                            user_id: $trip->driver->id
                        );
                    }
                    try {
                        checkPusherConnection(CustomerTripCancelledEvent::broadcast($trip->driver, $trip));
                    } catch (\Exception $exception) {
                    }
                    $this->driverDetailService->updateAvailability(data: ['user_id' => $trip->driver_id, 'trip_type' => $trip->type]);
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
                    foreach ($data as $tempNotification) {
                        try {
                            checkPusherConnection(CustomerTripCancelledEvent::broadcast($tempNotification->user, $trip));
                        } catch (\Exception $exception) {

                        }
                    }
                }
                $this->tempTripNotificationService->deleteBy(criteria: ['trip_request_id' => $trip->id]);
            }
        }
        if ($trip->is_paused) {

            return response()->json(responseFormatter(TRIP_REQUEST_PAUSED_404), 403);
        }

        if ($trip->driver_id && ($request->status == 'completed' || $request->status == 'cancelled') && $trip->current_status == ONGOING) {
            if ($request->status == 'cancelled') {
                $attributes['fee']['cancelled_by'] = 'customer';
            }
            $attributes['coordinate']['drop_coordinates'] = new Point($trip->driver->lastLocations->latitude, $trip->driver->lastLocations->longitude);
            $this->driverDetailService->updateAvailability(data: ['user_id' => $trip->driver_id, 'trip_type' => $trip->type]);
            $tripType = $trip->type == RIDE_REQUEST ? 'trip' : PARCEL;
            if ($request->status == 'cancelled' && $trip->type == PARCEL) {
                $push = getNotification($tripType . '_canceled');
                if (!is_null($trip->driver->fcm_token)) {
                    sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
                        title: translate($push['title']),
                        description: translate(textVariableDataFormat(value: $push['description'])),
                        status: $push['status'],
                        ride_request_id: $request['trip_request_id'],
                        type: $trip->type,
                        action: $push['action'],
                        user_id: $trip->driver->id
                    );
                }
            } else {
                $push = getNotification($tripType . '_completed');
                if (!is_null($trip->driver->fcm_token)) {
                    sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
                        title: translate($push['title']),
                        description: translate(textVariableDataFormat(value: $push['description'])),
                        status: $push['status'],
                        ride_request_id: $request['trip_request_id'],
                        type: $trip->type,
                        action: $push['action'],
                        user_id: $trip->driver->id
                    );
                }
                try {
                    checkPusherConnection(CustomerTripCancelledAfterOngoingEvent::broadcast($trip));
                } catch (\Exception $exception) {
                }
            }
        }
        DB::beginTransaction();
        if ($attributes['trip_status'] ?? null) {
            $this->tripRequestservice->update(id: $trip->id, data: ['current_status' => $attributes['trip_status']]);
            $trip->tripStatus()->update([$attributes['trip_status'] => now()]);
            $trip->refresh();
        }
        if ($attributes['trip_cancellation_reason'] ?? null) {
            $this->tripRequestservice->update(id: $trip->id, data: ['trip_cancellation_reason' => $attributes['trip_cancellation_reason']]);
            $trip->refresh();
        }
        if ($attributes['driver_id'] ?? null) {
            $this->tripRequestservice->update(id: $trip->id, data: ['driver_id' => null]);
            $trip->refresh();
        }
        if ($attributes['coordinate'] ?? null) {
            $coordinate = $trip->coordinate;
            if ($coordinate) {
                $coordinate->update([
                    'drop_coordinates' => $attributes['coordinate']['drop_coordinates'],
                ]);
            }
        }

        if ($attributes['fee'] ?? null) {
            $trip->fee()->update($attributes['fee']);
        }
        if (($request->status == 'cancelled' || $request->status == 'completed') && $trip->driver_id && $trip->current_status == ONGOING) {
            $this->customerLevelUpdateChecker(auth()->user());
            $this->driverLevelUpdateChecker($trip->driver);
        }
        DB::commit();
        if ($trip->driver_id && $request->status == 'cancelled' && $trip->current_status == ONGOING && $trip->type == PARCEL) {
            $env = env('APP_MODE');
            $otp = $env != "live" ? '0000' : rand(1000, 9999);
            $attributes = [];
            $attributes['otp'] = $otp;
            if ($trip?->parcel?->payer == SENDER) {
                $attributes['paid_fare'] = ($attributes['paid_fare'] ?? 0) + $trip->return_fee;;
                $attributes['due_amount'] = $trip->return_fee;
                $attributes['payment_status'] = PARTIAL_PAID;
            }
            $attributes['current_status'] = RETURNING;
            $parcelReturnTimeFeeStatus = businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false;
            $time = (int)businessConfig('return_time_for_driver', PARCEL_SETTINGS)?->value;
            $timeType = businessConfig('return_time_type_for_driver', PARCEL_SETTINGS)?->value;
            if ($parcelReturnTimeFeeStatus) {
                if ($timeType === 'hour') {
                    $attributes['return_time'] = Carbon::now()->addHours($time);
                } else {
                    $attributes['return_time'] = Carbon::now()->addDays($time);
                }
            }
            $this->tripRequestservice->update(id: $trip->id, data: $attributes);
            $trip->refresh();
            $trip->tripStatus()->update([
                RETURNING => now()
            ]);
        }

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }

    public function cancelCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $trip = $this->tripRequestservice->findOne(id: $request->trip_request_id, relations: ['driver']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if (is_null($trip->coupon_id)) {
            return response()->json(responseFormatter(constant: COUPON_404), 403);
        }

        DB::beginTransaction();
        $this->tripRequestservice->removeCouponData($trip);
        DB::commit();

        $push = getNotification('coupon_removed');
        sendDeviceNotification(
            fcm_token: $trip->driver->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->driver->id
        );

        $trip = new TripRequestResource($trip->append('distance_wise_fare'));
        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200, content: $trip));
    }

    public function ignoreBidding(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bidding_id' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $bidding = $this->fareBiddingService->findOneBy(criteria: ['id' => $request->bidding_id]);
        if (!$bidding) {

            return response()->json(responseFormatter(constant: DRIVER_BID_NOT_FOUND_403), 403);
        }
        $this->fareBiddingService->update(id: $request->bidding_id, data: ['is_ignored' => 1]);
        if ($bidding->driver_id) {
            if (!is_null($bidding->driver->fcm_token)) {
                $push = getNotification('customer_rejected_bid');
                sendDeviceNotification(fcm_token: $bidding->driver->fcm_token,
                    title: translate($push['title']),
                    description: translate(textVariableDataFormat(value: $push['description'], tripId: $bidding->trip_request->ref_id)),
                    status: $push['status'],
                    ride_request_id: $bidding->trip_request_id,
                    type: $bidding->trip_request_id,
                    action: $push['action'],
                    user_id: $bidding->driver->id
                );
            }
        }

        return response()->json(responseFormatter(constant: DEFAULT_200));
    }

    public function arrivalTime(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $time = $this->tripRequestTimeService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        if (!$time) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        $this->tripRequestTimeService->update(id: $time->id, data: ['customer_arrives_at' => now()]);

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }

    public function storeScreenshot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'file' => 'required|mimes:jpg,png,webp'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $this->tripRequestservice->update(id: $request->trip_request_id, data: [
            'map_screenshot' => $request->file,
        ],);

        return response()->json(responseFormatter(DEFAULT_200));
    }

    public function unpaidParcelRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $relations = ['customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
            'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo', 'parcelRefund'];

        $criteria = [
            'customer_id' => auth('api')->id(),
            'type' => PARCEL,
            'payment_status' => UNPAID,
            ['driver_id', '!=', NULL]
        ];

        $whereHasRelations = [
            'parcel' => ['payer' => SENDER]
        ];

        $data = $this->tripRequestservice->getBy(criteria: $criteria, whereHasRelations: $whereHasRelations, relations: $relations, limit: $request->limit, offset: $request->offset);
        $trips = TripRequestResource::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    public function coordinateArrival(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'is_reached' => 'required|in:coordinate_1,coordinate_2,destination',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->tripRequestCoordinateService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        $data = match ($request->is_reached) {
            'coordinate_1' => ['is_reached_1' => true],
            'coordinate_2' => ['is_reached_2' => true],
            'destination' => ['is_reached_destination' => true],
        };
        $this->tripRequestCoordinateService->update(id: $trip->id, data: $data);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));

    }

    public function receivedReturningParcel($trip_request_id): JsonResponse
    {
        $trip = $this->tripRequestservice->findOneBy(criteria: ['id' => $trip_request_id], relations: ['driver', 'driver.driverDetails', 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund', 'parcel']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->current_status == RETURNED) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNED_403), 403);
        }
        DB::beginTransaction();
        if ($trip?->fee?->cancelled_by == CUSTOMER && $trip?->parcel?->payer == 'sender' && $trip->due_amount > 0) {
            $this->cashReturnFeeTransaction($trip);
        }
        if ($trip?->fee?->cancelled_by == CUSTOMER && $trip?->parcel?->payer == 'receiver' && $trip->due_amount > 0) {
            $this->cashTransaction($trip, true);
            $this->cashReturnFeeTransaction($trip);
        }
        if ($trip?->fee?->cancelled_by == CUSTOMER) {
            $trip->payment_status = PAID;
        }
        $trip->due_amount = 0;
        $trip->current_status = RETURNED;
        $trip->save();
        $trip->tripStatus()->update([
            RETURNED => now()
        ]);
        DB::commit();
        $this->returnTimeExceedFeeTransaction($trip);
        $driverDetails = $this->driverDetailService->findOneBy(criteria: ['user_id' => $trip->driver_id]);
        --$driverDetails->parcel_count;
        $driverDetails->save();
        $push = getNotification('parcel_returned');
        sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $trip_request_id,
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->driver->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }
}
