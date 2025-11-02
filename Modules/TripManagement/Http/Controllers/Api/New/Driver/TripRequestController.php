<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Driver;


use App\Events\AnotherDriverTripAcceptedEvent;
use App\Events\DriverTripAcceptedEvent;
use App\Events\DriverTripCancelledEvent;
use App\Events\DriverTripCompletedEvent;
use App\Events\DriverTripStartedEvent;
use App\Jobs\SendPushNotificationJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Gateways\Traits\Payment;
use Modules\Gateways\Traits\SmsGatewayForMessage;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Entities\TempTripNotification;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\CommonTrait;
use Modules\TripManagement\Lib\CouponCalculationTrait;
use Modules\TripManagement\Service\Interface\FareBiddingServiceInterface;
use Modules\TripManagement\Service\Interface\RejectedDriverRequestServiceInterface;
use Modules\TripManagement\Service\Interface\TempTripNotificationServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestCoordinateServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestTimeServiceInterface;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Service\Interface\DriverDetailServiceInterface;
use Modules\UserManagement\Service\Interface\UserLastLocationServiceInterface;
use Modules\UserManagement\Service\Interface\UserServiceInterface;

class TripRequestController extends Controller
{

    use CommonTrait, TransactionTrait, Payment, CouponCalculationTrait, LevelHistoryManagerTrait, SmsGatewayForMessage;

    protected $tripRequestService;
    protected $tripRequestTimeService;
    protected $tripRequestCoordinateService;
    protected $userLastLocationService;
    protected $userService;
    protected $driverDetailService;
    protected $tempTripNotificationService;
    protected $fareBiddingService;
    protected $rejectedDriverRequestService;

    public function __construct(
        TripRequestServiceInterface           $tripRequestService,
        TripRequestTimeServiceInterface       $tripRequestTimeService,
        TripRequestCoordinateServiceInterface $tripRequestCoordinateService,
        UserLastLocationServiceInterface      $userLastLocationService,
        UserServiceInterface                  $userService,
        DriverDetailServiceInterface          $driverDetailService,
        TempTripNotificationServiceInterface  $tempTripNotificationService,
        FareBiddingServiceInterface           $fareBiddingService,
        RejectedDriverRequestServiceInterface $rejectedDriverRequestService,
    )
    {
        $this->tripRequestService = $tripRequestService;
        $this->tripRequestTimeService = $tripRequestTimeService;
        $this->tripRequestCoordinateService = $tripRequestCoordinateService;
        $this->userLastLocationService = $userLastLocationService;
        $this->userService = $userService;
        $this->driverDetailService = $driverDetailService;
        $this->tempTripNotificationService = $tempTripNotificationService;
        $this->fareBiddingService = $fareBiddingService;
        $this->rejectedDriverRequestService = $rejectedDriverRequestService;
    }

    public function showRideDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $relations = ['tripStatus', 'customer', 'driver', 'time', 'coordinate', 'time', 'fee', 'parcelRefund'];
        $criteria = ['type' => 'ride_request', 'driver_id' => auth('api')->id(), 'id' => $request->trip_request_id];
        $orderBy = ['created_at' => 'desc'];
        $withAvgRelations = [['customerReceivedReviews', 'rating']];
        $trip = $this->tripRequestService->findOneBy(criteria: $criteria, withAvgRelations: $withAvgRelations, relations: $relations, orderBy: $orderBy);

        if (!$trip || $trip->fee->cancelled_by == 'driver' || (!$trip->driver_id && $trip->current_status == 'cancelled') || ($trip->driver_id && $trip->payment_status == PAID)) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 404);
        }
        $trip = TripRequestResource::make($trip);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

    public function allRideList()
    {
        $trips = $this->tripRequestService->allRideList();
        if (!$trips) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404, content: $trips));
        }
        $data = TripRequestResource::collection($trips);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $data));
    }

    public function rideWaiting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'waiting_status' => 'required|in:pause,resume'
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $time = $this->tripRequestTimeService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer']);
        if (!$time) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        $this->tripRequestService->rideWaiting($trip, $time);

        $waitingStatus = $request->waiting_status == 'resume' ? 'resumed' : 'paused';
        $push = getNotification('trip_' . $waitingStatus);
        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }

    public function rideList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filter' => Rule::in([TODAY, PREVIOUS_DAY, THIS_WEEK, LAST_WEEK, LAST_7_DAYS, THIS_MONTH, LAST_MONTH, THIS_YEAR, ALL_TIME, CUSTOM_DATE]),
            'status' => Rule::in([ALL, PENDING, ONGOING, COMPLETED, CANCELLED, RETURNED, ACCEPTED]),
            'start' => 'required_if:filter,custom_date|required_with:end|date',
            'end' => 'required_if:filter,custom_date|required_with:start|date',
            'limit' => 'required|numeric|min:1',
            'offset' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $data = $this->tripRequestService->rideList(data: $validator->validated());
        $resource = TripRequestResource::setData('distance_wise_fare')::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $resource, limit: $request['limit'], offset: $request['offset']));
    }

    public function arrivalTime(Request $request)
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
        $this->tripRequestTimeService->updatedBy(criteria: ['trip_request_id' => $request->trip_request_id], data: ['driver_arrives_at' => now()]);

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }

    public function lastRideDetails()
    {
        $relations = ['fee', 'parcelRefund'];
        $lastRide = $this->tripRequestService->findOneBy(criteria: ['driver_id' => auth('api')->id(), 'type' => RIDE_REQUEST], relations: $relations, orderBy: ['created_at' => 'desc']);
        if (!$lastRide) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404, content: $lastRide));
        }
        $data = [];
        $data[] = TripRequestResource::make($lastRide->append('distance_wise_fare'));

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $data));
    }

    public function coordinateArrival(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'is_reached' => 'required|in:coordinate_1,coordinate_2,destination',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $tripCoordinate = $this->tripRequestCoordinateService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        $data = match ($request->is_reached) {
            'coordinate_1' => ['is_reached_1' => true],
            'coordinate_2' => ['is_reached_2' => true],
            'destination' => ['is_reached_destination' => true],
        };
        $this->tripRequestCoordinateService->update(id: $tripCoordinate->id, data: $data);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }

    public function pendingParcelList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $data = $this->tripRequestService->getPendingParcel(data: array_merge($validator->validated(), ['user_column' => 'driver_id']));

        $trips = TripRequestResource::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    public function unpaidParcelRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $relations = ['customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
            'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo'];
        $criteria = [
            'driver_id' => auth('api')->id(),
            'type' => PARCEL,
            'payment_status' => UNPAID,
            ['driver_id', '!=', NULL]
        ];
        $whereHasRelations = [
            'parcel' => ['payer' => SENDER]
        ];
        $data = $this->tripRequestService->getBy(criteria: $criteria, whereHasRelations: $whereHasRelations, relations: $relations, limit: $request->limit, offset: $request->offset);
        $trips = TripRequestResource::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }

        $push = getNotification('parcel_returning_otp');
        sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'], otp: $trip->otp)),
            status: $push['status'],
            ride_request_id: $request->trip_request_id,
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }

    public function matchOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'otp' => Rule::requiredIf(function () {
                return (bool)businessConfig(key: 'driver_otp_confirmation_for_trip', settingsType: TRIP_SETTINGS)?->value == 1;
            }), 'min:4|max:4',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer', 'coordinate']);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        if (array_key_exists('otp', $request->all()) && $request['otp'] && $trip->otp !== $request['otp']) {

            return response()->json(responseFormatter(OTP_MISMATCH_404), 403);
        }

        $data = [
            'current_status' => ONGOING
        ];

        DB::beginTransaction();
        $this->tripRequestService->updatedBy(criteria: ['id' => $request->trip_request_id], data: $data);
        $trip->tripStatus()->update(['ongoing' => now()]);

        if ($trip->customer->fcm_token) {
            $push = getNotification('trip_started');
            sendDeviceNotification(
                fcm_token: $trip->customer->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip['type'],
                action: $push['action'],
                user_id: $trip->customer->id
            );
        }
        try {
            checkPusherConnection(DriverTripStartedEvent::broadcast($trip));
        } catch (\Exception $exception) {

        }
        DB::commit();

        return response()->json(responseFormatter(DEFAULT_STORE_200));
    }

    public function trackLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required',
            'longitude' => 'required',
            'zoneId' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $data = [
            'type' => $request->route()->getPrefix() == "api/customer/ride" ? 'customer' : 'driver',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'zone_id' => $request->zoneId
        ];
        $this->userLastLocationService->updatedBy(criteria: ['user_id' => auth('api')->id()], data: $data);

        return response()->json(responseFormatter(DEFAULT_STORE_200));
    }

    public function rideDetails(Request $request, $trip_request_id): JsonResponse
    {
        $criteria = ['id' => $trip_request_id];
        $withAvgRelations = [['customerReceivedReviews', 'rating']];
        if (!is_null($request->type) && $request->type == 'overview') {
            $relations = ['customer' => [], 'vehicleCategory' => [], 'tripStatus' => [], 'time' => [], 'coordinate' => [], 'fee' => [], 'parcel' => [], 'parcelUserInfo' => [], 'parcelRefund' => [], 'fare_biddings' => [['driver_id', '=', auth('api')->id()]]];
            $overViewCriteria = array_merge($criteria, ['current_status' => PENDING]);
            $data = $this->tripRequestService->findOneBy(criteria: $overViewCriteria, withAvgRelations: $withAvgRelations, relations: $relations);
            if (!$data) {
                return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
            }
            if (!is_null($data)) {
                $resource = TripRequestResource::make($data);

                return response()->json(responseFormatter(DEFAULT_200, $resource));
            }
        } else {
            $relations = ['customer', 'vehicleCategory', 'tripStatus', 'time', 'coordinate', 'fee', 'parcel', 'parcelUserInfo', 'parcelRefund'];
            $data = $this->tripRequestService->findOneBy(criteria: $criteria, withAvgRelations: $withAvgRelations, relations: $relations);
            if ($data && auth('api')->id() == $data->driver_id) {
                $resource = TripRequestResource::make($data->append('distance_wise_fare'));

                return response()->json(responseFormatter(DEFAULT_200, $resource));
            }
        }

        return response()->json(responseFormatter(DEFAULT_404), 403);
    }

    public function pendingRideList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        if (empty($request->header('zoneId'))) {

            return response()->json(responseFormatter(ZONE_404));
        }
        $user = $this->userService->findOneBy(criteria: ['id' => auth('api')->id()], relations: ['driverDetails', 'vehicle']);
        if ($user->driverDetails->is_online != 1) {

            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }
        if (is_null($user->vehicle)) {
            return response()->json(responseFormatter(constant: VEHICLE_NOT_REGISTERED_404, content: []), 403);
        }
        if ($user?->vehicle?->is_active == 0) {
            return response()->json(responseFormatter(constant: VEHICLE_NOT_APPROVED_OR_ACTIVE_404, content: []), 403);
        }
        $maxParcelRequestAcceptLimit = businessConfig(key: 'maximum_parcel_request_accept_limit', settingsType: DRIVER_SETTINGS);
        $maxParcelRequestAcceptLimitStatus = (bool)($maxParcelRequestAcceptLimit?->value['status'] ?? false);
        $maxParcelRequestAcceptLimitCount = (int)($maxParcelRequestAcceptLimit?->value['limit'] ?? 0);
        $search_radius = (double)get_cache('search_radius') ?? 5;
        $location = $this->userLastLocationService->findOneBy(criteria: ['user_id' => $user->id]);
        if (!$location) {
            return response()->json(responseFormatter(constant: DEFAULT_200, content: ''));
        }
        if (!$user->vehicle) {
            return response()->json(responseFormatter(constant: DEFAULT_200, content: ''));
        }
        $acceptedTrip = $this->tripRequestService->findOneBy(criteria: ['driver_id' => $user->id, 'type' => RIDE_REQUEST, 'current_status' => ACCEPTED]);

        if ($acceptedTrip) {
            $data = array_merge(
                $validator->validated(),
                ['driver_locations' => $location],
                ['distance' => $search_radius * 1000],
                ['zone_id' => $request->header('zoneId')],
                ['vehicle_category_id' => $user?->vehicle?->category_id],
                ['ride_count' => $user?->driverDetails->ride_count ?? 0],
                ['parcel_count' => $user?->driverDetails->parcel_count ?? 0],
                ['parcel_follow_status' => $maxParcelRequestAcceptLimitStatus],
                ['max_parcel_request_accept_limit_count' => $maxParcelRequestAcceptLimitCount],
            );
            $trips = $this->tripRequestService->getPendingRide(data: $data);

            $transformedTrips = TripRequestResource::collection($trips);

            return response()->json(
                responseFormatter(
                    constant: DEFAULT_200,
                    content: $transformedTrips,
                    limit: $request['limit'],
                    offset: $request['offset'],
                )
            );
        }

        $ongoingTrip = $this->tripRequestService->findOneBy(criteria: ['driver_id' => $user->id, 'type' => RIDE_REQUEST, 'current_status' => ONGOING], relations: ['coordinate', 'driver.lastLocations']);
        if ($ongoingTrip) {
            $destinationCoordinates = json_decode($ongoingTrip->coordinate, true);
            $destinationLongitude = (float)$destinationCoordinates['destination_coordinates']['coordinates'][0];
            $destinationLatitude = (float)$destinationCoordinates['destination_coordinates']['coordinates'][1];
            $driverLastLongitude = (float)$ongoingTrip->driver->lastLocations->longitude;
            $driverLastLatitude = (float)$ongoingTrip->driver->lastLocations->latitude;
            $latDifference = deg2rad($destinationLatitude - $driverLastLatitude);
            $lonDifference = deg2rad($destinationLongitude - $driverLastLongitude);
            $earthRadius = 6371;
            $a = sin($latDifference / 2) * sin($latDifference / 2) +
                cos(deg2rad($driverLastLatitude)) * cos(deg2rad($destinationLatitude)) *
                sin($lonDifference / 2) * sin($lonDifference / 2);

            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = $earthRadius * $c;
            if ($distance > 1) {
                return response()->json(responseFormatter(constant: DEFAULT_200, content: ''));
            } else {
                $data = array_merge(
                    $validator->validated(),
                    ['driver_locations' => $location],
                    ['distance' => $search_radius * 1000],
                    ['zone_id' => $request->header('zoneId')],
                    ['vehicle_category_id' => $user?->vehicle?->category_id],
                    ['ride_count' => $user?->driverDetails->ride_count ?? 0],
                    ['parcel_count' => $user?->driverDetails->parcel_count ?? 0],
                    ['parcel_follow_status' => $maxParcelRequestAcceptLimitStatus],
                    ['max_parcel_request_accept_limit_count' => $maxParcelRequestAcceptLimitCount],
                );
                $trips = $this->tripRequestService->getPendingRide(data: $data);

                $transformedTrips = TripRequestResource::collection($trips);

                return response()->json(
                    responseFormatter(
                        constant: DEFAULT_200,
                        content: $transformedTrips,
                        limit: $request['limit'],
                        offset: $request['offset'],
                    )
                );
            }
        }


        $data = array_merge(
            $validator->validated(),
            ['driver_locations' => $location],
            ['distance' => $search_radius * 1000],
            ['zone_id' => $request->header('zoneId')],
            ['vehicle_category_id' => $user?->vehicle?->category_id],
            ['ride_count' => $user?->driverDetails->ride_count ?? 0],
            ['parcel_count' => $user?->driverDetails->parcel_count ?? 0],
            ['parcel_follow_status' => $maxParcelRequestAcceptLimitStatus],
            ['max_parcel_request_accept_limit_count' => $maxParcelRequestAcceptLimitCount],
        );
        $trips = $this->tripRequestService->getPendingRide(data: $data);

        $transformedTrips = TripRequestResource::collection($trips);

        return response()->json(
            responseFormatter(
                constant: DEFAULT_200,
                content: $transformedTrips,
                limit: $request['limit'],
                offset: $request['offset'],
            )
        );
    }

    public function returnedParcel(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'otp' => 'required|min:4|max:4',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id, 'type' => PARCEL], relations: ['driver', 'driver.driverDetails', 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        if ($trip->current_status == RETURNED) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNED_403), 403);
        }
        if ($trip->otp !== $request['otp']) {

            return response()->json(responseFormatter(OTP_MISMATCH_404), 403);
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
        //set driver availability_status as on_trip
        $driverDetails = $this->driverDetailService->findOneBy(criteria: ['user_id' => $trip->driver_id]);
        --$driverDetails->parcel_count;
        $driverDetails->save();
        $push = getNotification('parcel_returned');
        sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $request->trip_request_id,
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }

    public function tripOverview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filter' => ['required', Rule::in([TODAY, THIS_WEEK, LAST_WEEK])],
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $data = $this->tripRequestService->tripOverview(data: $validator->validated());
        return $data;
    }

    public function ignoreTripNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $this->tempTripNotificationService->deleteBy(criteria: ['trip_request_id' => $request->trip_request_id, 'user_id' => auth('api')->id()]);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }

    public function rideStatusUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'trip_request_id' => 'required',
            'return_time' => 'sometimes',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer']);

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(DEFAULT_400), 403);
        }
        if ($trip->current_status == 'cancelled') {
            return response()->json(responseFormatter(TRIP_STATUS_CANCELLED_403), 403);
        }
        if ($trip->current_status == 'completed') {
            return response()->json(responseFormatter(TRIP_STATUS_COMPLETED_403), 403);
        }
        if ($trip->current_status == RETURNING) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNING_403), 403);
        }
        if ($trip->is_paused) {

            return response()->json(responseFormatter(TRIP_REQUEST_PAUSED_404), 403);
        }

        $data = $this->tripRequestService->updateRideStatus(data: array_merge($validator->validated(), ['trip' => $trip]));

        $tripType = $trip->type == RIDE_REQUEST ? 'trip' : 'parcel';
        //Get status wise notification message
        if ($request->status == 'cancelled' && $trip->type == PARCEL) {
            $push = getNotification($tripType . '_canceled');
            sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip->type,
                action: $push['action'],
                user_id: $trip->customer->id
            );
        } else {
            $action = $request->status == 'cancelled' ? 'trip_canceled' : 'trip_' . $request->status;
            $push = getNotification($action);
            sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip->type,
                action: $action,
                user_id: $trip->customer->id
            );
        }
        if ($request->status == "completed") {
            try {
                checkPusherConnection(DriverTripCompletedEvent::broadcast($trip));
            } catch (\Exception $exception) {

            }
        }
        if ($request->status == "cancelled") {
            try {
                checkPusherConnection(DriverTripCancelledEvent::broadcast($trip));
            } catch (\Exception $exception) {

            }
        }

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, $data));
    }

    public function requestAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'action' => 'required|in:accepted,rejected',
        ]);


        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $user = auth('api')->user();
        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id]);
        $user_status = $user->driverDetails->availability_status;
        if ($user_status == 'unavailable' || !$user->driverDetails->is_online) {
            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }
        if ($trip->current_status == ACCEPTED && $trip->driver_id != $user->id) {

            return response()->json(responseFormatter(TRIP_REQUEST_DRIVER_403), 403);
        }
        if ($trip->current_status == ACCEPTED && $trip->driver_id == $user->id) {

            return response()->json(responseFormatter(DEFAULT_UPDATE_200));
        }
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id && $trip->driver_id != $user->id) {

            return response()->json(responseFormatter(TRIP_REQUEST_DRIVER_403), 403);
        }
        if ($request['action'] != ACCEPTED) {
            if (get_cache('bid_on_fare') ?? 0) {
                $allBidding = $this->fareBiddingService->getBy(criteria: ['trip_request_id' => $request['trip_request_id'], 'driver_id' => $user?->id]);
                if (count($allBidding) > 0) {
                    $push = getNotification('driver_canceled_ride_request');
                    sendDeviceNotification(
                        fcm_token: $trip->customer->fcm_token,
                        title: translate($push['title']),
                        description: translate(textVariableDataFormat(value: $push['description'])),
                        status: $push['status'],
                        ride_request_id: $trip->id,
                        type: $trip->type,
                        action: $push['action'],
                        user_id: $trip->customer->id
                    );
                    $this->fareBiddingService->deleteBy(criteria: ['trip_request_id' => $request['trip_request_id'], 'driver_id' => $user?->id]);
                }
            }

            $data = $this->tempTripNotificationService->findOneBy(criteria: ['trip_request_id' => $request['trip_request_id'], 'user_id' => auth('api')->id()]);
            if ($data) {
                $data->delete();
            }
            $this->rejectedDriverRequestService->create([
                'trip_request_id' => $request['trip_request_id'],
                'user_id' => $user?->id
            ]);
            return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
        }
        $env = env('APP_MODE');
        $otp = $env != "live" ? '0000' : rand(1000, 9999);
        $driverCurrentStatus = $this->driverDetailService->findOneBy(criteria: ['user_id' => $user->id], whereInCriteria: ['availability_status' => ['available', 'on_bidding']]);
        if (!$driverCurrentStatus) {
            return response()->json(responseFormatter(DRIVER_403), 403);
        }
        if ($trip->current_status === "cancelled") {
            return response()->json(responseFormatter(DRIVER_REQUEST_ACCEPT_TIMEOUT_408), 403);
        }
        $bid_on_fare = get_cache('bid_on_fare') ?? 0;
        $attributes = [
            'driver_id' => $user->id,
            'otp' => $otp,
            'vehicle_id' => $user->vehicle->id,
            'vehicle_category_id' => $user->vehicle->category_id,
            'current_status' => ACCEPTED,
            'trip_status' => ACCEPTED,
        ];
        if ($bid_on_fare) {
            $bidding = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id, 'driver_id' => $user->id, 'is_ignored' => 0]);
            if ($bidding) {
                return response()->json(responseFormatter(constant: BIDDING_SUBMITTED_403), 403);
            }
            if ($trip->estimated_fare != $trip->actual_fare) {
                $this->fareBiddingService->create(data: [
                    'trip_request_id' => $request['trip_request_id'],
                    'driver_id' => $user->id,
                    'customer_id' => $trip->customer_id,
                    'bid_fare' => $trip->actual_fare
                ]);
                $attributes['actual_fare'] = $trip->actual_fare;
            }

        }
        Cache::put($trip->id, ACCEPTED, now()->addHour());
        $driverArrivalTime = getRoutes(
            originCoordinates: [
                $trip->coordinate->pickup_coordinates->latitude,
                $trip->coordinate->pickup_coordinates->longitude
            ],
            destinationCoordinates: [
                $user->lastLocations->latitude,
                $user->lastLocations->longitude
            ],
        );

        $attributes['driver_arrival_time'] = (double)($driverArrivalTime[0]['duration']) / 60;
        $data = $this->tempTripNotificationService->getData(data: ['trip_request_id' => $request->trip_request_id]);
        if (!empty($data)) {
            $push = getNotification('another_driver_assigned');
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
                    checkPusherConnection(AnotherDriverTripAcceptedEvent::broadcast($tempNotification->user, $trip));
                } catch (\Exception $exception) {

                }
            }
            $this->tempTripNotificationService->deleteBy(criteria: ['trip_request_id' => $request->trip_request_id, 'user_id' => $user->id]);
        }
        DB::beginTransaction();
        try {
            $lockedTrip = $this->tripRequestService->getLockedTrip(data: ['id' => $request->trip_request_id]);

            if (!$lockedTrip) {
                return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
            }

            if ($lockedTrip->driver_id) {
                return response()->json(responseFormatter(constant: TRIP_REQUEST_DRIVER_403), 403);
            }

            $trip = $this->tripRequestService->updateTripRequestAction(attributes: $attributes, trip: $lockedTrip);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        if ($trip->type == PARCEL && $trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->contact_number && businessConfig('parcel_tracking_message')?->value && businessConfig('parcel_tracking_status')?->value && businessConfig('parcel_tracking_status')?->value == 1) {
            $parcelTemplateMessage = businessConfig('parcel_tracking_message')?->value;
            $smsTemplate = smsTemplateDataFormat(
                value: $parcelTemplateMessage,
                customerName: $trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->name,
                parcelId: $trip->ref_id,
                trackingLink: route('track-parcel', $trip->ref_id)
            );
            try {
                self::send($trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->contact_number, $smsTemplate);
            } catch (\Exception $exception) {

            }
        }
        $trip->tripStatus()->update([
            'accepted' => now()
        ]);

        $this->rejectedDriverRequestService->deleteBy(criteria: ['trip_request_id' => $request->trip_request_id]);
        $push = getNotification('driver_on_the_way');
        sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $request['trip_request_id'],
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->customer->id
        );
        try {
            checkPusherConnection(DriverTripAcceptedEvent::broadcast($trip));
        } catch (\Exception $exception) {

        }
        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }

    public function bid(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if ($user->driverDetails->availability_status != 'available' || $user->driverDetails->is_online != 1) {

            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'bid_fare' => 'numeric',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->tripRequestService->findOneBy(criteria: ['id' => $request->trip_request_id], relations: ['customer']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id) {

            return response()->json(responseFormatter(constant: TRIP_REQUEST_DRIVER_403), 403);
        }

        $bidding = $this->fareBiddingService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id, 'driver_id' => $user->id]);
        if ($bidding) {

            return response()->json(responseFormatter(constant: BIDDING_SUBMITTED_403), 403);
        }
        $this->fareBiddingService->create(data: [
            'trip_request_id' => $request['trip_request_id'],
            'driver_id' => $user->id,
            'customer_id' => $trip->customer_id,
            'bid_fare' => $request['bid_fare']
        ]);

        $push = getNotification('received_new_bid');
        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(constant: BIDDING_ACTION_200));
    }
}
