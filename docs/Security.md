# Security

## Authentication & Authorization

- Uses Sanctum (API tokens) and Passport (OAuth) per `composer.json`.
- Ensure guards/providers are configured in `config/auth.php`.

## Environment & Secrets

- Never commit real `.env` values. Use `.env.example` as template.
- Rotate `APP_KEY` on fresh installs; keep mail and 3rd-party API keys secret.

## Broadcasting

- Validate channel authorization callbacks in `routes/channels.php`.
- Use HTTPS and WSS in production (`REVERB_SCHEME=https`, proper certs if self-hosted).

## Input Validation

- Use Form Requests/validation rules in controllers.

## Filesystem & Storage

- Set correct permissions on `storage/` and `bootstrap/cache/`.
- Use S3 or secured disks for user uploads if applicable.

## Payments

- Multiple gateways integrated. Store only necessary references, never raw card data.
- Verify webhooks and signatures per provider.
