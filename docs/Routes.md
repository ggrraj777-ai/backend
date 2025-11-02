# Routes

## Web (`routes/web.php`)

- `GET /` → `LandingPageController@index`
- `GET /contact-us` → `LandingPageController@contactUs`
- `GET /about-us` → `LandingPageController@aboutUs`
- `GET /privacy` → `LandingPageController@privacy`
- `GET /terms` → `LandingPageController@terms`
- `GET /track-parcel/{id}` → `ParcelTrackingController@trackingParcel`
- Payments: `GET /add-payment-request`, `/payment-success`, `/payment-fail`, `/payment-cancel` → `PaymentRecordController`
- Diagnostics/tests: `/sender`, `/test-connection`, `/update-data-test`, `/sms-test`, `/firebase-gen`, `/trigger`, `/test`

## API (`routes/api.php`)

- `GET /api/user` (auth:sanctum) → Current user

## Console (`routes/console.php`)

- `artisan inspire` — prints an inspiring quote

## Install (`routes/install.php`)

- Steps 0–5 and actions: `database_installation`, `import_sql`, `force-import-sql`, `system_settings`, `purchase_code`

## Update (`routes/update.php`)

- `ANY /` → `UpdateController@update_software_index`
- `ANY /update-system` → `UpdateController@update_software`
