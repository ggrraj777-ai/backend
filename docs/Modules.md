# Modules

Enabled modules (`modules_statuses.json`):

- AdminModule
- UserManagement
- FareManagement
- ZoneManagement
- VehicleManagement
- PromotionManagement
- BusinessManagement
- AuthManagement
- ParcelManagement
- TripManagement
- ChattingManagement
- ReviewModule
- Gateways
- TransactionManagement

## Typical Module Structure

Common subfolders inside each `Modules/<ModuleName>/`:

- `Config/`, `Database/`, `Entities/` (models), `Http/` (controllers, requests, middleware), `Resources/` (views, lang), `Routes/`, `Providers/`, `Lib/`.

## Notes by Area (high-level)

- **TripManagement**: Entities like `TripRequest`, events for trip lifecycle.
- **ChattingManagement**: Real-time chat channels and related broadcasting classes.
- **Gateways**: Payment integration abstractions for Stripe, Razorpay, Paystack, MercadoPago, Xendit, IyziPay.
- **UserManagement**: User roles, profiles, authentication flows (with Sanctum/Passport where applicable).
- **Business/Promotion/Vehicle/Zone/Fare**: Domain configuration and operations to support trips and pricing.

Refer to the module directories for detailed controllers, routes, and views.
