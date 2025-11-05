# Project Overview

- **Name**: DriveMond
- **Framework**: Laravel `^10.10` (PHP `^8.1`)
- **Architecture**: Modular using `nwidart/laravel-modules`
- **Primary DB**: MySQL
- **Auth**: Laravel Sanctum, Laravel Passport
- **Broadcasting**: Laravel Reverb/Pusher (`laravel/reverb`, `pusher/pusher-php-server`, `laravel-echo`, `pusher-js`)
- **Queues/Schedule**: Laravel default (see `routes/console.php`), queue `sync` by default
- **Assets**: Laravel Mix (`laravel-mix`) compiling `resources/js/app.js` and `resources/css/app.css`
- **Testing**: PHPUnit ^10 (see `phpunit.xml`)
- **Modules**: See `docs/Modules.md`

## Repository Top-Level Structure

- `app/` — Core Laravel app (broadcasting channels, events, HTTP controllers/middleware, jobs, providers, etc.)
- `Modules/` — Business features split into modules (enabled via `modules_statuses.json`)
- `routes/` — HTTP/API/console/broadcast routes (`web.php`, `api.php`, `channels.php`, `console.php`, install/update flows)
- `resources/` — Frontend resources (CSS/JS, Blade views, translations)
- `public/` — Public assets/build artifacts, service worker, landing page assets
- `config/`, `database/`, `bootstrap/`, `storage/`, `tests/` — Standard Laravel directories

## Key Entry Points

- HTTP web routes: `routes/web.php`
- HTTP API routes: `routes/api.php`
- Broadcasting channels: `routes/channels.php`
- Install flow: `routes/install.php`
- Update flow: `routes/update.php`
- Frontend bootstrap: `resources/js/app.js`, `resources/css/app.css`, built via `webpack.mix.js`

## Enabled Modules (`modules_statuses.json`)

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

## Notable Composer Packages

- `laravel/framework`, `laravel/sanctum`, `laravel/passport`
- `nwidart/laravel-modules` for modular structure
- Payment gateways: `stripe/stripe-php`, `razorpay/razorpay`, `unicodeveloper/laravel-paystack`, `mercadopago/dx-php`, `xendit/xendit-php`, `iyzico/iyzipay-php`
- PDF/Export: `barryvdh/laravel-dompdf`, `mpdf/mpdf`, `rap2hpoutre/fast-excel`
- Realtime: `laravel/reverb`, `pusher/pusher-php-server`, `cboden/ratchet`
- Others: `doctrine/dbal`, `guzzlehttp/guzzle`, `stevebauman/location`, `spatie/db-dumper`

See the other docs for setup, architecture, routes, broadcasting, and deployment details.
