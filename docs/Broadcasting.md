# Broadcasting

## Packages

- Backend: `laravel/reverb`, `pusher/pusher-php-server`
- Frontend: `laravel-echo`, `pusher-js`

## Environment

- Reverb: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`
- Pusher: `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER`, `PUSHER_HOST`, `PUSHER_PORT`, `PUSHER_SCHEME`

## Channels (`routes/channels.php`)

- Private user: `App.Models.User.{id}`
- Ride channels (samples): `ride-request.{id}`
- Chat and trip lifecycle channels using classes in `app/Broadcasting/`:
  - `customer-ride-chat.{id}` → `CustomerRideChatChannel`
  - `ride-chat.{id}` → `RideChatChannel`
  - `driver-trip-accepted.{id}` → `DriverTripAcceptedChannel`
  - `driver-trip-started.{id}` → `DriverTripStartedChannel`
  - `driver-trip-cancelled.{id}` → `DriverTripCancelledChannel`
  - `driver-trip-completed.{id}` → `DriverTripCompletedChannel`
  - `driver-payment-received.{id}` → `DriverPaymentReceivedChannel`
  - `another-driver-trip-accepted.{id}.{userId}` → `AnotherDriverTripAcceptedChannel`
  - `customer-trip-cancelled-after-ongoing.{id}` → `CustomerTripCanceledAfterOngoingChannel`
  - `customer-trip-cancelled.{id}.{userId}` → `CustomerTripCanceledChannel`
  - `customer-coupon-applied.{id}` / `customer-coupon-removed.{id}` → Coupon channels
  - `customer-trip-request.{id}`
  - `customer-trip-payment-successful.{id}`

## Frontend

- Configure Echo/Pusher using `MIX_*` vars in `.env` (`MIX_REVERB_*`, `MIX_PUSHER_*`).
- Initialize Echo in `resources/js/app.js`.
