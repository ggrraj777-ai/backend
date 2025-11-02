# Setup & Installation

## Prerequisites

- PHP 8.1+
- Composer
- Node.js 14+ and npm
- MySQL (or compatible)

## Installation Steps

1. Copy environment file:

```bash
cp .env.example .env
```

2. Configure `.env`:

- `APP_NAME`, `APP_URL`
- Database: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Broadcasting (Reverb/Pusher): `REVERB_*` and `PUSHER_*`
- Mail/S3/etc. as needed

3. Install PHP dependencies:

```bash
composer install
php artisan key:generate
```

4. Run migrations and seed as required:

```bash
php artisan migrate
# php artisan db:seed   # if seeders are available
```

5. Install frontend dependencies and build assets:

```bash
npm install
npm run dev   # or: npm run prod
```

6. Start the application:

```bash
php artisan serve
```

## Development Scripts

- `npm run dev` — Development build using Laravel Mix
- `npm run watch` — Watch and rebuild assets
- `npm run prod` — Production build

## Environment Variables (from `.env.example`)

- `APP_*`, `LOG_*`
- DB: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Cache/Queue/Session drivers
- Mail: `MAIL_*`
- Redis: `REDIS_*`
- Broadcasting: `REVERB_*`, `PUSHER_*`, `MIX_REVERB_*`, `MIX_PUSHER_*`
- AWS S3: `AWS_*` (optional)
