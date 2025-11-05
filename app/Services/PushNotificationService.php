<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Jobs\SendSinglePushNotificationJob;
use Modules\UserManagement\Entities\User;
use Modules\TripManagement\Entities\TripRequest;

/**
 * Enhanced Push Notification Service
 * Handles all push notifications for ride and payment status updates
 */
class PushNotificationService
{
    /**
     * Send ride request notification to driver
     */
    public static function sendRideRequestToDriver(User $driver, TripRequest $trip): void
    {
        if (!$driver->fcm_token || !$driver->is_active) {
            return;
        }

        $notification = [
            'title' => 'New Ride Request',
            'description' => "New ride request from {$trip->customer->first_name}",
            'status' => 'pending',
            'ride_request_id' => $trip->id,
            'type' => 'ride_request',
            'action' => 'view_ride',
            'user_id' => $driver->id,
        ];

        sendDeviceNotification(
            fcm_token: $driver->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }

    /**
     * Send ride accepted notification to customer
     */
    public static function sendRideAcceptedToCustomer(TripRequest $trip): void
    {
        if (!$trip->customer || !$trip->customer->fcm_token) {
            return;
        }

        $notification = [
            'title' => 'Ride Accepted',
            'description' => "Your ride has been accepted by {$trip->driver->first_name}",
            'status' => 'accepted',
            'ride_request_id' => $trip->id,
            'type' => 'ride_accepted',
            'action' => 'view_ride',
            'user_id' => $trip->customer->id,
        ];

        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }

    /**
     * Send ride started notification to customer
     */
    public static function sendRideStartedToCustomer(TripRequest $trip): void
    {
        if (!$trip->customer || !$trip->customer->fcm_token) {
            return;
        }

        $notification = [
            'title' => 'Ride Started',
            'description' => 'Your ride has started. Enjoy your journey!',
            'status' => 'ongoing',
            'ride_request_id' => $trip->id,
            'type' => 'ride_started',
            'action' => 'view_ride',
            'user_id' => $trip->customer->id,
        ];

        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }

    /**
     * Send ride completed notification to customer
     */
    public static function sendRideCompletedToCustomer(TripRequest $trip): void
    {
        if (!$trip->customer || !$trip->customer->fcm_token) {
            return;
        }

        $notification = [
            'title' => 'Ride Completed',
            'description' => 'Your ride has been completed. Please proceed to payment.',
            'status' => 'completed',
            'ride_request_id' => $trip->id,
            'type' => 'ride_completed',
            'action' => 'make_payment',
            'user_id' => $trip->customer->id,
        ];

        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }

    /**
     * Send payment received notification to driver
     */
    public static function sendPaymentReceivedToDriver(TripRequest $trip): void
    {
        if (!$trip->driver || !$trip->driver->fcm_token) {
            return;
        }

        $amount = $trip->paid_fare ?? $trip->estimated_fare;
        
        $notification = [
            'title' => 'Payment Received',
            'description' => "Payment of ₹{$amount} received for trip #{$trip->id}",
            'status' => 'paid',
            'ride_request_id' => $trip->id,
            'type' => 'payment_received',
            'action' => 'view_trip',
            'user_id' => $trip->driver->id,
        ];

        sendDeviceNotification(
            fcm_token: $trip->driver->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }

    /**
     * Send payment successful notification to customer
     */
    public static function sendPaymentSuccessfulToCustomer(TripRequest $trip): void
    {
        if (!$trip->customer || !$trip->customer->fcm_token) {
            return;
        }

        $amount = $trip->paid_fare ?? $trip->estimated_fare;
        
        $notification = [
            'title' => 'Payment Successful',
            'description' => "Your payment of ₹{$amount} was successful. Thank you!",
            'status' => 'paid',
            'ride_request_id' => $trip->id,
            'type' => 'payment_successful',
            'action' => 'view_trip',
            'user_id' => $trip->customer->id,
        ];

        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }

    /**
     * Send driver arriving notification to customer
     */
    public static function sendDriverArrivingToCustomer(TripRequest $trip, int $estimatedMinutes): void
    {
        if (!$trip->customer || !$trip->customer->fcm_token) {
            return;
        }

        $notification = [
            'title' => 'Driver Arriving',
            'description' => "Your driver will arrive in approximately {$estimatedMinutes} minutes",
            'status' => 'arriving',
            'ride_request_id' => $trip->id,
            'type' => 'driver_arriving',
            'action' => 'view_ride',
            'user_id' => $trip->customer->id,
        ];

        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }

    /**
     * Send driver reached pickup notification to customer
     */
    public static function sendDriverReachedPickup(TripRequest $trip): void
    {
        if (!$trip->customer || !$trip->customer->fcm_token) {
            return;
        }

        $notification = [
            'title' => 'Driver Reached',
            'description' => 'Your driver has reached the pickup location',
            'status' => 'reached',
            'ride_request_id' => $trip->id,
            'type' => 'driver_reached',
            'action' => 'view_ride',
            'user_id' => $trip->customer->id,
        ];

        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }

    /**
     * Send ride cancelled notification
     */
    public static function sendRideCancelled(TripRequest $trip, string $cancelledBy): void
    {
        $recipient = $cancelledBy === 'driver' ? $trip->customer : $trip->driver;
        
        if (!$recipient || !$recipient->fcm_token) {
            return;
        }

        $cancellerName = $cancelledBy === 'driver' ? 'Driver' : 'Customer';
        
        $notification = [
            'title' => 'Ride Cancelled',
            'description' => "{$cancellerName} has cancelled the ride",
            'status' => 'cancelled',
            'ride_request_id' => $trip->id,
            'type' => 'ride_cancelled',
            'action' => 'view_trip',
            'user_id' => $recipient->id,
        ];

        sendDeviceNotification(
            fcm_token: $recipient->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }

    /**
     * Send payment reminder to customer
     */
    public static function sendPaymentReminder(TripRequest $trip): void
    {
        if (!$trip->customer || !$trip->customer->fcm_token) {
            return;
        }

        $amount = $trip->paid_fare ?? $trip->estimated_fare;
        
        $notification = [
            'title' => 'Payment Pending',
            'description' => "Please complete payment of ₹{$amount} for trip #{$trip->id}",
            'status' => 'pending',
            'ride_request_id' => $trip->id,
            'type' => 'payment_reminder',
            'action' => 'make_payment',
            'user_id' => $trip->customer->id,
        ];

        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: $notification['title'],
            description: $notification['description'],
            status: $notification['status'],
            ride_request_id: $notification['ride_request_id'],
            type: $notification['type'],
            action: $notification['action'],
            user_id: $notification['user_id'],
        );
    }
}

