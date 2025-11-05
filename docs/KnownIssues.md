# Known Issues & Review Notes

- **Suspicious NPM dependency**
  - `package.json` lists dependency `"-": "^0.0.1"` which is likely invalid/no-op and should be removed.

- **`vendor/` empty**
  - `vendor/` is empty, meaning Composer dependencies are not installed. Run `composer install` in local/dev.

- **Broadcasting defaults**
  - `.env.example` sets `APP_URL=localhost` and Reverb/Pusher on port `6015` over `http`. For production, set a proper domain, use `https`, and configure cert paths if self-hosting Reverb.

- **Dual Auth packages**
  - Both Sanctum and Passport are required. Confirm which areas use which and avoid overlap/conflict in guards.

- **API routes minimal**
  - `routes/api.php` only has the default `/user` route. Most API endpoints may live inside module route files; ensure they are documented and loaded.

- **Large modules footprint**
  - Many modules with 1000+ files total. Consider enabling only required modules in `modules_statuses.json` per deployment.

- **Mix/Node versions**
  - `laravel-mix` v6 suggests Node 14/16 compatibility. Verify Node version to avoid build issues.
