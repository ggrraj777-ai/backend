# Architecture

## High-Level

- **Laravel 10** application using **modular architecture** via `nwidart/laravel-modules`.
- Core app code under `app/`; feature modules under `Modules/`.
- Autoloaded helpers in `composer.json` (`app/Lib/*.php`, `app/Library/*.php`, and selected `Modules/*/Lib/*.php`).

## Autoloading

`composer.json`:

- PSR-4 namespaces: `App\` (`app/`), `Modules\` (`Modules/`), `Database\*`
- Files autoloaded: `app/Lib/Helpers.php`, `app/Lib/Constant.php`, `app/Lib/Response.php`, `app/Lib/QueryInterface.php`, and various library files under `Modules/...`

## Modules

- Modules encapsulate domain features (controllers, models, migrations, views, routes, config).
- Enable/disable via `modules_statuses.json`.
- See `docs/Modules.md` for per-module notes.

## Routing

- `routes/web.php` — web endpoints (public pages, parcel tracking, payments, tests)
- `routes/api.php` — API endpoints (currently default user route with Sanctum)
- `routes/channels.php` — broadcasting channel authorizations
- `routes/install.php` — installation wizard
- `routes/update.php` — update process

## Broadcasting

- Configured with Reverb/Pusher; channels defined in `routes/channels.php` and classes under `app/Broadcasting/`.
- Frontend uses `laravel-echo` and `pusher-js`.

## Assets

- Laravel Mix config in `webpack.mix.js` compiles `resources/js/app.js` and `resources/css/app.css` into `public/`.
- `public/mix-manifest.json` maps built files.

## Storage & Logs

- Runtime files in `storage/` (cache, logs, sessions). Ensure proper permissions in production.

## Testing

- PHPUnit configured via `phpunit.xml`.
