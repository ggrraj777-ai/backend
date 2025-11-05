<?php

namespace Modules\TripManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ParcelManagement\Transformers\InformationResource;
use Modules\ParcelManagement\Transformers\UserResource;
use Modules\PromotionManagement\Transformers\CouponResource;
use Modules\PromotionManagement\Transformers\DiscountResource;
use Modules\UserManagement\Transformers\CustomerResource;
use Modules\UserManagement\Transformers\DriverResource;
use Modules\VehicleManagement\Transformers\VehicleModelResource;
use Modules\VehicleManagement\Transformers\VehicleCategoryResource;
use Modules\VehicleManagement\Transformers\VehicleResource;
use Modules\ZoneManagement\Transformers\ZoneResource;

class TripRequestResource extends JsonResource
{
    public static $key = false;

    public static function setData($key)
    {
        self::$key = $key;
        return __CLASS__;
    }

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $fee = [];
        $trip_request = [
            'id' => $this->id,
            'ref_id' => $this->ref_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'driver' => DriverResource::make($this->whenLoaded('driver')),
            'vehicle_category' => VehicleCategoryResource::make($this->whenLoaded('vehicleCategory')),
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'zone' => ZoneResource::make($this->whenLoaded('zone')),
            'model' => VehicleModelResource::make($this->whenLoaded('vehicle.model')),
            'estimated_fare' => round((double)$this->estimated_fare, 2),
            'actual_fare' => $this->actual_fare,
            'return_fee' => $this->return_fee,
            'return_time' => $this->return_time,
            'due_amount' => $this->due_amount,
            'discount_actual_fare' => $this->discount_actual_fare,
            'estimated_distance' => $this->estimated_distance,
            'paid_fare' => round((double)$this->paid_fare, 2),
            'actual_distance' => round((double)$this->actual_distance, 2),
            'accepted_by' => $this->accepted_by,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'coupon_amount' => round((double)$this->coupon_amount, 2),
            'discount' => $this->discount_id === null ? null : DiscountResource::make($this->discount),
            'discount_amount' => $this->discount_amount === null ? null : round((double)$this->discount_amount, 2),
            'note' => $this->note,
            'otp' => $this->otp,
            'rise_request_count' => $this->rise_request_count,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'entrance' => $this->entrance,
            'encoded_polyline' => $this->encoded_polyline,
            'customer_review' => !($this->customerReceivedReview == null),
            'driver_review' => !($this->driverReceivedReview == null),
            'customer_avg_rating' => $this->customer_received_reviews_avg_rating,
            'driver_avg_rating' => $this->driver_received_reviews_avg_rating,
            'current_status' => $this->current_status,
            'is_paused' => (bool)$this->is_paused,
            // Fare breakdown computed from admin settings
            'fare_breakdown' => (function(){
                $fare = (double)($this->actual_fare ?? $this->estimated_fare ?? 0);
                if ($fare <= 0) return null;
                return calculateFareBreakdown($fare);
            })(),
            'fare_biddings' => FareBiddingResource::collection($this->whenLoaded('fare_biddings')),
            'parcel_information' => InformationResource::make($this->whenLoaded('parcel')),
            'parcel_user_info' => UserResource::collection($this->whenLoaded('parcelUserInfo')),
            'coupon' => $this->coupon_id === null ? null : CouponResource::make($this->coupon),
            'tripStatus' => TripStatusResource::make($this->whenLoaded('tripStatus')),
            'screenshot' => $this->map_screenshot,
            'parcel_start_time' => $this->type === PARCEL ? ($this->tripStatus?->ongoing ?? null) : null,
            'ride_start_time' => $this->type === RIDE_REQUEST ? ($this->tripStatus?->ongoing ?? null) : null,
            'parcel_complete_time' => $this->type === PARCEL ? ($this->tripStatus?->completed ?? null) : null,
            'ride_complete_time' => $this->type === RIDE_REQUEST ? ($this->tripStatus?->completed ?? null) : null,
            'parcel_refund' => ParcelRefundResource::make($this->whenLoaded('parcelRefund')),
            'driver_safety_alert' => SafetyAlertResource::make($this->driverSafetyAlert),
            'customer_safety_alert' => SafetyAlertResource::make($this->customerSafetyAlert),
        ];

        // ----- Safe coordinate handling -----
        $coordinate = [];

        // Check if relation exists in DB first; then try to get the loaded relation or fetch it safely.
        if ($this->coordinate()->exists()) {
            // If relation is already loaded, use it; otherwise fetch first() so we have an object.
            $coord = $this->relationLoaded('coordinate') ? $this->coordinate : $this->coordinate()->first();

            if ($coord) {
                $coordinate = [
                    'pickup_coordinates' => $coord->pickup_coordinates ?? null,
                    'pickup_address' => $coord->pickup_address ?? null,
                    'destination_coordinates' => $coord->destination_coordinates ?? null,
                    'destination_address' => $coord->destination_address ?? null,
                    'start_coordinates' => $coord->start_coordinates ?? null,
                    'drop_coordinates' => $coord->drop_coordinates ?? null,
                    'driver_accept_coordinates' => $coord->driver_accept_coordinates ?? null,
                    'customer_request_coordinates' => $coord->customer_request_coordinates ?? null,
                    'intermediate_coordinates' => $coord->intermediate_coordinates ?? null,
                    'intermediate_addresses' => $coord->intermediate_addresses ?? null,
                    'is_reached_destination' => isset($coord->is_reached_destination) ? (bool)$coord->is_reached_destination : false,
                    'is_reached_1' => isset($coord->is_reached_1) ? (bool)$coord->is_reached_1 : false,
                    'is_reached_2' => isset($coord->is_reached_2) ? (bool)$coord->is_reached_2 : false,
                ];
            }
        }

        // ----- Safe fee handling -----
        if ($this->fee()->exists()) {
            $feeModel = $this->relationLoaded('fee') ? $this->fee : $this->fee()->first();
            if ($feeModel) {
                $fee = [
                    'waiting_fee' => round((double)($feeModel->waiting_fee ?? 0), 2),
                    'waited_by' => $feeModel->waited_by ?? null,
                    'idle_fee' => round((double)($feeModel->idle_fee ?? 0), 2),
                    'delay_fee' => round((double)($feeModel->delay_fee ?? 0), 2),
                    'delayed_by' => $feeModel->delayed_by ?? null,
                    'cancellation_fee' => round((double)($feeModel->cancellation_fee ?? 0), 2),
                    'cancelled_by' => $feeModel->cancelled_by ?? null,
                    'vat_tax' => round((double)($feeModel->vat_tax ?? 0), 2),
                    'admin_commission' => round((double)($feeModel->admin_commission ?? 0), 2),
                    'tips' => round((double)($feeModel->tips ?? 0), 2),
                    'distance_wise_fare' => $this->whenAppended('distance_wise_fare', $this->distance_wise_fare()),
                ];

                if (self::$key == 'distance_wise_fare') {
                    $fee = ['distance_wise_fare' => round($this->distance_wise_fare(), 2)];
                }
            }
        }

        // ----- Safe time handling -----
        $time = [];
        if ($this->time()->exists()) {
            $timeModel = $this->relationLoaded('time') ? $this->time : $this->time()->first();
            if ($timeModel) {
                $time = [
                    'waiting_time' => round((double)($timeModel->waiting_time ?? 0), 2),
                    'delay_time' => round((double)($timeModel->delay_time ?? 0), 2),
                    'idle_time' => round((double)($timeModel->idle_time ?? 0)),
                    'actual_time' => round((double)($timeModel->actual_time ?? 0), 2),
                    'estimated_time' => round((double)($timeModel->estimated_time ?? 0), 2),
                ];
            }
        }

        return array_merge($trip_request, $coordinate, $fee, $time);
    }
}